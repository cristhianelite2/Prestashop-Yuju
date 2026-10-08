<?php
/**
 * Yuju OAuth callback entry point
 * Place this file at: /shop/yuju/oauth.php
 * Or access via friendly URL: /yuju/oauth (requires routes.yml)
 */

require_once dirname(__FILE__) . '/../config/config.inc.php';

$_GET['fc'] = 'module';
$_GET['module'] = 'prestashopyuju';
$_GET['controller'] = 'oauth';

require_once dirname(__FILE__) . '/../index.php';