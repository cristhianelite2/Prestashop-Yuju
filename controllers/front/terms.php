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

class PrestashopyujuTermsModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    public $display_column_right = false;

    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
    }

    public function initContent()
    {
        parent::initContent();

        // Obtener información de la tienda
        $shop_name = Configuration::get('PS_SHOP_NAME');
        $shop_email = Configuration::get('PS_SHOP_EMAIL');
        $shop_url = Tools::getHttpHost(true) . __PS_BASE_URI__;
        
        // Fecha actual para los términos
        $current_date = date('d/m/Y');
        
        $this->context->smarty->assign([
            'shop_name' => $shop_name,
            'shop_email' => $shop_email,
            'shop_url' => $shop_url,
            'current_date' => $current_date,
            'module_version' => $this->module->version,
        ]);

        $this->setTemplate('module:prestashopyuju/views/templates/front/terms.tpl');
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        
        $breadcrumb['links'][] = [
            'title' => $this->trans('Términos y Condiciones - Integración Yuju', [], 'Modules.Prestashopyuju.Shop'),
            'url' => $this->context->link->getModuleLink('prestashopyuju', 'terms'),
        ];

        return $breadcrumb;
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $this->trans('Términos y Condiciones - Integración Yuju', [], 'Modules.Prestashopyuju.Shop');
        $page['meta']['description'] = $this->trans('Términos y condiciones para el uso de la integración con Yuju', [], 'Modules.Prestashopyuju.Shop');
        $page['meta']['keywords'] = $this->trans('términos, condiciones, yuju, integración', [], 'Modules.Prestashopyuju.Shop');
        
        return $page;
    }
}