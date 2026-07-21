<?php
/**
 * Ciclo de vida de información outbound de un pedido hacia Yuju.
 * Best-effort: nunca lanza excepciones hacia el flujo de creación.
 *
 * @see https://api-docs.yuju.io/docs/informacion-outbound
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuOrderOutbound
{
    const MODULE_NAME = 'prestashopyuju';
    const TYPE_ORDER = 'order';
    const INTEGRATION_LABEL = 'PrestaShop ↔ Yuju (módulo prestashopyuju)';

    /** @var YujuApiClient */
    protected $api;

    /** @var YujuLogger */
    protected $logger;

    /** @var int|string|null */
    protected $idChannel;

    /** @var string */
    protected $idOrder = '';

    /** @var string */
    protected $reference = '';

    /** @var string|null */
    protected $externalPk;

    /** @var string */
    protected $lastStatus = '';

    /** @var string */
    protected $lastMessage = '';

    /** @var string Paso legible actual */
    protected $currentStepLabel = '';

    /** @var array Contexto interno (IDs, etc.); no se vuelca crudo a Yuju */
    protected $context = [];

    /** @var string[] Líneas de historial legibles */
    protected $timeline = [];

    /** @var bool */
    protected $enabled = true;

    /** @var float|null */
    protected $lastApiAt;

    public function __construct($api_client = null, $logger = null)
    {
        $this->api = $api_client instanceof YujuApiClient ? $api_client : new YujuApiClient();
        $this->logger = $logger instanceof YujuLogger ? $logger : new YujuLogger();
        $flag = YujuConfig::get('YUJU_ORDER_OUTBOUND_ENABLED', 1);
        $this->enabled = !((string) $flag === '0' || $flag === false || $flag === 'false');
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getExternalPk()
    {
        return $this->externalPk;
    }

    public function getLastStatus()
    {
        return $this->lastStatus;
    }

    /**
     * Extra limpio que se envía a Yuju.
     *
     * @return array
     */
    public function getExtra()
    {
        return $this->buildClearExtra();
    }

    /**
     * @param int|string $id_channel
     * @param int|string $id_order
     * @param string $reference
     * @param array $context
     * @return bool
     */
    public function start($id_channel, $id_order, $reference = '', array $context = [])
    {
        if (!$this->enabled) {
            return false;
        }

        $this->idChannel = $id_channel;
        $this->idOrder = (string) $id_order;
        $this->reference = $reference !== '' ? (string) $reference : $this->idOrder;
        $this->context = $this->normalizeContext($context);
        $this->timeline = [];
        $this->currentStepLabel = 'Inicio';

        if ($this->idChannel === null || $this->idChannel === '' || $this->idOrder === '') {
            $this->logger->log(
                'YujuOrderOutbound::start skipped: missing id_channel or id_order',
                'warning'
            );
            return false;
        }

        return $this->step(
            'processing',
            'Recibido el pedido desde Yuju. Se está creando en PrestaShop.',
            [],
            'Inicio'
        );
    }

    /**
     * @param string $status
     * @param string $message
     * @param array $contextMerge IDs / datos útiles (se traducen a texto claro)
     * @param string $stepLabel Etiqueta corta del paso (ej. "Cliente", "Orden")
     * @return bool
     */
    public function step($status, $message, array $contextMerge = [], $stepLabel = '')
    {
        if (!$this->enabled) {
            return false;
        }
        if ($this->idChannel === null || $this->idChannel === '' || $this->idOrder === '') {
            return false;
        }

        $status = trim((string) $status);
        $message = trim((string) $message);
        if ($status === '' || $message === '') {
            return false;
        }

        $this->lastStatus = $status;
        $this->lastMessage = $message;
        if ($stepLabel !== '') {
            $this->currentStepLabel = $stepLabel;
        }

        foreach ($this->normalizeContext($contextMerge) as $k => $v) {
            $this->context[$k] = $v;
        }

        $this->timeline[] = date('d/m/Y H:i:s') . ' · ' . $message;
        if (count($this->timeline) > 15) {
            $this->timeline = array_slice($this->timeline, -15);
        }

        $body = $this->buildBody($status, $message);

        try {
            $this->throttle();
            if ($this->externalPk) {
                $response = $this->api->updateOrderOutbound(
                    $this->idChannel,
                    $this->idOrder,
                    $this->externalPk,
                    $body
                );
            } else {
                $response = $this->api->createOrderOutbound(
                    $this->idChannel,
                    $this->idOrder,
                    $body
                );
                $pk = $this->extractExternalPk($response);
                if ($pk !== '') {
                    $this->externalPk = $pk;
                    $this->persistMappingOutbound();
                }
            }
            $this->lastApiAt = microtime(true);

            $ok = is_array($response) && !empty($response['success']);
            if (!$ok) {
                $this->logger->log(
                    'YujuOrderOutbound step failed: ' . ($response['message'] ?? 'unknown')
                    . ' | order=' . $this->idOrder
                    . ' | status=' . $status,
                    'warning'
                );
                return false;
            }

            if (!$this->externalPk) {
                $pk = $this->extractExternalPk($response);
                if ($pk !== '') {
                    $this->externalPk = $pk;
                }
            }
            $this->persistMappingOutbound();

            return true;
        } catch (Exception $e) {
            $this->logger->log('YujuOrderOutbound::step exception: ' . $e->getMessage(), 'warning');
            return false;
        } catch (Throwable $e) {
            $this->logger->log('YujuOrderOutbound::step exception: ' . $e->getMessage(), 'warning');
            return false;
        }
    }

    /**
     * @param string $message
     * @param array $contextMerge
     * @return bool
     */
    public function finishSuccess($message = 'Orden creada correctamente en PrestaShop.', array $contextMerge = [])
    {
        return $this->step('created', $message, $contextMerge, 'Finalizado');
    }

    /**
     * @param string $message
     * @param array $contextMerge
     * @return bool
     */
    public function finishError($message, array $contextMerge = [])
    {
        $message = trim((string) $message);
        if ($message === '') {
            $message = 'No se pudo crear la orden en PrestaShop.';
        }
        // Mensajes técnicos en inglés → resumen claro
        $message = $this->humanizeErrorMessage($message);

        return $this->step('error', $message, $contextMerge, 'Error');
    }

    public function setExternalPk($pk)
    {
        $pk = trim((string) $pk);
        if ($pk !== '') {
            $this->externalPk = $pk;
        }
    }

    public function setReference($reference)
    {
        $reference = trim((string) $reference);
        if ($reference !== '') {
            $this->reference = $reference;
        }
    }

    /**
     * Estructura legible para el panel "Información extra" de Yuju.
     *
     * @return array
     */
    protected function buildClearExtra()
    {
        $extra = [
            'integracion' => self::INTEGRATION_LABEL,
            'estado_proceso' => $this->statusLabel($this->lastStatus),
            'paso_actual' => $this->currentStepLabel !== '' ? $this->currentStepLabel : '—',
            'ultimo_mensaje' => $this->lastMessage !== '' ? $this->lastMessage : '—',
            'historial' => $this->timeline,
            'pedido_yuju' => $this->idOrder,
            'referencia' => $this->reference !== '' ? $this->reference : $this->idOrder,
        ];

        if (!empty($this->context['prestashop_order_id'])) {
            $extra['pedido_prestashop'] = (int) $this->context['prestashop_order_id'];
        }
        if (!empty($this->context['order_reference'])) {
            $extra['referencia_prestashop'] = (string) $this->context['order_reference'];
        }
        if (!empty($this->context['customer_id'])) {
            $cust = 'ID ' . (int) $this->context['customer_id'];
            if (!empty($this->context['customer_status'])) {
                $cust .= ' (' . $this->labelCustomerStatus($this->context['customer_status']) . ')';
            }
            $extra['cliente'] = $cust;
        }
        if (!empty($this->context['shipping_address_id'])) {
            $addr = 'ID ' . (int) $this->context['shipping_address_id'];
            if (!empty($this->context['address_status'])) {
                $addr .= ' (' . $this->labelExistStatus($this->context['address_status']) . ')';
            }
            $extra['direccion_envio'] = $addr;
        }
        if (!empty($this->context['cart_id'])) {
            $cart = 'ID ' . (int) $this->context['cart_id'];
            if (!empty($this->context['cart_status'])) {
                $cart .= ' (' . $this->labelExistStatus($this->context['cart_status']) . ')';
            }
            $extra['carrito'] = $cart;
        }
        if (!empty($this->context['carrier_id'])) {
            $extra['transportista_id'] = (int) $this->context['carrier_id'];
        }
        if (!empty($this->context['stock_note'])) {
            $extra['stock'] = (string) $this->context['stock_note'];
        }
        if (!empty($this->context['error_detail'])) {
            $extra['detalle_error'] = (string) $this->context['error_detail'];
        }
        if (!empty($this->context['pending_note'])) {
            $extra['nota'] = (string) $this->context['pending_note'];
        }

        return $extra;
    }

    /**
     * @param array $raw
     * @return array
     */
    protected function normalizeContext(array $raw)
    {
        $out = [];
        $map = [
            'prestashop_order_id' => 'prestashop_order_id',
            'order_id' => 'prestashop_order_id',
            'order_reference' => 'order_reference',
            'customer_id' => 'customer_id',
            'customer_status' => 'customer_status',
            'shipping_address_id' => 'shipping_address_id',
            'address_status' => 'address_status',
            'cart_id' => 'cart_id',
            'cart_status' => 'cart_status',
            'carrier_id' => 'carrier_id',
            'stock_note' => 'stock_note',
            'error_detail' => 'error_detail',
            'customer_error' => 'error_detail',
            'shipping_address_error' => 'error_detail',
            'cart_error' => 'error_detail',
            'order_error' => 'error_detail',
            'pending_note' => 'pending_note',
        ];

        foreach ($raw as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            // stock_sync array → nota corta
            if ($k === 'stock_sync' && is_array($v)) {
                $ok = !empty($v['success']) || !empty($v['pushed']);
                $out['stock_note'] = $ok
                    ? 'Stock descontado en PrestaShop y actualizado en Yuju'
                    : ('Stock: ' . (isset($v['message']) ? (string) $v['message'] : 'revisar'));
                continue;
            }
            if ($k === 'stock_sync_error') {
                $out['stock_note'] = 'Error parcial al sincronizar stock: ' . (string) $v;
                continue;
            }
            if (isset($map[$k])) {
                $out[$map[$k]] = is_scalar($v) ? $v : json_encode($v);
            }
            // Ignorar force, mapping_status, phase, steps, module, etc.
        }

        return $out;
    }

    /**
     * @param string $status
     * @return string
     */
    protected function statusLabel($status)
    {
        $map = [
            'processing' => 'En proceso',
            'created' => 'Creada con éxito',
            'error' => 'Error',
        ];

        return isset($map[$status]) ? $map[$status] : ($status !== '' ? $status : '—');
    }

    /**
     * @param string $status
     * @return string
     */
    protected function labelCustomerStatus($status)
    {
        $s = strtoupper((string) $status);
        if ($s === 'CREATED') {
            return 'cliente nuevo';
        }
        if ($s === 'EXISTING') {
            return 'cliente existente';
        }

        return (string) $status;
    }

    /**
     * @param string $status
     * @return string
     */
    protected function labelExistStatus($status)
    {
        $s = strtoupper((string) $status);
        if ($s === 'CREATED') {
            return 'creado';
        }
        if ($s === 'EXISTING') {
            return 'ya existía';
        }

        return (string) $status;
    }

    /**
     * @param string $message
     * @return string
     */
    protected function humanizeErrorMessage($message)
    {
        $map = [
            'Failed to get or create customer' => 'No se pudo obtener o crear el cliente',
            'Failed to create shipping address' => 'No se pudo crear la dirección de envío',
            'Failed to create cart' => 'No se pudo crear el carrito',
            'Failed to create order in PrestaShop' => 'No se pudo crear la orden en PrestaShop',
        ];
        foreach ($map as $en => $es) {
            if (stripos($message, $en) === 0) {
                $rest = trim(substr($message, strlen($en)));
                $rest = ltrim($rest, ': ');

                return $rest !== '' ? ($es . ': ' . $rest) : $es;
            }
        }

        return $message;
    }

    /**
     * @param string $status
     * @param string $message
     * @return array
     */
    protected function buildBody($status, $message)
    {
        $channelId = is_numeric($this->idChannel) ? (int) $this->idChannel : $this->idChannel;

        return [
            'status' => $status,
            'message' => $message,
            'type' => self::TYPE_ORDER,
            'module_name' => self::MODULE_NAME,
            'reference' => $this->reference !== '' ? $this->reference : $this->idOrder,
            'channel_id' => $channelId,
            'extra' => $this->buildClearExtra(),
        ];
    }

    /**
     * @param array|null $response
     * @return string
     */
    protected function extractExternalPk($response)
    {
        if (!is_array($response)) {
            return '';
        }
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;
        if (!empty($data['order_int_external_pk'])) {
            return (string) $data['order_int_external_pk'];
        }
        if (isset($data[0]) && is_array($data[0]) && !empty($data[0]['order_int_external_pk'])) {
            return (string) $data[0]['order_int_external_pk'];
        }

        return '';
    }

    protected function persistMappingOutbound()
    {
        if ($this->idOrder === '' || !$this->externalPk) {
            return;
        }
        try {
            $table = _DB_PREFIX_ . 'yuju_order_mapping';
            $cols = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE "outbound_external_pk"');
            if (empty($cols)) {
                return;
            }
            $now = date('Y-m-d H:i:s');
            $sets = [
                'outbound_external_pk = "' . pSQL($this->externalPk) . '"',
                'outbound_status = "' . pSQL($this->lastStatus) . '"',
                'outbound_updated_at = "' . pSQL($now) . '"',
                'updated_at = "' . pSQL($now) . '"',
            ];
            $channelCols = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE "id_channel"');
            if (!empty($channelCols) && $this->idChannel !== null && $this->idChannel !== '') {
                $sets[] = 'id_channel = "' . pSQL((string) $this->idChannel) . '"';
            }
            Db::getInstance()->execute(
                'UPDATE `' . $table . '` SET ' . implode(', ', $sets)
                . ' WHERE yuju_order_id = "' . pSQL($this->idOrder) . '"'
            );
        } catch (Exception $e) {
            $this->logger->log('YujuOrderOutbound persistMappingOutbound: ' . $e->getMessage(), 'warning');
        } catch (Throwable $e) {
            $this->logger->log('YujuOrderOutbound persistMappingOutbound: ' . $e->getMessage(), 'warning');
        }
    }

    protected function throttle()
    {
        if ($this->lastApiAt === null) {
            return;
        }
        $elapsed = microtime(true) - $this->lastApiAt;
        $minGap = 0.55;
        if ($elapsed < $minGap) {
            usleep((int) (($minGap - $elapsed) * 1000000));
        }
    }
}
