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
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['register_webhooks'] = [
            'href' => self::$currentIndex . '&action=registerWebhooks&token=' . $this->token,
            'desc' => $this->trans('Register Webhooks', array(), 'Modules.Prestashopyuju.Admin'),
            'icon' => 'process-icon-new',
            ];

            $this->page_header_toolbar_btn['unregister_webhooks'] = [
            'href' => self::$currentIndex . '&action=unregisterWebhooks&token=' . $this->token,
            'desc' => $this->trans('Unregister Webhooks', array(), 'Modules.Prestashopyuju.Admin'),
            'icon' => 'process-icon-delete',
            ];

            $this->page_header_toolbar_btn['test_webhook'] = [
            'href' => self::$currentIndex . '&action=testWebhook&token=' . $this->token,
            'desc' => $this->trans('Test Webhook', array(), 'Modules.Prestashopyuju.Admin'),
            'icon' => 'process-icon-cogs',
            ];

            $this->page_header_toolbar_btn['clean_logs'] = [
            'href' => self::$currentIndex . '&action=cleanLogs&token=' . $this->token,
            'desc' => $this->trans('Clean Old Logs', array(), 'Modules.Prestashopyuju.Admin'),
            'icon' => 'process-icon-eraser',
            ];
        }

        parent::initPageHeaderToolbar();
    }

    public function renderList()
    {
        // Add webhook statistics
        $stats = $this->webhook_manager->getWebhookStats();

        $this->context->smarty->assign([
        'webhook_stats' => $stats,
        'webhook_url' => $this->getWebhookUrl(),
        ]);

        $stats_html = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/webhook_stats.tpl');

        return $stats_html . parent::renderList();
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
