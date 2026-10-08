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

// Include PrestaShop configuration
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

// Include webhook manager
require_once dirname(__FILE__) . '/classes/YujuWebhookManager.php';
require_once dirname(__FILE__) . '/classes/YujuLogger.php';

// Set content type
header('Content-Type: application/json');

// Initialize logger
$logger = new YujuLogger();

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
    $headers = [];

    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $header_name = str_replace('HTTP_', '', $key);
            $header_name = str_replace('_', '-', $header_name);
            $headers[$header_name] = $value;
        }
    }

    // Log incoming webhook
    $logger->log('Webhook received: ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'], 'info');
    $logger->log('Headers: ' . json_encode($headers), 'debug');
    $logger->log('Payload length: ' . strlen($raw_payload), 'debug');

    // Initialize webhook manager
    $webhook_manager = new YujuWebhookManager();

    // Process webhook
    $result = $webhook_manager->processWebhook($raw_payload, $headers);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Webhook processed successfully',
        'data' => $result,
    ]);

    $logger->log('Webhook processed successfully', 'info');
} catch (Exception $e) {
    // Log error
    $logger->log('Webhook processing failed: ' . $e->getMessage(), 'error');

    // Return error response
    $error_code = 500;

    // Set appropriate HTTP status code based on error
    if (strpos($e->getMessage(), 'Invalid webhook signature') !== false) {
        $error_code = 401;
    } elseif (strpos($e->getMessage(), 'Empty payload') !== false
    || strpos($e->getMessage(), 'Invalid webhook payload') !== false) {
        $error_code = 400;
    } elseif (strpos($e->getMessage(), 'Method not allowed') !== false) {
        $error_code = 405;
    }

    http_response_code($error_code);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
    ]);
}

// Ensure output is sent
if (ob_get_level()) {
    ob_end_flush();
}

exit;
