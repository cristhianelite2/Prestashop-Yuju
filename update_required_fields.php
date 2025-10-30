<?php
/**
 * Script temporal para actualizar campos requeridos
 */

// Incluir configuración de PrestaShop
$config_files = [
    dirname(__FILE__) . '/../../../../config/config.inc.php',
    dirname(__FILE__) . '/../../../config/config.inc.php',
    dirname(__FILE__) . '/../../config/config.inc.php'
];

foreach ($config_files as $config_file) {
    if (file_exists($config_file)) {
        require_once $config_file;
        break;
    }
}

if (!defined('_PS_VERSION_')) {
    die('PrestaShop configuration not found');
}

// Lista de campos que deben ser obligatorios según requerimientos
$required_fields = [
    'name', 'reference', 'description', 'images', 'price', 
    'quantity', 'manufacturer', 'condition', 'shipping_method', 
    'shipping_price', 'dimension_unit', 'height', 'width', 
    'depth', 'weight_unit', 'weight', 'ml_template'
];

echo "Iniciando actualización de campos requeridos...\n";

$updated = 0;
foreach ($required_fields as $field) {
    $query = '
        UPDATE ' . _DB_PREFIX_ . 'yuju_product_mapping 
        SET is_required = 1 
        WHERE prestashop_field = "' . pSQL($field) . '"';
    
    $result = Db::getInstance()->execute($query);
    
    if ($result) {
        $updated++;
        echo "✓ Campo '{$field}' marcado como obligatorio\n";
    } else {
        echo "✗ Error al actualizar campo '{$field}'\n";
    }
}

echo "\nResumen:\n";
echo "- Campos actualizados: {$updated}\n";
echo "- Total de campos procesados: " . count($required_fields) . "\n";

// Verificar resultados
echo "\nVerificando resultados:\n";
$mappings = Db::getInstance()->executeS('
    SELECT prestashop_field, yuju_field, is_required 
    FROM ' . _DB_PREFIX_ . 'yuju_product_mapping 
    ORDER BY prestashop_field
');

foreach ($mappings as $mapping) {
    $status = $mapping['is_required'] ? '✓ SÍ' : '✗ NO';
    echo "- {$mapping['prestashop_field']} -> {$mapping['yuju_field']}: {$status}\n";
}

echo "\n¡Actualización completada!\n";
