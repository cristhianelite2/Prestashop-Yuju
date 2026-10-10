<?php
/**
 * 2024 Yuju Integration.
 *
 * Actualización 1.1.4 -> 1.1.5
 *
 * - Se retiran 20 hooks que ya no se usaban: la sincronización automática pasó a
 *   ejecutarse por cron (cron/sync.php) y por webhooks (webhook.php). Hay que
 *   desregistrarlos en las tiendas ya instaladas (PrestaShop no lo hace solo).
 * - Los 4 hooks de sincronización puntual (producto, stock/precio, validar
 *   pedido y estado de pedido) quedan disponibles pero DESHABILITADOS por defecto;
 *   se activan desde el switch del panel de configuración.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_1_5($module)
{
    // Hooks que el módulo ya no gestiona: desregistrar en tiendas existentes.
    $removed_hooks = [
        'actionProductAdd',
        'actionProductDelete',
        'actionProductAttributeUpdate',
        'actionProductAttributeDelete',
        'actionCategoryAdd',
        'actionCategoryUpdate',
        'actionCategoryDelete',
        'actionOrderReturn',
        'actionAttributeGroupDelete',
        'actionAttributeDelete',
        'actionCarrierUpdate',
        'actionCustomerAccountAdd',
        'actionCustomerAccountUpdate',
        'actionObjectManufacturerAddAfter',
        'actionObjectManufacturerUpdateAfter',
        'actionObjectManufacturerDeleteAfter',
        'displayAdminProductsExtra',
    ];

    foreach ($removed_hooks as $hook) {
        $hook_id = (int) Hook::getIdByName($hook);

        if ($hook_id > 0 && $module->isRegisteredInHook($hook)) {
            $module->unregisterHook($hook_id);
        }
    }

    // Hooks de sincronización que deben quedar deshabilitados por defecto.
    $default_disabled = [
        'actionProductUpdate',
        'actionUpdateQuantity',
        'actionValidateOrder',
        'actionOrderStatusUpdate',
    ];

    $raw = Configuration::get('YUJU_DISABLED_HOOKS');
    $current = [];

    if (is_string($raw) && $raw !== '') {
        $current = array_filter(array_map('trim', explode(',', $raw)));
    }

    $merged = array_values(array_unique(array_merge($current, $default_disabled)));
    Configuration::updateValue('YUJU_DISABLED_HOOKS', implode(',', $merged));

    foreach ($default_disabled as $hook) {
        $hook_id = (int) Hook::getIdByName($hook);

        if ($hook_id > 0 && $module->isRegisteredInHook($hook)) {
            $module->unregisterHook($hook_id);
        }
    }

    return true;
}
