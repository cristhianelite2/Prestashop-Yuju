<?php
/**
 * Yuju Webhook entry point
 * Place this file at: /shop/yuju/webhook.php
 * Or access via friendly URL: /yuju/webhook (requires routes.yml)
 */

require_once dirname(__FILE__) . '/../config/config.inc.php';

$_GET['fc'] = 'module';
$_GET['module'] = 'prestashopyuju';
$_GET['controller'] = 'webhook';

require_once dirname(__FILE__) . '/../index.php';