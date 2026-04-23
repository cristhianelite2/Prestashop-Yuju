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
     * @param string $action 'create' o 'update'
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
        require_once dirname(__FILE__) . '/YujuApiClient.php';
        
        try {
            $product_manager = new YujuProductManager();
            $api_client = new YujuApiClient();
            
            // Obtener yuju_product_id
            $status = Db::getInstance()->getRow('
                SELECT yuju_product_id 
                FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                WHERE prestashop_product_id = ' . (int)$product_id
            );
            
            if (!$status || empty($status['yuju_product_id'])) {
                $this->logger->warning('Cola: Producto no tiene yuju_product_id', [
                    'product_id' => $product_id
                ]);
                return false;
            }
            
            // Enviar actualización
            $result = $api_client->updateProduct($status['yuju_product_id'], $data);
            
            if ($result['success']) {
                $this->logger->info('Cola: Actualización inmediata exitosa', [
                    'product_id' => $product_id,
                    'yuju_product_id' => $status['yuju_product_id']
                ]);
                return true;
            } else {
                $this->logger->error('Cola: Error en actualización inmediata', [
                    'product_id' => $product_id,
                    'error' => $result['message']
                ]);
                return false;
            }
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
        $api_client = new YujuApiClient();
        
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0
        ];
        
        foreach ($batch as $item) {
            $stats['processed']++;
            
            // Marcar como procesando
            $this->updateQueueStatus($item['id'], 'processing');
            
            try {
                $data = json_decode($item['data'], true);
                $result = false;
                $sync_duration = 0;
                
                if ($item['action'] === 'create') {
                    $start_time = microtime(true);
                    $result = $product_manager->sendProductToYuju((int)$item['prestashop_product_id']);
                    $sync_duration = microtime(true) - $start_time;
                } else {
                    // Obtener yuju_product_id
                    $status = Db::getInstance()->getRow('
                        SELECT yuju_product_id 
                        FROM ' . _DB_PREFIX_ . 'yuju_product_status 
                        WHERE prestashop_product_id = ' . (int)$item['prestashop_product_id']
                    );
                    
                    if ($status && !empty($status['yuju_product_id'])) {
                        $start_time = microtime(true);
                        $api_result = $api_client->updateProduct($status['yuju_product_id'], $data);
                        $sync_duration = microtime(true) - $start_time;
                        $result = $api_result['success'];
                        
                        // Guardar historial de actualización
                        if ($result) {
                            $this->saveQueueUpdateHistory(
                                (int)$item['prestashop_product_id'],
                                $status['yuju_product_id'],
                                $data,
                                $api_result,
                                $sync_duration
                            );
                            
                            // Actualizar last_sync_data para futuras comparaciones
                            Db::getInstance()->update(
                                'yuju_product_status',
                                [
                                    'sync_status' => pSQL('synced'),
                                    'last_sync_at' => date('Y-m-d H:i:s'),
                                    'last_sync_data' => pSQL(json_encode($data)),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ],
                                'prestashop_product_id = ' . (int)$item['prestashop_product_id']
                            );
                        } else {
                            // Error en actualización
                            Db::getInstance()->update(
                                'yuju_product_status',
                                [
                                    'sync_status' => pSQL('synced_with_errors'),
                                    'last_error' => pSQL($api_result['message'] ?? 'Error en actualización'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ],
                                'prestashop_product_id = ' . (int)$item['prestashop_product_id']
                            );
                        }
                    }
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
