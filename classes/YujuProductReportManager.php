<?php
/**
 * Coordinate Yuju's asynchronous general-products report.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuProductReportFileStore.php';

class YujuProductReportManager
{
    const STATE_CONFIG_KEY = 'YUJU_PRODUCTS_REPORT_STATE';
    const QUOTA_CONFIG_KEY = 'YUJU_PRODUCTS_REPORT_QUOTA';
    const OFFER_STATE_CONFIG_KEY = 'YUJU_PRODUCTS_OFFER_REPORT_STATE';

    private $api_client;
    private $file_store;

    public function __construct($api_client = null, $file_store = null)
    {
        $this->api_client = $api_client ?: new YujuApiClient();
        $this->file_store = $file_store ?: new YujuProductReportFileStore();
    }

    /**
     * Start one report or resume the current task without issuing a duplicate POST.
     *
     * @return array
     */
    public function startOrResumeReport()
    {
        // Recover existing files before retention cleanup. The previous order deleted
        // an active report after 24 hours, forcing needless downloads on the next visit.
        $state = $this->getStoredState();
        $offer_state = $this->getStoredOfferState();
        if (empty($offer_state['cache_id'])) {
            $local_offer = $this->file_store->discoverLatestValidOfferReport();
            if (is_array($local_offer) && !empty($local_offer['cache_id'])) {
                $offer_state = array_merge($offer_state, $local_offer);
                Configuration::updateValue(self::OFFER_STATE_CONFIG_KEY, json_encode($offer_state));
            }
        }
        $protected_cache_ids = [];
        foreach ([$state, $offer_state] as $cached_state) {
            if (!empty($cached_state['cache_id']) && preg_match('/^[a-f0-9]{32}$/', (string) $cached_state['cache_id'])) {
                $protected_cache_ids[] = (string) $cached_state['cache_id'];
            }
        }
        $this->file_store->cleanupExpired(86400, $protected_cache_ids);

        $has_cached_report = !empty($state['cache_id'])
            && preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])
            && $this->file_store->reportExists($state['cache_id']);

        if (!empty($state['task_id']) && in_array($state['status'], ['CREATED', 'PROCESSING'], true)) {
            return [
                'success' => true,
                'status' => $state['status'],
                'task_id' => $state['task_id'],
                'requested_at' => isset($state['requested_at']) ? (int) $state['requested_at'] : null,
                'reused' => true,
            ];
        }

        if (!empty($state['task_id']) && $state['status'] === 'COMPLETED' && $has_cached_report) {
            return [
                'success' => true,
                'status' => 'COMPLETED',
                'task_id' => $state['task_id'],
                'requested_at' => isset($state['requested_at']) ? (int) $state['requested_at'] : null,
                'reused' => true,
            ];
        }

        if (!empty($state['task_id']) && $state['status'] === 'COMPLETED' && !$has_cached_report) {
            // Remote completion is not enough: recover the existing task's download before creating another report.
            $state['status'] = 'DOWNLOAD_EXPIRED';
            $state['cache_id'] = null;
            $this->storeJson(self::STATE_CONFIG_KEY, $state);

            return $this->pollReport(true);
        }

        if (in_array($state['status'], ['DOWNLOAD_ERROR', 'DOWNLOAD_EXPIRED'], true) && !empty($state['task_id'])) {
            // Retry the saved task/download only; never spend quota by creating a replacement task.
            return $this->pollReport(true);
        }

        $requested_at = isset($state['requested_at']) ? (int) $state['requested_at'] : 0;
        $request_day = isset($state['request_day']) ? $state['request_day'] : '';
        $today = gmdate('Y-m-d');

        if ($state['status'] === 'UNKNOWN' && $request_day === $today) {
            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'Yuju pudo haber iniciado el reporte, pero no se recibió el ID de tarea. No se repetirá automáticamente para evitar consumir otra solicitud diaria; intenta de nuevo mañana UTC.',
                'requested_at' => $requested_at,
            ];
        }

        $quota = $this->getDailyQuota($today);

        if ($quota['count'] >= 2) {
            return [
                'success' => false,
                'status' => 'RATE_LIMITED',
                'message' => 'Ya se solicitaron los dos reportes generales permitidos por Yuju para hoy (UTC). El límite se restablece mañana a las 00:00 UTC.',
                'requested_at' => $requested_at,
            ];
        }

        ++$quota['count'];
        $state = [
            'status' => 'REQUESTING',
            'task_id' => null,
            'requested_at' => time(),
            'request_day' => $today,
        ];

        if (!$this->storeJson(self::QUOTA_CONFIG_KEY, $quota)
            || !$this->storeJson(self::STATE_CONFIG_KEY, $state)
        ) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'No se pudo guardar el control local del reporte; no se llamó a Yuju.',
            ];
        }

        try {
            $response = $this->api_client->createProductGeneralReport();
        } catch (Exception $e) {
            $state['status'] = 'UNKNOWN';
            $this->storeJson(self::STATE_CONFIG_KEY, $state);

            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'No se pudo confirmar si Yuju inició el reporte. No se repetirá automáticamente para evitar duplicar la solicitud diaria.',
                'requested_at' => $state['requested_at'],
            ];
        }

        if (empty($response['success'])) {
            $state['status'] = 'UNKNOWN';
            $this->storeJson(self::STATE_CONFIG_KEY, $state);

            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'Yuju no confirmó el inicio del reporte. No se repetirá automáticamente para evitar duplicar la solicitud diaria.',
                'requested_at' => $state['requested_at'],
            ];
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $task_id = isset($data['id_task']) ? (string) $data['id_task'] : '';

        if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $task_id)) {
            $state['status'] = 'UNKNOWN';
            $this->storeJson(self::STATE_CONFIG_KEY, $state);

            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'Yuju respondió sin un ID de tarea válido. No se repetirá automáticamente para evitar consumir la cuota.',
                'requested_at' => $state['requested_at'],
            ];
        }

        $state['task_id'] = $task_id;
        $state['status'] = isset($data['status']) ? strtoupper((string) $data['status']) : 'CREATED';

        if (!in_array($state['status'], ['CREATED', 'PROCESSING', 'COMPLETED'], true)) {
            $state['status'] = 'CREATED';
        }

        if (!$this->storeJson(self::STATE_CONFIG_KEY, $state)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Yuju creó el reporte, pero no se pudo guardar su estado local. No vuelvas a iniciarlo; revisa el registro antes de consumir otra solicitud.',
                'requested_at' => $state['requested_at'],
            ];
        }

        // Yuju can return COMPLETED in the initial POST response. Download and cache the
        // completed report now, instead of advertising a report the reconciler cannot read.
        if ($state['status'] === 'COMPLETED') {
            return $this->pollReport();
        }

        return [
            'success' => true,
            'status' => $state['status'],
            'task_id' => $task_id,
            'requested_at' => $state['requested_at'],
            'reused' => false,
        ];
    }

    /**
     * Request the offer report, then download and persist the actual file from its temporary URL.
     */
    public function downloadOfferReport()
    {
        // Reuse the actual local file first. The configuration pointer may have been
        // lost during an upgrade, so discover a valid local offer file before touching Yuju.
        $offer_state = $this->getStoredOfferState();
        if (empty($offer_state['cache_id']) || !$this->isUsableOfferCache($offer_state['cache_id'])) {
            $local_offer = $this->file_store->discoverLatestValidOfferReport();
            if (is_array($local_offer) && !empty($local_offer['cache_id'])) {
                $offer_state = array_merge($offer_state, $local_offer);
                $offer_state['downloaded_at'] = isset($local_offer['saved_at']) ? $local_offer['saved_at'] : time();
                Configuration::updateValue(self::OFFER_STATE_CONFIG_KEY, json_encode($offer_state));
            }
        }
        if (!empty($offer_state['cache_id'])) {
            try {
                $cached_products = $this->file_store->readOfferProducts($offer_state['cache_id']);
                if (!empty($cached_products)) {
                    // Restore the index and state even when the JSON file predates the
                    // current Configuration pointer or the metadata table was incomplete.
                    $this->persistOfferProductsToDatabase($cached_products, $offer_state['cache_id']);
                    $offer_state['product_count'] = count($cached_products);
                    $offer_state['report_size_bytes'] = isset($offer_state['report_size_bytes'])
                        ? (int) $offer_state['report_size_bytes']
                        : (int) @filesize(dirname(__DIR__) . '/var/reports/' . $offer_state['cache_id'] . '.json');
                    $offer_state['download_url'] = $this->getSavedOfferReportUrl($offer_state);
                    Configuration::updateValue(self::OFFER_STATE_CONFIG_KEY, json_encode($offer_state));
                    $this->saveReportMetadata([
                        'cache_id' => $offer_state['cache_id'],
                        'product_count' => count($cached_products),
                        'report_size_bytes' => $offer_state['report_size_bytes'],
                        'report_downloaded_at' => isset($offer_state['report_downloaded_at']) ? (int) $offer_state['report_downloaded_at'] : time(),
                        'download_url' => !empty($offer_state['download_url']) ? $offer_state['download_url'] : null,
                    ], 'offer');
                    return [
                        'success' => true,
                        'status' => 'completed',
                        'report_type' => 'offer',
                        'report_cached' => true,
                        'reused' => true,
                        'products_count' => count($cached_products),
                        'file_size_bytes' => (int) ($offer_state['report_size_bytes'] ?? 0),
                        'message' => 'Se reutilizó el reporte de stock/precio local; no se solicitó un reporte nuevo a Yuju.',
                    ];
                }
            } catch (Exception $e) {
                // The cache is missing or invalid. Recover from its saved URL below.
            }
        }

        $url = $this->getSavedOfferReportUrl($offer_state);
        $response_http_code = 200;

        if ($url === '') {
            // No cached file or saved URL exists. Only now request the report URL from Yuju.
            try {
                $response = $this->api_client->getProductOfferReport();
            } catch (Exception $e) {
                return ['success' => false, 'status' => 'failed', 'message' => 'No se pudo consultar el reporte de ofertas de Yuju.'];
            }

            if (empty($response['success'])) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'http_code' => (int) ($response['http_code'] ?? 0),
                    'message' => isset($response['message']) ? (string) $response['message'] : 'Yuju rechazó la solicitud del reporte de ofertas.',
                ];
            }

            $payload = $response['data'] ?? null;
            $url = is_array($payload) && isset($payload['url']) && is_string($payload['url'])
                ? trim($payload['url'])
                : '';
            $response_http_code = (int) ($response['http_code'] ?? 200);

            if ($url === '') {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'http_code' => $response_http_code,
                    'message' => 'Yuju respondió, pero no incluyó una URL de descarga válida en el campo "url".',
                ];
            }

            // Persist the URL before downloading so a failed transfer can be retried
            // without calling the report endpoint again.
            $offer_state['download_url'] = $url;
            Configuration::updateValue(self::OFFER_STATE_CONFIG_KEY, json_encode($offer_state));
        }

        try {
            $saved = $this->file_store->downloadRawReportFile($url);
            $offer_products = $this->file_store->readOfferProducts($saved['cache_id']);
            $state = [
                'cache_id' => $saved['cache_id'],
                'task_id' => null,
                'product_count' => count($offer_products),
                'report_size_bytes' => $saved['size_bytes'],
                'report_downloaded_at' => $saved['saved_at'],
                'download_url' => $url,
            ];

            if (!$this->saveReportMetadata($state, 'offer')) {
                $this->file_store->deleteJsonPayload($saved['cache_id']);
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'El archivo se descargó, pero no se pudieron registrar sus metadatos en la base de datos.',
                ];
            }

            $new_offer_state = [
                'cache_id' => $saved['cache_id'],
                'product_count' => count($offer_products),
                'report_size_bytes' => $saved['size_bytes'],
                'report_downloaded_at' => $saved['saved_at'],
                'download_url' => $url,
            ];
            if (!Configuration::updateValue(self::OFFER_STATE_CONFIG_KEY, json_encode($new_offer_state))) {
                $this->file_store->deleteJsonPayload($saved['cache_id']);
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'El archivo de ofertas se descargó, pero no se pudo guardar su estado en la base de datos.',
                ];
            }
            if (!$this->persistOfferProductsToDatabase($offer_products, $saved['cache_id'])) {
                Configuration::updateValue(self::OFFER_STATE_CONFIG_KEY, json_encode(['download_url' => $url]));
                $this->file_store->deleteJsonPayload($saved['cache_id']);
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'El archivo se descargó, pero no se pudieron guardar sus productos de stock/precio en la base de datos.',
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'http_code' => $response_http_code,
                'message' => 'No se pudo descargar o guardar el archivo real del reporte de ofertas: ' . $e->getMessage()
                    . ' Se conservará la URL para reintentar sin volver a solicitar el reporte.',
            ];
        }

        return [
            'success' => true,
            'status' => 'completed',
            'report_type' => 'offer',
            'report_cached' => true,
            'reused' => false,
            'file_path' => $saved['relative_path'],
            'file_size_bytes' => $saved['size_bytes'],
            'products_count' => count($offer_products),
            'http_code' => $response_http_code,
            'message' => 'El archivo real del reporte de ofertas se descargó desde la URL guardada y quedó listo para combinar stock/precio con el reporte general.',
        ];
    }

    /**
     * Check that the cache identifier points to a readable local offer file.
     */
    protected function isUsableOfferCache($cache_id)
    {
        if (!is_string($cache_id) || !preg_match('/^[a-f0-9]{32}$/', $cache_id)) {
            return false;
        }
        try {
            return count($this->file_store->readOfferProducts($cache_id)) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Return the saved offer-report state, including a local cache ID or URL.
     */
    protected function getStoredOfferState()
    {
        $raw_state = trim((string) Configuration::get(self::OFFER_STATE_CONFIG_KEY));
        $state = json_decode($raw_state, true);
        if (is_array($state)) {
            return $state;
        }

        // Some earlier builds may have stored only the temporary report URL.
        if (preg_match('#^https://#i', $raw_state)) {
            return ['download_url' => $raw_state];
        }

        return [];
    }

    /**
     * Recover an existing temporary URL without requesting a new report.
     * Older module versions may have persisted it in the report metadata table.
     */
    protected function getSavedOfferReportUrl($offer_state)
    {
        if (is_array($offer_state) && !empty($offer_state['download_url']) && is_string($offer_state['download_url'])) {
            return trim($offer_state['download_url']);
        }

        try {
            $db = Db::getInstance();
            $table = _DB_PREFIX_ . 'yuju_product_reports';
            $table_sql = chr(96) . bqSQL($table) . chr(96);
            $exists = $db->getValue("SHOW TABLES LIKE '" . pSQL(_DB_PREFIX_ . "yuju_product_reports") . "'");
            if ($exists) {
                // Older installations keep the table but lack this column. Migrate it
                // before selecting the URL instead of silently falling back to Yuju.
                $has_url_column = $db->getValue("SHOW COLUMNS FROM " . $table_sql . " LIKE 'download_url'");
                if (!$has_url_column) {
                    $db->execute("ALTER TABLE " . $table_sql . " ADD COLUMN download_url TEXT NULL");
                }
                $url = $db->getValue("SELECT download_url FROM " . $table_sql
                    . " WHERE report_type = 'offer' AND download_url IS NOT NULL AND download_url <> ''"
                    . " ORDER BY id DESC LIMIT 1");
                if (is_string($url) && trim($url) !== '') {
                    return trim($url);
                }
            }
        } catch (Exception $e) {
            // Continue to the API fallback only if no recoverable URL is present.
        }

        return '';
    }

    protected function countOfferRows($payload)
    {
        if (!is_array($payload)) {
            return 0;
        }
        if ($payload === []) {
            return 0;
        }
        if (array_keys($payload) === range(0, count($payload) - 1)) {
            return count($payload);
        }
        foreach (['products', 'offers', 'items', 'results', 'data'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return count($payload[$key]);
            }
        }
        return 0;
    }

    /**
     * Poll a saved task. The download URL is returned only to server-side callers.
     *
     * @return array
     */
    public function pollReport($retry_download = false)
    {
        $state = $this->getStoredState();

        if ($state['status'] === 'DOWNLOAD_ERROR' && !$retry_download) {
            return [
                'success' => false,
                'status' => 'DOWNLOAD_ERROR',
                'task_id' => $state['task_id'],
                'message' => 'El reporte está listo, pero la descarga necesita revisión o reintento manual.',
            ];
        }

        if ($state['status'] === 'COMPLETED' && !empty($state['cache_id'])
            && preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])
            && $this->file_store->reportExists($state['cache_id'])
        ) {
            return $this->getCachedReportStatus($state);
        }

        if (empty($state['task_id'])) {
            return ['success' => false, 'status' => $state['status'], 'message' => 'No hay un reporte activo para consultar.'];
        }

        try {
            $response = $this->api_client->getProductGeneralReportStatus($state['task_id']);
        } catch (Exception $e) {
            return ['success' => false, 'status' => $state['status'], 'message' => 'No se pudo consultar el estado del reporte. Intenta nuevamente.'];
        }

        if (empty($response['success']) || empty($response['data']) || !is_array($response['data'])) {
            return [
                'success' => false,
                'status' => $state['status'],
                'message' => 'Yuju no devolvió un estado válido para el reporte.',
            ];
        }

        $data = $response['data'];
        $status = isset($data['status']) ? strtoupper((string) $data['status']) : '';

        if (!in_array($status, ['CREATED', 'PROCESSING', 'COMPLETED', 'REJECTED', 'ERROR'], true)) {
            return ['success' => false, 'status' => $state['status'], 'message' => 'Yuju devolvió un estado de reporte desconocido.'];
        }

        if ($status === 'COMPLETED') {
            $report_url = isset($data['url']) && is_string($data['url']) ? $data['url'] : '';

            if ($report_url === '') {
                return ['success' => false, 'status' => 'ERROR', 'message' => 'El reporte terminó, pero Yuju no devolvió su URL de descarga.'];
            }

            try {
                $download = $this->file_store->downloadAndValidate($report_url);
            } catch (Exception $e) {
                $state['status'] = 'DOWNLOAD_ERROR';
                $state['cache_id'] = null;
                $state['download_error'] = 'download_failed';
                // Keep a short diagnostic, never the signed download URL.
                $state['download_error_message'] = substr($e->getMessage(), 0, 500);
                $this->storeJson(self::STATE_CONFIG_KEY, $state);

                return [
                    'success' => false,
                    'status' => 'DOWNLOAD_ERROR',
                    'message' => 'Yuju terminó el informe, pero no se pudo validar su archivo: '
                        . $state['download_error_message']
                        . ' Puedes reintentar la descarga sin generar otro informe ni consumir otra solicitud.',
                ];
            }

            $state['status'] = 'COMPLETED';
            unset($state['download_error'], $state['download_error_message']);
            $state['cache_id'] = $download['cache_id'];
            $state['product_count'] = (int) $download['product_count'];
            $state['report_size_bytes'] = (int) $download['size_bytes'];
            $state['report_downloaded_at'] = (int) $download['downloaded_at'];

            if (!$this->storeJson(self::STATE_CONFIG_KEY, $state)) {
                $this->file_store->deleteReport($download['cache_id']);

                return ['success' => false, 'status' => 'ERROR', 'message' => 'No se pudo guardar el reporte descargado en el estado local.'];
            }

            return $this->getCachedReportStatus($state);
        }

        $state['status'] = $status;
        if (!$this->storeJson(self::STATE_CONFIG_KEY, $state)) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'No se pudo guardar el estado actualizado del reporte.'];
        }

        return [
            'success' => $status !== 'ERROR' && $status !== 'REJECTED',
            'status' => $status,
            'task_id' => $state['task_id'],
            'message' => isset($data['message']) && is_scalar($data['message']) ? (string) $data['message'] : null,
        ];
    }

    /**
     * Force a fresh download for the existing task when the saved cache is unusable.
     * This never creates another report task or consumes another report quota slot.
     *
     * @return array
     */
    public function recoverReportDownload()
    {
        $state = $this->getStoredState();
        if (empty($state['task_id'])) {
            return [
                'success' => false,
                'status' => isset($state['status']) ? $state['status'] : 'UNKNOWN',
                'message' => 'No hay una tarea de informe guardada que se pueda recuperar.',
            ];
        }

        if (!empty($state['cache_id']) && preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])) {
            $this->file_store->deleteReport($state['cache_id']);
        }

        $state['status'] = 'DOWNLOAD_EXPIRED';
        $state['cache_id'] = null;
        if (!$this->storeJson(self::STATE_CONFIG_KEY, $state)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'No se pudo actualizar el estado local para recuperar la descarga.',
            ];
        }

        return $this->pollReport(true);
    }

    /**
     * Read validated products from the current private report cache.
     *
     * @return Generator
     */
    public function getCachedReportProducts()
    {
        $state = $this->getStoredState();

        if ($state['status'] !== 'COMPLETED' || empty($state['cache_id'])
            || !preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])
            || !$this->file_store->reportExists($state['cache_id'])
        ) {
            throw new RuntimeException('No validated general-products report is cached.');
        }

        return $this->file_store->readProducts($state['cache_id']);
    }



    /**
     * Persist the latest stock/price snapshot in a dedicated database table.
     * The private JSON file remains the source used by the reconciliation reader.
     */
    protected function persistOfferProductsToDatabase($products, $cache_id)
    {
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'yuju_product_offer_rows';
        $table_sql = chr(96) . bqSQL($table) . chr(96);
        $create_sql = "CREATE TABLE IF NOT EXISTS {$table_sql} (
            id_product varchar(64) NOT NULL,
            sku varchar(255) DEFAULT NULL,
            stock decimal(20,6) DEFAULT NULL,
            price decimal(20,6) DEFAULT NULL,
            report_cache_id char(32) NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id_product),
            KEY idx_report_cache_id (report_cache_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!$db->execute($create_sql) || !$db->execute('START TRANSACTION')) {
            return false;
        }

        try {
            if (!$db->execute('DELETE FROM ' . $table_sql)) {
                $db->execute('ROLLBACK');
                return false;
            }

            $updated_at = date('Y-m-d H:i:s');
            foreach ($products as $product) {
                $row = [
                    'id_product' => pSQL((string) $product['id_product']),
                    'sku' => isset($product['sku']) && is_scalar($product['sku']) ? pSQL((string) $product['sku']) : null,
                    'stock' => isset($product['stock']) && is_numeric($product['stock']) ? (float) $product['stock'] : null,
                    'price' => isset($product['price']) && is_numeric($product['price']) ? (float) $product['price'] : null,
                    'report_cache_id' => pSQL($cache_id),
                    'updated_at' => pSQL($updated_at),
                ];
                if (!$db->insert('yuju_product_offer_rows', $row, true)) {
                    $db->execute('ROLLBACK');
                    return false;
                }
            }

            return $db->execute('COMMIT');
        } catch (Exception $e) {
            $db->execute('ROLLBACK');
            return false;
        }
    }

    /** Enrich general product rows with stock and price from the latest offer report. */
    public function getMergedCachedReportProducts()
    {
        $products = [];
        foreach ($this->getCachedReportProducts() as $product) $products[] = $product;

        $offer_state = json_decode((string) Configuration::get(self::OFFER_STATE_CONFIG_KEY), true);
        if (!is_array($offer_state) || empty($offer_state['cache_id'])
            || !preg_match('/^[a-f0-9]{32}$/', (string) $offer_state['cache_id'])) {
            throw new RuntimeException('Primero debes descargar el reporte de stock y precio de Yuju.');
        }
        $offer_rows = $this->file_store->readOfferProducts($offer_state['cache_id']);
        $offer_by_id = [];
        foreach ($offer_rows as $offer) $offer_by_id[(string) $offer['id_product']] = $offer;

        foreach ($products as &$product) {
            $id = isset($product['id_product']) ? (string) $product['id_product'] : '';
            if ($id === '' || !isset($offer_by_id[$id])) continue;
            foreach (['stock', 'price'] as $field) {
                if (array_key_exists($field, $offer_by_id[$id])) $product[$field] = $offer_by_id[$id][$field];
            }
            $product['_offer_report_matched'] = true;
        }
        unset($product);
        return $products;
    }

    protected function getCachedReportStatus($state)
    {
        return [
            'success' => true,
            'status' => 'COMPLETED',
            'task_id' => $state['task_id'],
            'product_count' => isset($state['product_count']) ? (int) $state['product_count'] : 0,
            'report_size_bytes' => isset($state['report_size_bytes']) ? (int) $state['report_size_bytes'] : 0,
            'report_downloaded_at' => isset($state['report_downloaded_at']) ? (int) $state['report_downloaded_at'] : 0,
            'report_cached' => true,
        ];
    }

    public function getStoredState()
    {
        $state = json_decode((string) Configuration::get(self::STATE_CONFIG_KEY), true);
        $state = is_array($state)
            ? array_merge(['status' => '', 'task_id' => null], $state)
            : ['status' => '', 'task_id' => null];

        $saved_cache_is_readable = !empty($state['cache_id'])
            && preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])
            && $this->file_store->reportExists($state['cache_id']);

        if ($saved_cache_is_readable) {
            $this->saveReportMetadata($state);

            return $state;
        }

        // Recover a local JSONL without changing PrestaShop Configuration or
        // issuing another request to Yuju. The report file is the source of truth.
        $local_report = $this->file_store->discoverLatestValidReport();
        if ($local_report !== null) {
            $state['status'] = 'COMPLETED';
            $state['cache_id'] = $local_report['cache_id'];
            $state['product_count'] = (int) $local_report['product_count'];
            $state['report_size_bytes'] = (int) $local_report['size_bytes'];
            $state['report_downloaded_at'] = (int) $local_report['downloaded_at'];
            $state['local_report_recovered'] = true;
        }

        return $state;
    }

    /**
     * Index a validated report in the module-owned table.
     *
     * The JSONL remains the source of truth and stays inside var/reports.
     * No signed URL, product payload, or absolute server path is stored.
     */
    protected function saveReportMetadata($state, $report_type = 'gral')
    {
        if (empty($state['cache_id']) || !preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])) {
            return false;
        }

        try {
            $db = Db::getInstance();
            $table = _DB_PREFIX_ . 'yuju_product_reports';
            $table_sql = chr(96) . bqSQL($table) . chr(96);
            $create_sql = "CREATE TABLE IF NOT EXISTS {$table_sql} (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_task` varchar(64) DEFAULT NULL,
                `report_type` varchar(32) NOT NULL DEFAULT 'gral',
                `trigger` enum('scheduled','manual','webhook') NOT NULL DEFAULT 'manual',
                `status` enum('pending','processing','completed','failed','rejected','limit_reached') NOT NULL DEFAULT 'pending',
                `products_count` int(11) NOT NULL DEFAULT 0,
                `file_path` varchar(512) DEFAULT NULL,
                `file_size` int(11) NOT NULL DEFAULT 0,
                `download_url` text,
                `error_message` text,
                `meta_json` longtext,
                `requested_at` datetime DEFAULT NULL,
                `completed_at` datetime DEFAULT NULL,
                `utc_day` char(10) DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_id_task` (`id_task`),
                KEY `idx_status` (`status`),
                KEY `idx_utc_day` (`utc_day`),
                KEY `idx_report_type` (`report_type`),
                KEY `idx_requested_at` (`requested_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (!$db->execute($create_sql)) {
                return false;
            }

            // CREATE TABLE IF NOT EXISTS does not upgrade an existing table. Ensure
            // installations upgraded from older versions can store and recover URLs.
            $has_url_column = $db->getValue("SHOW COLUMNS FROM " . $table_sql . " LIKE 'download_url'");
            if (!$has_url_column && !$db->execute("ALTER TABLE " . $table_sql . " ADD COLUMN download_url TEXT NULL")) {
                return false;
            }

            $extension = $report_type === 'offer' ? '.json' : '.jsonl';
            $relative_path = 'var/reports/' . $state['cache_id'] . $extension;
            $task_id = !empty($state['task_id']) ? (string) $state['task_id'] : null;
            $where = "file_path = '" . pSQL($relative_path) . "'";
            if ($task_id !== null) {
                $where .= " OR id_task = '" . pSQL($task_id) . "'";
            }
            $existing_id = (int) $db->getValue('SELECT id FROM ' . $table_sql . ' WHERE (' . $where . ') ORDER BY id DESC');

            $downloaded_at = isset($state['report_downloaded_at']) ? (int) $state['report_downloaded_at'] : time();
            $meta = [
                'cache_id' => $state['cache_id'],
                'local_report_recovered' => !empty($state['local_report_recovered']),
                'downloaded_at' => $downloaded_at,
            ];
            $row = [
                'id_task' => $task_id,
                'report_type' => $report_type,
                'trigger' => 'manual',
                'status' => 'completed',
                'products_count' => isset($state['product_count']) ? (int) $state['product_count'] : 0,
                'file_path' => pSQL($relative_path),
                'file_size' => isset($state['report_size_bytes']) ? (int) $state['report_size_bytes'] : 0,
                'download_url' => !empty($state['download_url']) ? pSQL((string) $state['download_url']) : null,
                'error_message' => null,
                'meta_json' => pSQL(json_encode($meta)),
                'completed_at' => date('Y-m-d H:i:s', $downloaded_at),
                'utc_day' => gmdate('Y-m-d', $downloaded_at),
            ];

            if ($existing_id > 0) {
                return $db->update('yuju_product_reports', $row, 'id = ' . $existing_id);
            }

            return $db->insert('yuju_product_reports', $row);
        } catch (Exception $e) {
            // Report display must remain available even if the metadata table is unavailable.
            return false;
        }
    }

    protected function getDailyQuota($today)
    {
        $quota = json_decode((string) Configuration::get(self::QUOTA_CONFIG_KEY), true);

        if (!is_array($quota) || !isset($quota['date_utc']) || $quota['date_utc'] !== $today) {
            return ['date_utc' => $today, 'count' => 0];
        }

        return ['date_utc' => $today, 'count' => max(0, min(2, (int) ($quota['count'] ?? 0)))];
    }

    protected function storeJson($key, $value)
    {
        return Configuration::updateValue($key, json_encode($value));
    }
}
