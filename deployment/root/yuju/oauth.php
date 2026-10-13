<?php
/**
 * Puente OAuth opcional para la raíz de PrestaShop.
 *
 * Destino de despliegue: /shop/yuju/oauth.php
 * El nombre del módulo actual es "yuju" (no "prestashopyuju").
 */
require_once dirname(__FILE__) . '/../../config/config.inc.php';

$_GET['fc'] = 'module';
$_GET['module'] = 'yuju';
$_GET['controller'] = 'oauth';

require_once dirname(__FILE__) . '/../../index.php';
