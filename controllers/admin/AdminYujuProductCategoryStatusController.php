<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'prestashopyuju/controllers/admin/AdminYujuProductStatusController.php';

/**
 * Panel independiente con el estado de sincronización agrupado por categoría.
 * Reutiliza las consultas y acciones de estadísticas del controlador de productos.
 */
class AdminYujuProductCategoryStatusController extends AdminYujuProductStatusController
{
    public function initContent()
    {
        $this->context->smarty->assign('current_controller', 'AdminYujuProductCategoryStatus');
        parent::initContent();
        $this->setTemplate('product_category_status.tpl');
    }

    public function renderList()
    {
        $stats = $this->getProductStatusStats();
        $this->context->smarty->assign([
            'stats' => $stats,
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'prestashopyuju/views/templates/admin/product_status_stats.tpl'
        );
    }
}
