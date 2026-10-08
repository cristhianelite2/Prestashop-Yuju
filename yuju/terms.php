<?php
/**
 * Yuju Terms entry point
 * Place this file at: /shop/yuju/terms.php
 * Or access via friendly URL: /yuju/terms (requires routes.yml)
 */

require_once dirname(__FILE__) . '/../config/config.inc.php';

$_GET['fc'] = 'module';
$_GET['module'] = 'prestashopyuju';
$_GET['controller'] = 'terms';

require_once dirname(__FILE__) . '/../index.php';