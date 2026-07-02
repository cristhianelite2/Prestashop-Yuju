<?php
/**
 * 2024 Yuju Integration.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuWebhookManager.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuLogger.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuWebhookLog.php';
require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuApiClient.php';

class AdminYujuWebhookController extends ModuleAdminController
{
    protected $webhook_manager;

    protected $logger;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'yuju_webhook_logs';
        $this->className = 'YujuWebhookLog';
        $this->identifier = 'id';
        $this->lang = false;
        $this->addRowAction('view');
        $this->addRowAction('delete');

        parent::__construct();

        $this->fields_list = [
        'id' => [
        'title' => $this->trans('ID', array(), 'Modules.Prestashopyuju.Admin'),
        'align' => 'center',
        'class' => 'fixed-width-xs',
        ],
        'event_type' => [
        'title' => $this->trans('Event Type', array(), 'Modules.Prestashopyuju.Admin'),
        'width' => 140,
        ],
        'entity_id' => [
        'title' => $this->trans('Entity ID', array(), 'Modules.Prestashopyuju.Admin'),
        'width' => 100,
        ],
        'status' => [
        'title' => $this->trans('Status', array(), 'Modules.Prestashopyuju.Admin'),
        'width' => 80,
        'type' => 'select',
        'list' => [
        'processing' => $this->trans('Processing', array(), 'Modules.Prestashopyuju.Admin'),
        'processed' => $this->trans('Processed', array(), 'Modules.Prestashopyuju.Admin'),
        'failed' => $this->trans('Failed', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        'filter_key' => 'status',
        'callback' => 'displayStatus',
        ],
        'received_at' => [
        'title' => $this->trans('Received At', array(), 'Modules.Prestashopyuju.Admin'),
        'width' => 160,
        'type' => 'datetime',
        ],
        'processed_at' => [
        'title' => $this->trans('Processed At', array(), 'Modules.Prestashopyuju.Admin'),
        'width' => 160,
        'type' => 'datetime',
        ],
        ];

        $this->bulk_actions = [
        'delete' => [
        'text' => $this->trans('Delete selected', array(), 'Modules.Prestashopyuju.Admin'),
        'icon' => 'icon-trash',
        'confirm' => $this->trans('Delete selected items?', array(), 'Modules.Prestashopyuju.Admin'),
        ],
        ];

        $this->webhook_manager = new YujuWebhookManager();
        $this->logger = new YujuLogger();
    }

    public function initContent()
    {
        $this->context->smarty->assign('current_controller', 'AdminYujuWebhook');
        parent::initContent();
        
        $this->setTemplate('webhook.tpl');
    }

    public function initPageHeaderToolbar()
    {
        // Toolbar oculto a pedido: se usará la alerta con botón "Habilitar webhooks".
        $this->page_header_toolbar_btn = [];
    }

    public function renderList()
    {
        // Add webhook statistics
        $stats = $this->webhook_manager->getWebhookStats();
        $subscription_status = $this->webhook_manager->getRequiredWebhookSubscriptionStatus();
        $subscription_configs = [];
        $subscription_configs_error = null;
        try {
            if (method_exists($this->webhook_manager, 'getWebhookSubscriptionsDetailed')) {
                $subscription_configs = $this->webhook_manager->getWebhookSubscriptionsDetailed();
            } else {
                // Compatibilidad con versiones anteriores de YujuWebhookManager
                $api_client = new YujuApiClient();
                $raw = $api_client->getWebhookSubscriptions();
                $subscription_configs = $this->normalizeWebhookSubscriptionConfigsCompat($raw);
            }
        } catch (Exception $e) {
            $subscription_configs_error = $e->getMessage();
        }
        $enable_webhooks_url = self::$currentIndex . '&action=registerWebhooks&token=' . $this->token;

        $this->context->smarty->assign([
        'webhook_stats' => $stats,
        'webhook_url' => $this->getWebhookUrl(),
        'yuju_required_webhook_subscription' => $subscription_status,
        'yuju_enable_webhooks_url' => $enable_webhooks_url,
        'yuju_webhook_subscription_configs' => $subscription_configs,
        'yuju_webhook_subscription_configs_error' => $subscription_configs_error,
        'yuju_webhook_toggle_config_base' => self::$currentIndex . '&action=toggleWebhookConfiguration&token=' . $this->token,
        'yuju_webhook_toggle_topic_base' => self::$currentIndex . '&action=toggleWebhookTopic&token=' . $this->token,
        'yuju_webhook_delete_config_base' => self::$currentIndex . '&action=deleteWebhookConfiguration&token=' . $this->token,
        ]);

        $stats_html = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/webhook_stats.tpl');
        $status_alert_html = '';
        if (empty($subscription_status['has_required_subscription'])) {
            $missing_topics = [];
            if (!empty($subscription_status['missing_topics']) && is_array($subscription_status['missing_topics'])) {
                $missing_topics = $subscription_status['missing_topics'];
            }
            $topics_text = !empty($missing_topics) ? implode(', ', $missing_topics) : 'No se detectaron topics requeridos';
            $status_alert_html = '<div class="alert alert-warning">'
                . '<p style="margin:0 0 8px 0;"><strong>Atención:</strong> No se detectó una suscripción activa con todos los webhooks necesarios de Yuju.</p>'
                . '<p style="margin:0 0 10px 0;">Faltantes: <code>' . htmlspecialchars($topics_text, ENT_QUOTES, 'UTF-8') . '</code></p>'
                . '<a class="btn btn-warning" href="' . htmlspecialchars($enable_webhooks_url, ENT_QUOTES, 'UTF-8') . '">'
                . '<i class="icon-check"></i> Habilitar webhooks'
                . '</a>'
                . '</div>';
        } else {
            $status_alert_html = '<div class="alert alert-info">'
                . '<p style="margin:0 0 8px 0;"><strong>Webhooks detectados:</strong> Existe una suscripción activa con los topics requeridos.</p>'
                . '<a class="btn btn-default" href="' . htmlspecialchars($enable_webhooks_url, ENT_QUOTES, 'UTF-8') . '">'
                . '<i class="icon-refresh"></i> Reintentar suscripción'
                . '</a>'
                . '</div>';
        }

        return $status_alert_html . $stats_html . parent::renderList();
    }

    /**
     * Normaliza respuesta de suscripciones para UI en modo compatibilidad.
     *
     * @param mixed $api_response
     * @return array<int, array<string,mixed>>
     */
    protected function normalizeWebhookSubscriptionConfigsCompat($api_response)
    {
        if (!is_array($api_response)) {
            return [];
        }

        $subs = [];
        if (isset($api_response['data']) && is_array($api_response['data'])) {
            if (isset($api_response['data'][0]) && is_array($api_response['data'][0])) {
                $subs = $api_response['data'];
            } else {
                $subs = [$api_response['data']];
            }
        } elseif (isset($api_response[0]) && is_array($api_response[0])) {
            $subs = $api_response;
        } else {
            $subs = [$api_response];
        }

        $out = [];
        foreach ($subs as $sub) {
            if (!is_array($sub)) {
                continue;
            }
            $id = '';
            foreach (['id', 'subscription_id', 'id_third_party_app_webhook'] as $k) {
                if (isset($sub[$k]) && trim((string) $sub[$k]) !== '') {
                    $id = trim((string) $sub[$k]);
                    break;
                }
            }
            $topics = [];
            if (isset($sub['topics']) && is_array($sub['topics'])) {
                $topics = $sub['topics'];
            } elseif (isset($sub['topic']) && is_string($sub['topic'])) {
                $topics = [$sub['topic']];
            }
            $topics = array_values(array_unique(array_filter(array_map(static function ($t) {
                return is_string($t) ? str_replace('_', '-', strtolower(trim($t))) : '';
            }, $topics))));

            $is_active = true;
            if (array_key_exists('is_active', $sub)) {
                $is_active = (bool) $sub['is_active'];
            } elseif (array_key_exists('active', $sub)) {
                $is_active = (bool) $sub['active'];
            }

            $out[] = [
                'id' => $id,
                'url' => isset($sub['url']) ? (string) $sub['url'] : '',
                'topics' => $topics,
                'is_active' => $is_active,
                'raw' => $sub,
            ];
        }

        return $out;
    }

    /**
     * Devuelve la cadena con JSON pretty-printed si el contenido es JSON válido.
     * Si no lo es (HTML/texto/binario), retorna el valor original sin modificar.
     *
     * @param string|null $raw
     *
     * @return string
     */
    protected function formatJsonForWebhookView($raw)
    {
        if ($raw === null) {
            return '';
        }
        $raw_str = (string) $raw;
        $trim = trim($raw_str);
        if ($trim === '') {
            return '';
        }
        // Sólo intentamos decodificar si parece JSON (objeto, array o cadena/null/bool/numérico JSON).
        $first = $trim[0];
        if (!in_array($first, ['{', '[', '"'], true)
            && !preg_match('/^(true|false|null|-?\d)/', $trim)
        ) {
            return $raw_str;
        }
        $decoded = json_decode($raw_str, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $raw_str;
        }
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($pretty === false) {
            return $raw_str;
        }

        return $pretty;
    }

    public function renderView()
    {
        $webhook_log = Db::getInstance()->getRow(
            '
        SELECT * FROM ' . _DB_PREFIX_ . 'yuju_webhook_logs
        WHERE id = ' . (int) Tools::getValue('id')
        );

        if (!$webhook_log) {
            $this->errors[] = $this->trans('Webhook log not found', array(), 'Modules.Prestashopyuju.Admin');

            return $this->renderList();
        }

        // Parse JSON data
        $webhook_log['payload_decoded'] = json_decode($webhook_log['payload'], true);
        $webhook_log['headers_decoded'] = json_decode($webhook_log['headers'], true);
        $webhook_log['response_decoded'] = json_decode($webhook_log['response'], true);

        // Pretty-print de payload y response si son JSON válidos (incluye listas, objetos y "null").
        // Si no son JSON, se conserva el valor original para no romper respuestas HTML/texto.
        $webhook_log['payload_pretty'] = $this->formatJsonForWebhookView($webhook_log['payload']);
        $webhook_log['response_pretty'] = $this->formatJsonForWebhookView($webhook_log['response']);

        $this->context->smarty->assign([
        'webhook_log' => $webhook_log,
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/webhook_view.tpl');
    }

    public function processRegisterWebhooks()
    {
        try {
            $results = $this->webhook_manager->registerWebhooks();

            $success_count = 0;
            $error_count = 0;

            foreach ($results as $result) {
                if ($result['status'] === 'registered') {
                    ++$success_count;
                } else {
                    ++$error_count;
                }
            }

            if ($success_count > 0) {
                $this->confirmations[] = sprintf(
                    $this->trans('Successfully registered %d webhooks', array(), 'Modules.Prestashopyuju.Admin'),
                    $success_count
                );
            }

            if ($error_count > 0) {
                $this->warnings[] = sprintf(
                    $this->trans('Failed to register %d webhooks', array(), 'Modules.Prestashopyuju.Admin'),
                    $error_count
                );
                foreach ($results as $result) {
                    if (($result['status'] ?? '') !== 'registered' && !empty($result['error'])) {
                        $this->warnings[] = (string) $result['error'];
                    }
                }
            }
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error registering webhooks: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    public function processUnregisterWebhooks()
    {
        try {
            $count = $this->webhook_manager->unregisterWebhooks();

            $this->confirmations[] = sprintf(
                $this->trans('Successfully unregistered %d webhooks', array(), 'Modules.Prestashopyuju.Admin'),
                $count
            );
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error unregistering webhooks: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    public function processToggleWebhookConfiguration()
    {
        try {
            $subscription_id = (string) Tools::getValue('subscription_id', '');
            $enable_raw = (string) Tools::getValue('enable', '1');
            $enabled = ($enable_raw === '1' || strtolower($enable_raw) === 'true');
            $result = $this->webhook_manager->toggleWebhookConfiguration($subscription_id, $enabled);
            if (!empty($result['success'])) {
                $this->confirmations[] = (string) ($result['message'] ?? 'Configuración actualizada');
            } else {
                $this->warnings[] = (string) ($result['message'] ?? 'No se pudo actualizar la configuración');
            }
        } catch (Exception $e) {
            $this->errors[] = 'Error al actualizar configuración webhook: ' . $e->getMessage();
        }
    }

    public function processDeleteWebhookConfiguration()
    {
        try {
            $subscription_id = (string) Tools::getValue('subscription_id', '');
            if (trim($subscription_id) === '' || (int) $subscription_id <= 0) {
                $this->errors[] = 'ID de configuración inválido';

                return;
            }
            $result = $this->webhook_manager->deleteWebhookConfiguration($subscription_id);
            if (!empty($result['success'])) {
                $this->confirmations[] = (string) ($result['message'] ?? 'Configuración eliminada');
            } else {
                $this->warnings[] = (string) ($result['message'] ?? 'No se pudo eliminar la configuración');
            }
        } catch (Exception $e) {
            $this->errors[] = 'Error al eliminar configuración webhook: ' . $e->getMessage();
        }
    }

    public function processToggleWebhookTopic()
    {
        try {
            $subscription_id = (string) Tools::getValue('subscription_id', '');
            $topic = (string) Tools::getValue('topic', '');
            $enable_raw = (string) Tools::getValue('enable', '1');
            $enabled = ($enable_raw === '1' || strtolower($enable_raw) === 'true');
            $result = $this->webhook_manager->toggleWebhookTopic($subscription_id, $topic, $enabled);
            if (!empty($result['success'])) {
                $this->confirmations[] = (string) ($result['message'] ?? 'Webhook actualizado');
            } else {
                $this->warnings[] = (string) ($result['message'] ?? 'No se pudo actualizar el webhook');
            }
        } catch (Exception $e) {
            $this->errors[] = 'Error al actualizar webhook por configuración: ' . $e->getMessage();
        }
    }

    public function processTestWebhook()
    {
        try {
            // Create a test webhook payload
            $test_payload = json_encode([
            'event' => 'test.webhook',
            'data' => [
            'id' => 'test_' . time(),
            'message' => 'This is a test webhook from PrestaShop',
            'timestamp' => date('Y-m-d H:i:s'),
            ],
            ]);

            // Simulate webhook headers
            $test_headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Yuju-Webhook/1.0',
            'X-Yuju-Event' => 'test.webhook',
            ];

            // Process test webhook
            $result = $this->webhook_manager->processWebhook($test_payload, $test_headers);

            $this->confirmations[] = $this->trans('Test webhook processed successfully', array(), 'Modules.Prestashopyuju.Admin');
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Test webhook failed: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    public function processCleanLogs()
    {
        try {
            $days = (int) Tools::getValue('days', 30);
            $deleted_count = $this->webhook_manager->cleanOldWebhookLogs($days);

            $this->confirmations[] = sprintf(
                $this->trans('Cleaned %d old webhook logs (older than %d days)', array(), 'Modules.Prestashopyuju.Admin'),
                $deleted_count,
                $days
            );
        } catch (Exception $e) {
            $this->errors[] = $this->trans('Error cleaning logs: ', array(), 'Modules.Prestashopyuju.Admin') . $e->getMessage();
        }
    }

    public function displayStatus($value, $row)
    {
        $status_colors = [
        'processing' => 'warning',
        'processed' => 'success',
        'failed' => 'danger',
        ];

        $color = isset($status_colors[$value]) ? $status_colors[$value] : 'default';

        return '<span class="label label-' . $color . '">' . ucfirst($value) . '</span>';
    }

    protected function getWebhookUrl()
    {
        $shop_url = Configuration::get('PS_SHOP_DOMAIN');
        $ssl = Configuration::get('PS_SSL_ENABLED');

        $protocol = $ssl ? 'https://' : 'http://';

        return $protocol . $shop_url . '/modules/prestashopyuju/webhook.php';
    }

    public function ajaxProcessGetWebhookStats()
    {
        try {
            $stats = $this->webhook_manager->getWebhookStats();

            exit(json_encode([
            'success' => true,
            'stats' => $stats,
            ]));
        } catch (Exception $e) {
            exit(json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            ]));
        }
    }

    public function ajaxProcessRetryWebhook()
    {
        try {
            $webhook_id = (int) Tools::getValue('webhook_id');

            $webhook_log = Db::getInstance()->getRow(
                '
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_webhook_logs
            WHERE id = ' . (int) $webhook_id
            );

            if (!$webhook_log) {
                throw new Exception('Webhook log not found');
            }

            // Retry webhook processing
            $headers = json_decode($webhook_log['headers'], true);
            $result = $this->webhook_manager->processWebhook($webhook_log['payload'], $headers);

            exit(json_encode([
            'success' => true,
            'message' => 'Webhook retried successfully',
            'result' => $result,
            ]));
        } catch (Exception $e) {
            exit(json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            ]));
        }
    }

    public function ajaxProcessExportWebhookLogs()
    {
        try {
            $filters = [];

            if (Tools::getValue('event_type')) {
                $filters['event_type'] = Tools::getValue('event_type');
            }

            if (Tools::getValue('status')) {
                $filters['status'] = Tools::getValue('status');
            }

            if (Tools::getValue('date_from')) {
                $filters['date_from'] = Tools::getValue('date_from');
            }

            if (Tools::getValue('date_to')) {
                $filters['date_to'] = Tools::getValue('date_to');
            }

            $logs = $this->getWebhookLogsForExport($filters);

            // Generate CSV
            $csv_content = $this->generateWebhookLogsCsv($logs);

            $filename = 'yuju_webhook_logs_' . date('Y-m-d_H-i-s') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($csv_content));

            echo $csv_content;
            exit;
        } catch (Exception $e) {
            exit(json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            ]));
        }
    }

    protected function getWebhookLogsForExport($filters = [])
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_webhook_logs WHERE 1=1';

        if (isset($filters['event_type'])) {
            $sql .= ' AND event_type = "' . pSQL($filters['event_type']) . '"';
        }

        if (isset($filters['status'])) {
            $sql .= ' AND status = "' . pSQL($filters['status']) . '"';
        }

        if (isset($filters['date_from'])) {
            $sql .= ' AND received_at >= "' . pSQL($filters['date_from']) . '"';
        }

        if (isset($filters['date_to'])) {
            $sql .= ' AND received_at <= "' . pSQL($filters['date_to']) . '"';
        }

        $sql .= ' ORDER BY received_at DESC LIMIT 1000';

        return Db::getInstance()->executeS($sql);
    }

    protected function generateWebhookLogsCsv($logs)
    {
        $csv_lines = [];

        // Header
        $csv_lines[] = [
        'ID',
        'Event Type',
        'Entity ID',
        'Status',
        'Received At',
        'Processed At',
        'Error Message',
        ];

        // Data

        foreach ($logs as $log) {
            $csv_lines[] = [
            $log['id'],
            $log['event_type'],
            $log['entity_id'],
            $log['status'],
            $log['received_at'],
            $log['processed_at'],
            $log['error_message'],
            ];
        }

        // Convert to CSV string
        $csv_content = '';

        foreach ($csv_lines as $line) {
            $csv_content .= '"' . implode('";"', $line) . '"' . "\n";
        }

        return $csv_content;
    }
}
