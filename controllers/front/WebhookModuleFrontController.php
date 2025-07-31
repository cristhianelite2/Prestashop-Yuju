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

class PrestashopyujuWebhookModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public $display_column_left = false;

    public $display_column_right = false;

    public $display_header = false;

    public $display_footer = false;

    protected $webhook_manager;

    protected $logger;

    public function __construct()
    {
        parent::__construct();

        $this->webhook_manager = new YujuWebhookManager();
        $this->logger = new YujuLogger();
    }

    public function init()
    {
        parent::init();

        // Set content type
        header('Content-Type: application/json');

        // Disable PrestaShop output buffering

        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    public function initContent()
    {
        // Don't call parent::initContent() to avoid template rendering

        try {
            // Check if module is active

            if (!Module::isEnabled('prestashopyuju')) {
                throw new Exception('Yuju module is not active');
            }

            // Only accept POST requests

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);

                throw new Exception('Method not allowed');
            }

            // Get raw POST data
            $raw_payload = file_get_contents('php://input');

            if (empty($raw_payload)) {
                http_response_code(400);

                throw new Exception('Empty payload');
            }

            // Get headers
            $headers = $this->getAllHeaders();

            // Log incoming webhook
            $this->logger->log('Webhook received via front controller: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'], 'info');
            $this->logger->log('Headers: ' . json_encode($headers), 'debug');
            $this->logger->log('Payload length: ' . strlen($raw_payload), 'debug');

            // Process webhook
            $result = $this->webhook_manager->processWebhook($raw_payload, $headers);

            // Return success response
            http_response_code(200);
            echo json_encode([
            'success' => true,
            'message' => 'Webhook processed successfully',
            'data' => $result,
            ]);

            $this->logger->log('Webhook processed successfully via front controller', 'info');
        } catch (Exception $e) {
            // Log error
            $this->logger->log('Webhook processing failed via front controller: ' . $e->getMessage(), 'error');

            // Return error response
            $error_code = $this->getErrorCode($e->getMessage());

            http_response_code($error_code);

            echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }

        // Ensure output is sent and exit

        if (ob_get_level()) {
            ob_end_flush();
        }

        exit;
    }

    /**
     * Get all HTTP headers.
     */
    protected function getAllHeaders()
    {
        $headers = [];

        // Try getallheaders() first (Apache)

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback for other servers

            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $header_name = str_replace('HTTP_', '', $key);
                    $header_name = str_replace('_', '-', $header_name);
                    $header_name = ucwords(strtolower($header_name), '-');
                    $headers[$header_name] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Get appropriate HTTP error code based on error message.
     */
    protected function getErrorCode($error_message)
    {
        if (strpos($error_message, 'Invalid webhook signature') !== false) {
            return 401;
        } elseif (strpos($error_message, 'Empty payload') !== false
        || strpos($error_message, 'Invalid webhook payload') !== false) {
            return 400;
        } elseif (strpos($error_message, 'Method not allowed') !== false) {
            return 405;
        } elseif (strpos($error_message, 'module is not active') !== false) {
            return 503;
        }

        return 500;
    }

    /**
     * Override postProcess to prevent default processing.
     */
    public function postProcess()
    {
        // Don't call parent::postProcess() to avoid conflicts

        return true;
    }

    /**
     * Override display to prevent template rendering.
     */
    public function display()
    {
        // Don't call parent::display() to avoid template rendering

        return true;
    }

    /**
     * Override setTemplate to prevent template assignment.
     */
    public function setTemplate($template, $params = [], $locale = null)
    {
        // Don't set any template

        return true;
    }
}
