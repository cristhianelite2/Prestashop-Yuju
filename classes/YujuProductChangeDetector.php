<?php
/**
 * Clase para detectar cambios en productos de PrestaShop y determinar prioridad
 */
class YujuProductChangeDetector
{
    private $logger;
    
    // Campos de alta prioridad (precio y stock)
    const HIGH_PRIORITY_FIELDS = [
        'price',
        'wholesale_price',
        'quantity',
        'out_of_stock',
        'available_for_order',
    ];
    
    // Campos de prioridad normal
    const NORMAL_PRIORITY_FIELDS = [
        'name',
        'description',
        'description_short',
        'reference',
        'ean13',
        'upc',
        'weight',
        'width',
        'height',
        'depth',
        'active',
    ];
    
    public function __construct()
    {
        $this->logger = new YujuLogger();
    }
    
    /**
     * Detecta qué campos cambiaron en un producto
     * Compara el producto actual con su última sincronización en Yuju
     * 
     * @param Product $product El producto actual
     * @return array ['changed_fields' => [], 'priority' => 'high'|'normal', 'old_values' => [], 'new_values' => []]
     */
    public function detectChanges($product)
    {
        try {
            $product_id = (int)$product->id;
            
            $this->logger->info('detectChanges: Inicio', [
                'product_id' => $product_id
            ]);
            
            // Obtener la última data sincronizada desde yuju_product_status (más confiable)
            $status = Db::getInstance()->getRow('
                SELECT last_sync_data 
                FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                WHERE prestashop_product_id = ' . $product_id
            );
            
            $this->logger->info('detectChanges: Consultado yuju_product_status', [
                'found' => !empty($status),
                'has_last_sync_data' => !empty($status) && isset($status['last_sync_data'])
            ]);
            
            $old_values = [];
            if ($status && !empty($status['last_sync_data'])) {
                $this->logger->info('detectChanges: Decodificando last_sync_data...');
                $old_values = json_decode($status['last_sync_data'], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('detectChanges: Error decodificando JSON', [
                        'error' => json_last_error_msg()
                    ]);
                    $old_values = [];
                }
            }
            
            // Si no hay old_values, obtener datos actuales del producto para comparar en próxima actualización
            if (empty($old_values)) {
                $this->logger->info('detectChanges: No hay historial previo. No se pueden detectar cambios sin referencia.');
                // NO retornar cambios si no hay historial previo
                // En su lugar, guardar el estado actual para futuras comparaciones
                return [
                    'changed_fields' => [],
                    'priority' => 'normal',
                    'old_values' => [],
                    'new_values' => []
                ];
            }
            
            $this->logger->info('detectChanges: old_values obtenidos', [
                'count' => count($old_values),
                'keys' => !empty($old_values) ? array_keys($old_values) : []
            ]);
        
        $changed_fields = [];
        $new_values = [];
        $priority = 'normal';
        
        // Mapeo de campos de PrestaShop a nombres de Yuju (para consistencia en comparación y envío)
        // IMPORTANTE: Solo incluir campos que Yuju acepta y que queremos sincronizar
        $field_to_yuju_name = [
            'price' => 'price',
            'quantity' => 'stock',
            'name' => 'name',
            'reference' => 'sku_simple',
            'weight' => 'weight',
            // 'active' => NO EXISTE EN YUJU - removido
        ];
        
        // Verificar cambios en campos de alta prioridad
        $high_priority_checks = [
            'price' => $product->price,
            'quantity' => Product::getQuantity($product_id),
        ];
        
        foreach ($high_priority_checks as $ps_field => $new_value) {
            $yuju_field = isset($field_to_yuju_name[$ps_field]) ? $field_to_yuju_name[$ps_field] : $ps_field;
            
            // Solo comparar si el campo existe en old_values (fue sincronizado antes)
            if (!isset($old_values[$yuju_field])) {
                $this->logger->info("Campo $ps_field no existe en old_values, saltando", [
                    'yuju_field' => $yuju_field
                ]);
                continue;
            }
            
            $old_value = $old_values[$yuju_field];
            
            if ($this->valuesAreDifferent($old_value, $new_value)) {
                $changed_fields[] = $ps_field;
                $new_values[$yuju_field] = $new_value; // Guardar con nombre de Yuju
                $priority = 'high';
                
                $this->logger->info("Campo de alta prioridad cambió: $ps_field", [
                    'yuju_field' => $yuju_field,
                    'old' => $old_value,
                    'new' => $new_value
                ]);
            }
        }
        
        // Verificar cambios en campos normales
        $id_lang = Configuration::get('PS_LANG_DEFAULT');
        
        $this->logger->info('detectChanges: Preparando campos normales', [
            'id_lang' => $id_lang,
            'product_name_is_array' => is_array($product->name),
            'product_name' => $product->name
        ]);
        
        // Obtener el nombre en el idioma correcto
        $name_value = '';
        if (is_array($product->name)) {
            $name_value = isset($product->name[$id_lang]) ? $product->name[$id_lang] : '';
        } elseif (is_string($product->name)) {
            $name_value = $product->name;
        }
        
        $this->logger->info('detectChanges: Nombre extraído', [
            'name_value' => $name_value,
            'name_length' => strlen($name_value)
        ]);
        
        $normal_priority_checks = [
            'name' => $name_value,
            'reference' => $product->reference,
            'weight' => $product->weight,
        ];
        
        $this->logger->info('detectChanges: Campos a verificar', [
            'fields' => array_keys($normal_priority_checks),
            'values' => $normal_priority_checks
        ]);
        
        foreach ($normal_priority_checks as $ps_field => $new_value) {
            $yuju_field = isset($field_to_yuju_name[$ps_field]) ? $field_to_yuju_name[$ps_field] : $ps_field;
            
            // Solo comparar si el campo existe en old_values (fue sincronizado antes)
            if (!isset($old_values[$yuju_field])) {
                $this->logger->info("Campo $ps_field no existe en old_values, saltando", [
                    'yuju_field' => $yuju_field
                ]);
                continue;
            }
            
            $old_value = $old_values[$yuju_field];
            
            $this->logger->info("detectChanges: Comparando campo $ps_field", [
                'yuju_field' => $yuju_field,
                'old_value' => $old_value,
                'new_value' => $new_value,
                'are_different' => $this->valuesAreDifferent($old_value, $new_value)
            ]);
            
            if ($this->valuesAreDifferent($old_value, $new_value)) {
                $changed_fields[] = $ps_field;
                $new_values[$yuju_field] = $new_value; // Guardar con nombre de Yuju
                
                $this->logger->info("detectChanges: Campo $ps_field CAMBIÓ", [
                    'yuju_field' => $yuju_field,
                    'old' => $old_value,
                    'new' => $new_value
                ]);
            }
        }
        
        // Log de cambios detectados
        if (!empty($changed_fields)) {
            $this->logger->info('Cambios detectados en producto', [
                'product_id' => $product_id,
                'changed_fields' => $changed_fields,
                'priority' => $priority,
                'new_values' => $new_values
            ]);
        } else {
            $this->logger->info('detectChanges: NO se detectaron cambios', [
                'product_id' => $product_id
            ]);
        }
        
            $this->logger->info('detectChanges: Finalizando', [
                'changed_fields_count' => count($changed_fields),
                'priority' => $priority
            ]);
            
            return [
                'changed_fields' => $changed_fields,
                'priority' => $priority,
                'old_values' => $old_values,
                'new_values' => $new_values
            ];
            
        } catch (Exception $e) {
            $this->logger->error('detectChanges: EXCEPTION', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Retornar respuesta vacía en caso de error
            return [
                'changed_fields' => [],
                'priority' => 'normal',
                'old_values' => [],
                'new_values' => []
            ];
        }
    }
    
    /**
     * Compara dos valores teniendo en cuenta tipos
     */
    private function valuesAreDifferent($old_value, $new_value)
    {
        // Normalizar valores nulos
        if (is_null($old_value) && $new_value === '') return false;
        if ($old_value === '' && is_null($new_value)) return false;
        
        // Comparación numérica para precios y cantidades
        if (is_numeric($old_value) && is_numeric($new_value)) {
            return (float)$old_value !== (float)$new_value;
        }
        
        // Comparación de strings
        return (string)$old_value !== (string)$new_value;
    }
    
    /**
     * Prepara los datos para enviar a Yuju (solo campos modificados)
     * 
     * @param Product $product
     * @param array $changed_fields
     * @return array Datos formateados para la API de Yuju
     */
    public function prepareYujuUpdateData($product, $changed_fields)
    {
        require_once dirname(__FILE__) . '/YujuProductManager.php';
        
        $data = [];
        $product_manager = new YujuProductManager();
        
        // Mapear campos de PrestaShop a Yuju
        $field_mapping = [
            'price' => 'price',
            'quantity' => 'stock',
            'name' => 'name',
            'description' => 'description',
            'description_short' => 'short_description',
            'reference' => 'sku_simple',
            'active' => 'active',
            'weight' => 'weight',
            'ean13' => 'ean',
        ];
        
        foreach ($changed_fields as $field) {
            // Remover sufijo de idioma si existe
            $base_field = preg_replace('/_\d+$/', '', $field);
            
            if (isset($field_mapping[$base_field])) {
                $yuju_field = $field_mapping[$base_field];
                
                // Obtener valor según el tipo de campo
                if (in_array($base_field, ['name', 'description', 'description_short'])) {
                    // Campos multilang - usar idioma por defecto
                    $id_lang = Configuration::get('PS_LANG_DEFAULT');
                    $value = $product->{$base_field}[$id_lang];
                } else {
                    $value = $product->$base_field;
                }
                
                // Formatear valor según campo
                if ($base_field === 'price') {
                    $value = (float)$value;
                } elseif ($base_field === 'quantity') {
                    $value = (int)$value;
                } elseif ($base_field === 'active') {
                    $value = (bool)$value;
                }
                
                $data[$yuju_field] = $value;
            }
        }
        
        return $data;
    }
}
