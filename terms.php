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
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

// Punto de entrada .php directo: despacha al controlador front "terms" del módulo.
require_once dirname(__FILE__) . '/../../config/config.inc.php';

$_GET['fc'] = 'module';
$_GET['module'] = 'prestashopyuju';
$_GET['controller'] = 'terms';

require_once dirname(__FILE__) . '/../../index.php';
