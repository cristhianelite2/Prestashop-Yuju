<?php
/**
 * Clase para manejar la cola de sincronización por lotes
 */
class YujuSyncQueue
{
    private $logger;
    
    public function __construct()
    {
        require_once dirname(__FILE__) . '/YujuLogger.php';
        $this->logger = new YujuLogger();
    }
    
    /**
     * Agregar producto a la cola de sincronización
     * 
     * @param int $product_id ID del producto en PrestaShop
     * @param string $action 'create', 'update' o 'delete'
     * @param string $priority 'high' (precio/stock) o 'normal' (otros campos)
     * @param array $data Datos a sincronizar
     * @return bool
     */
    public function addToQueue($product_id, $action, $priority = 'normal', $data = [])
    {
        try {
            // Si es prioridad alta (precio/stock), procesar inmediatamente
            if ($priority === 'high') {
                $this->logger->info('Cola: Prioridad ALTA - Procesando inmediatamente', [
                    'product_id' => $product_id,
                    'action' => $action
                ]);
                return $this->processImmediately($product_id, $action, $data);
            }
            
            // Verificar si ya existe en cola pendiente
            $existing = Db::getInstance()->getRow('
                SELECT id 
                FROM ' . _DB_PREFIX_ . 'yuju_sync_queue 
                WHERE prestashop_product_id = ' . (int)$product_id . '
                AND action = "' . pSQL($action) . '"
                AND status = "pending"
            ');
            
            if ($existing) {
                // Actualizar el registro existente
                Db::getInstance()->update('yuju_sync_queue', [
                    'data' => pSQL(json_encode($data)),
                    'created_at' => date('Y-m-d H:i:s')
                ], 'id = ' . (int)$existing['id']);
                
                $this->logger->info('Cola: Actualizado registro existente', [
                    'queue_id' => $existing['id'],
                    'product_id' => $product_id
                ]);
            } else {
                // Crear nuevo registro en cola
                Db::getInstance()->insert('yuju_sync_queue', [
                    'prestashop_product_id' => (int)$product_id,
                    'action' => pSQL($action),
                    'priority' => pSQL($priority),
                    'status' => 'pending',
                    'data' => pSQL(json_encode($data)),
                    'attempts' => 0,
                    'max_attempts' => 3,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $this->logger->info('Cola: Producto agregado', [
                    'product_id' => $product_id,
                    'action' => $action,
                    'priority' => $priority
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            $this->logger->error('Cola: Error al agregar producto', [
                'error' => $e->getMessage(),
                'product_id' => $product_id
            ]);
            return false;
        }
    }
    
    /**
     * Procesar inmediatamente (para prioridad alta)
     */
    private function processImmediately($product_id, $action, $data)
    {
        require_once dirname(__FILE__) . '/YujuProductManager.php';
        
        try {
            $product_manager = new YujuProductManager();
            if ($action === 'delete') {
                $result = $product_manager->removeProductFromYujuAndLocalStatus((int) $product_id);
                return !empty($result['success']);
            }

            // create/update: usar el flujo único que aplica todas las validaciones
            // (duplicados de SKU, mapeo de categoría y campos obligatorios).
            $result = $product_manager->sendProductToYuju((int) $product_id);
            return is_array($result) && !empty($result['success']);
        } catch (Exception $e) {
            $this->logger->error('Cola: Excepción en procesamiento inmediato', [
                'error' => $e->getMessage(),
                'product_id' => $product_id
            ]);
            return false;
        }
    }
    
    /**
     * Obtener lote de productos pendientes para procesar
     * 
     * @param int $batch_size Tamaño del lote
     * @return array
     */
    public function getNextBatch($batch_size = 100)
    {
        $items = Db::getInstance()->executeS('
            SELECT * 
            FROM ' . _DB_PREFIX_ . 'yuju_sync_queue 
            WHERE status = "pending"
            AND attempts < max_attempts
            ORDER BY priority DESC, created_at ASC
            LIMIT ' . (int)$batch_size
        );
        
        return $items ? $items : [];
    }
    
    /**
     * Procesar un lote de productos
     * 
     * @param int $batch_size Tamaño del lote
     * @return array Resultados del procesamiento
     */
    public function processBatch($batch_size = 100)
    {
        require_once dirname(__FILE__) . '/YujuProductManager.php';
        require_once dirname(__FILE__) . '/YujuApiClient.php';
        
        $batch = $this->getNextBatch($batch_size);
        
        if (empty($batch)) {
            $this->logger->info('Cola: No hay elementos pendientes para procesar');
            return [
                'processed' => 0,
                'success' => 0,
                'failed' => 0
            ];
        }
        
        $this->logger->info('Cola: Procesando lote', [
            'batch_size' => count($batch)
        ]);
        
        $product_manager = new YujuProductManager();
        
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0
        ];
        
        foreach ($batch as $item) {
            $stats['processed']++;
            
            // Marcar como procesando
            $this->updateQueueStatus($item['id'], 'processing');

            // Reflejar el estado intermedio en yuju_product_status para que la UI
            // muestre "Actualizando…" / "Creando…" / "Eliminando…" mientras se
            // procesa el item en la cola (entre "En cola" y "Sincronizado").
            $this->setProductStatusForQueueAction(
                (int) $item['prestashop_product_id'],
                (string) $item['action']
            );
            
            try {
                $data = json_decode($item['data'], true);
                if (!is_array($data)) {
                    $data = [];
                }
                $result = false;
                $sync_duration = 0;

                if ($item['action'] === 'delete') {
                    $start_time = microtime(true);
                    $del = $product_manager->removeProductFromYujuAndLocalStatus((int) $item['prestashop_product_id']);
                    $sync_duration = microtime(true) - $start_time;
                    $result = !empty($del['success']);
                    if (!$result) {
                        $this->handleQueueError($item['id'], $del['message'] ?? 'Error al eliminar en Yuju');
                        ++$stats['failed'];
                        continue;
                    }
                } elseif ($item['action'] === 'create') {
                    $start_time = microtime(true);
                    $send_res = $product_manager->sendProductToYuju((int) $item['prestashop_product_id'], $data);
                    $sync_duration = microtime(true) - $start_time;
                    $result = is_array($send_res) && !empty($send_res['success']);
                } else {
                    // update (o cola antigua): usar SIEMPRE el flujo centralizado para aplicar
                    // validaciones de duplicados/mapeo/campos obligatorios antes de enviar a Yuju.
                    $start_time = microtime(true);
                    $send_res = $product_manager->sendProductToYuju((int) $item['prestashop_product_id'], $data);
                    $sync_duration = microtime(true) - $start_time;
                    $result = is_array($send_res) && !empty($send_res['success']);
                }
                
                if ($result) {
                    $this->updateQueueStatus($item['id'], 'completed');
                    $stats['success']++;
                    
                    $this->logger->info('Cola: Producto procesado exitosamente', [
                        'queue_id' => $item['id'],
                        'product_id' => $item['prestashop_product_id'],
                        'action' => $item['action']
                    ]);
                } else {
                    $this->handleQueueError($item['id'], 'Error al procesar producto');
                    $stats['failed']++;
                }
            } catch (Exception $e) {
                $this->handleQueueError($item['id'], $e->getMessage());
                $stats['failed']++;
                
                $this->logger->error('Cola: Error procesando producto', [
                    'queue_id' => $item['id'],
                    'product_id' => $item['prestashop_product_id'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('Cola: Lote procesado', $stats);
        
        return $stats;
    }
    
    /**
     * Marcar el estado intermedio del producto en yuju_product_status según la
     * acción que se está procesando en la cola.
     */
    private function setProductStatusForQueueAction($product_id, $action)
    {
        try {
            $map = [
                'create' => 'creating_in_yuju',
                'update' => 'updating_in_yuju',
                'delete' => 'deleting_in_yuju',
            ];
            $sync_status = isset($map[$action]) ? $map[$action] : 'updating_in_yuju';

            Db::getInstance()->update(
                'yuju_product_status',
                [
                    'sync_status' => pSQL($sync_status),
                    'last_error' => null,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'prestashop_product_id = ' . (int) $product_id
            );
        } catch (Exception $e) {
            // Silencioso: no debe interrumpir el procesamiento de la cola
        }
    }

    /**
     * Actualizar estado de un elemento en la cola
     */
    private function updateQueueStatus($queue_id, $status)
    {
        $data = [
            'status' => pSQL($status)
        ];
        
        if ($status === 'completed') {
            $data['processed_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'processing') {
            $data['last_attempt_at'] = date('Y-m-d H:i:s');
        }
        
        Db::getInstance()->update('yuju_sync_queue', $data, 'id = ' . (int)$queue_id);
    }
    
    /**
     * Manejar error en elemento de la cola
     */
    private function handleQueueError($queue_id, $error_message)
    {
        // Incrementar intentos
        Db::getInstance()->execute('
            UPDATE ' . _DB_PREFIX_ . 'yuju_sync_queue 
            SET attempts = attempts + 1,
                status = "pending",
                error_message = "' . pSQL($error_message) . '",
                last_attempt_at = "' . date('Y-m-d H:i:s') . '"
            WHERE id = ' . (int)$queue_id
        );
        
        // Si alcanzó el máximo de intentos, marcar como fallido
        $item = Db::getInstance()->getRow('
            SELECT attempts, max_attempts 
            FROM ' . _DB_PREFIX_ . 'yuju_sync_queue 
            WHERE id = ' . (int)$queue_id
        );
        
        if ($item && $item['attempts'] >= $item['max_attempts']) {
            $this->updateQueueStatus($queue_id, 'failed');
        }
    }
    
    /**
     * Obtener estadísticas de la cola
     */
    public function getQueueStats()
    {
        $stats = Db::getInstance()->getRow('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            FROM ' . _DB_PREFIX_ . 'yuju_sync_queue
        ');
        
        return $stats;
    }
    
    /**
     * Limpiar elementos completados antiguos
     * 
     * @param int $days Días de antigüedad
     */
    public function cleanOldCompleted($days = 7)
    {
        $deleted = Db::getInstance()->execute('
            DELETE FROM ' . _DB_PREFIX_ . 'yuju_sync_queue 
            WHERE status = "completed" 
            AND processed_at < DATE_SUB(NOW(), INTERVAL ' . (int)$days . ' DAY)
        ');
        
        $this->logger->info('Cola: Limpieza de registros completados', [
            'deleted' => $deleted
        ]);
        
        return $deleted;
    }
    
    /**
     * Guardar historial de actualización desde cola
     */
    private function saveQueueUpdateHistory($product_id, $yuju_product_id, $data, $result, $sync_duration)
    {
        try {
            // Obtener valores anteriores y nuevos para el historial
            $changed_fields = array_keys($data);
            
            // Cargar producto para obtener valores actuales
            $product = new Product($product_id);
            
            $old_values = [];
            $new_values = [];
            
            // Mapeo de campos Yuju -> PrestaShop para el historial
            $field_map = [
                'price' => 'price',
                'stock' => 'quantity',
                'name' => 'name',
                'sku_simple' => 'reference',
                'weight' => 'weight'
            ];
            
            foreach ($data as $yuju_field => $new_value) {
                $ps_field = isset($field_map[$yuju_field]) ? $field_map[$yuju_field] : $yuju_field;
                
                // Obtener valor anterior del last_sync_data
                $last_sync = Db::getInstance()->getRow('
                    SELECT last_sync_data 
                    FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                    WHERE prestashop_product_id = ' . (int)$product_id
                );
                
                $old_data = $last_sync && !empty($last_sync['last_sync_data']) 
                    ? json_decode($last_sync['last_sync_data'], true) 
                    : [];
                
                $old_values[$yuju_field] = isset($old_data[$yuju_field]) ? $old_data[$yuju_field] : null;
                $new_values[$yuju_field] = $new_value;
            }
            
            // Insertar en historial
            Db::getInstance()->insert('yuju_sync_history', [
                'prestashop_product_id' => (int)$product_id,
                'yuju_product_id' => pSQL($yuju_product_id),
                'sync_type' => 'update',
                'sync_direction' => 'prestashop_to_yuju',
                'changed_fields' => pSQL(json_encode($changed_fields)),
                'old_values' => pSQL(json_encode($old_values)),
                'new_values' => pSQL(json_encode($new_values)),
                'sync_status' => $result['success'] ? 'success' : 'error',
                'error_message' => isset($result['message']) && !$result['success'] ? pSQL($result['message']) : null,
                'sync_duration' => round($sync_duration, 4),
                'created_at' => date('Y-m-d H:i:s'),
                'metadata' => pSQL(json_encode([
                    'source' => 'queue',
                    'api_response' => $result
                ]))
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Cola: Error guardando historial', [
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
