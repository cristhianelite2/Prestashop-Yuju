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

// Include PrestaShop configuration
require_once dirname(__FILE__) . '/../../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../../init.php';

// Include required classes
require_once dirname(__FILE__) . '/../classes/YujuSyncManager.php';
require_once dirname(__FILE__) . '/../classes/YujuLogger.php';
require_once dirname(__FILE__) . '/../config/config.php';

// Set execution time limit
set_time_limit(0);
ini_set('memory_limit', '512M');

// Initialize logger
$logger = new YujuLogger();

try {
    // Check if module is active

    if (!Module::isEnabled('prestashopyuju')) {
        throw new Exception('Yuju module is not active');
    }

    // Get sync parameters from command line or default values
    $sync_type = isset($argv[1]) ? $argv[1] : 'incremental';
    $entity_types = isset($argv[2]) ? explode(',', $argv[2]) : ['products', 'stock', 'prices'];
    $force = isset($argv[3]) && $argv[3] === 'force';

    $logger->log('Starting cron sync: type=' . $sync_type . ', entities=' . implode(',', $entity_types), 'info');

    // Initialize sync manager
    $sync_manager = new YujuSyncManager();

    // Check if sync is already running (unless forced)

    if (!$force && $sync_manager->isSyncRunning()) {
        $logger->log('Sync already running, skipping cron execution', 'warning');
        exit(0);
    }

    // Check sync frequency
    $last_sync = YujuConfig::get('YUJU_LAST_CRON_SYNC');
    $sync_frequency = (int) YujuConfig::get('YUJU_SYNC_FREQUENCY', 3600); // Default 1 hour

    if (!$force && $last_sync && (time() - strtotime($last_sync)) < $sync_frequency) {
        $logger->log('Sync frequency not reached, skipping cron execution', 'info');
        exit(0);
    }

    // Update last sync time
    YujuConfig::set('YUJU_LAST_CRON_SYNC', date('Y-m-d H:i:s'));

    // Perform sync based on type
    $results = [];

    if ($sync_type === 'full') {
        $results = $sync_manager->executeFullSync('bidirectional', $force);
    } else {
        $results = $sync_manager->executeIncrementalSync();
    }

    // Log results

    foreach ($results as $entity_type => $result) {
        if ($result['success']) {
            $logger->log(
                'Cron sync completed for ' . $entity_type . ': ' .
            $result['processed'] . ' processed, ' .
            $result['success_count'] . ' successful, ' .
            $result['error_count'] . ' errors',
                'info'
            );
        } else {
            $logger->log(
                'Cron sync failed for ' . $entity_type . ': ' . $result['error'],
                'error'
            );
        }
    }

    // Send notification email if there were errors
    $total_errors = array_sum(array_column($results, 'error_count'));

    if ($total_errors > 0 && YujuConfig::get('YUJU_ENABLE_EMAIL_NOTIFICATIONS')) {
        sendErrorNotification($results, $total_errors);
    }

    $logger->log('Cron sync completed successfully', 'info');
} catch (Exception $e) {
    $logger->log('Cron sync failed: ' . $e->getMessage(), 'error');

    // Send error notification

    if (YujuConfig::get('YUJU_ENABLE_EMAIL_NOTIFICATIONS')) {
        sendCriticalErrorNotification($e);
    }

    exit(1);
}

/**
 * Send error notification email.
 */
function sendErrorNotification($results, $total_errors)
{
    $notification_email = YujuConfig::get('YUJU_NOTIFICATION_EMAIL');

    if (empty($notification_email)) {
        return;
    }

    $shop_name = Configuration::get('PS_SHOP_NAME');
    $subject = '[' . $shop_name . '] Yuju Sync Errors - ' . $total_errors . ' errors detected';

    $message = 'Dear Administrator,\n\n';
    $message .= 'The Yuju synchronization process has completed with errors.\n\n';
    $message .= 'Summary:\n';

    foreach ($results as $entity_type => $result) {
        $message .= '- ' . ucfirst($entity_type) . ': ';
        $message .= $result['processed'] . ' processed, ';
        $message .= $result['success_count'] . ' successful, ';
        $message .= $result['error_count'] . ' errors\n';
    }

    $message .= '\nPlease check the Yuju module logs for more details.\n\n';
    $message .= 'Time: ' . date('Y-m-d H:i:s') . '\n';
    $message .= 'Shop: ' . $shop_name . '\n';

    $headers = 'From: noreply@' . Configuration::get('PS_SHOP_DOMAIN') . '\r\n';
    $headers .= 'Reply-To: noreply@' . Configuration::get('PS_SHOP_DOMAIN') . '\r\n';
    $headers .= 'X-Mailer: PrestaShop Yuju Module\r\n';

    mail($notification_email, $subject, $message, $headers);
}

/**
 * Send critical error notification email.
 */
function sendCriticalErrorNotification($exception)
{
    $notification_email = YujuConfig::get('YUJU_NOTIFICATION_EMAIL');

    if (empty($notification_email)) {
        return;
    }

    $shop_name = Configuration::get('PS_SHOP_NAME');
    $subject = '[' . $shop_name . '] CRITICAL: Yuju Sync Failed';

    $message = 'Dear Administrator,\n\n';
    $message .= 'The Yuju synchronization process has failed with a critical error.\n\n';
    $message .= 'Error Details:\n';
    $message .= 'Message: ' . $exception->getMessage() . '\n';
    $message .= 'File: ' . $exception->getFile() . '\n';
    $message .= 'Line: ' . $exception->getLine() . '\n\n';
    $message .= 'Stack Trace:\n' . $exception->getTraceAsString() . '\n\n';
    $message .= 'Please check the server logs and Yuju module configuration.\n\n';
    $message .= 'Time: ' . date('Y-m-d H:i:s') . '\n';
    $message .= 'Shop: ' . $shop_name . '\n';

    $headers = 'From: noreply@' . Configuration::get('PS_SHOP_DOMAIN') . '\r\n';
    $headers .= 'Reply-To: noreply@' . Configuration::get('PS_SHOP_DOMAIN') . '\r\n';
    $headers .= 'X-Mailer: PrestaShop Yuju Module\r\n';

    mail($notification_email, $subject, $message, $headers);
}

// Output success for cron monitoring
echo 'Yuju sync completed at ' . date('Y-m-d H:i:s') . '\n';
