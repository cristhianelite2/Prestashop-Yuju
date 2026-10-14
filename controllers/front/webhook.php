<?php
/**
 * Receptor de webhooks de Yuju para PrestaShop 8/9.
 *
 * La URL pública /modules/yuju/webhook.php enruta aquí mediante index.php.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuWebhookManager.php';
require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuLogger.php';
require_once _PS_MODULE_DIR_ . 'yuju/classes/YujuWebhookStorage.php';

class YujuWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;

    public function postProcess()
    {
        // Evita procesamiento estándar de formularios.
    }

    public function initContent()
    {
        // No llamar a parent::initContent(): este endpoint es JSON, no una página.
        header('Content-Type: application/json; charset=utf-8');
        ignore_user_abort(true);
        @set_time_limit(0);

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            http_response_code(204);
            exit;
        }

        if ($method !== 'POST') {
            $this->respond(405, [
                'success' => false,
                'error' => 'Method not allowed; use POST',
            ]);
        }

        $headers = $this->getRequestHeaders();
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = $value;
        }

        $topic = trim((string) ($normalized['x-yuju-topic'] ?? ''));
        $yujuId = trim((string) ($normalized['x-yuju-id'] ?? ''));

        // Yuju puede hacer una comprobación de conectividad sin enviar los
        // encabezados de un evento real. No debe confundirse con una orden.
        if ($topic === '' || $yujuId === '') {
            http_response_code(204);
            exit;
        }

        if (!Module::isEnabled('yuju')) {
            $this->respond(503, [
                'success' => false,
                'error' => 'Yuju module is not active',
            ]);
        }

        $rawPayload = file_get_contents('php://input');
        if (!is_string($rawPayload)) {
            $rawPayload = '';
        }

        $logger = new YujuLogger();
        $manager = new YujuWebhookManager();
        $ackSent = false;
        $start = microtime(true);

        try {
            $logger->log('Webhook received via public entrypoint', 'info');
            $logger->log('Yuju headers: ' . json_encode($normalized, JSON_UNESCAPED_UNICODE), 'info');
            $logger->log('Webhook payload bytes: ' . strlen($rawPayload), 'debug');

            $result = $manager->processWebhook(
                $rawPayload,
                $headers,
                function ($logId, $webhookData) use (&$ackSent, $yujuId, $topic, $normalized) {
                    if ($ackSent) {
                        return;
                    }

                    $this->respond(200, [
                        'success' => true,
                        'message' => 'Webhook accepted for processing',
                        'webhook' => [
                            'id' => $yujuId,
                            'topic' => $topic,
                            'resource' => $normalized['x-yuju-resource'] ?? null,
                        ],
                        'processing' => [
                            'status' => 'accepted',
                            'log_id' => (int) $logId,
                        ],
                    ], true);

                    $ackSent = true;
                }
            );

            // Si la orden está bloqueada por otro proceso, no confirmar 200:
            // así Yuju puede reintentar en lugar de perder el evento.
            if (!empty($result['skipped_lock']) && !$ackSent) {
                $logger->log('Webhook not acknowledged because order lock is busy; requesting retry', 'warning');
                $this->respond(503, [
                    'success' => false,
                    'message' => 'Order is being processed; retry this webhook',
                    'retry' => true,
                    'webhook' => [
                        'id' => $yujuId,
                        'topic' => $topic,
                    ],
                ]);
            }

            // Persistir copia para la pantalla de webhooks, sin interferir con el ACK.
            try {
                $storage = new YujuWebhookStorage();
                $storedPayload = isset($result['enriched_data'])
                    ? json_encode($result['enriched_data'], JSON_UNESCAPED_UNICODE)
                    : $rawPayload;
                $storage->saveWebhook($headers, is_string($storedPayload) ? $storedPayload : $rawPayload);
            } catch (Throwable $storageError) {
                $logger->log('Webhook storage failed: ' . $storageError->getMessage(), 'error');
            }

            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $logger->log(
                'Webhook processing finished in ' . $elapsed . 'ms; acknowledged=' . ($ackSent ? 'yes' : 'no'),
                'info'
            );

            if (!$ackSent) {
                $ok = !isset($result['success']) || $result['success'] !== false;
                $this->respond($ok ? 200 : 500, [
                    'success' => $ok,
                    'message' => $ok ? 'Webhook processed successfully' : 'Webhook processing failed',
                    'processing' => $result,
                    'execution_time_ms' => $elapsed,
                ]);
            }
        } catch (Throwable $e) {
            $logger->log('Webhook processing failed: ' . $e->getMessage(), 'error');
            $logger->log('Webhook exception trace: ' . $e->getTraceAsString(), 'debug');

            // Si ya se confirmó el evento, no se puede cambiar la respuesta HTTP.
            // El error queda registrado para diagnóstico.
            if (!$ackSent) {
                $this->respond(500, [
                    'success' => false,
                    'error' => 'Webhook processing failed',
                ]);
            }
        }

        exit;
    }

    /**
     * Obtiene encabezados con soporte para Apache/FPM y nombres normalizados.
     *
     * @return array<string,string>
     */
    protected function getRequestHeaders()
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            $all = getallheaders();
            if (is_array($all)) {
                foreach ($all as $name => $value) {
                    $headers[(string) $name] = (string) $value;
                }
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') !== 0) {
                continue;
            }

            $name = str_replace('_', '-', substr($key, 5));
            // Preservar el encabezado de getallheaders() si ya estaba disponible.
            $exists = false;
            foreach ($headers as $existingName => $_existingValue) {
                if (strcasecmp($existingName, $name) === 0) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $headers[$name] = (string) $value;
            }
        }

        // CONTENT_TYPE y CONTENT_LENGTH no siempre tienen prefijo HTTP_.
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Emite una respuesta JSON y, para el ACK temprano, cierra la respuesta HTTP
     * antes de continuar con la lógica lenta del webhook.
     */
    protected function respond($status, array $body, $finishRequest = false)
    {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($finishRequest) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                @ob_flush();
                flush();
            }

            return;
        }

        exit;
    }
}
