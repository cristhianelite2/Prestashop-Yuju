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
     * Agregar producto a la cola de sincronización.
     * Nunca duplica el mismo producto+acción si ya hay uno pending/processing.
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
            $product_id = (int) $product_id;
            $action = (string) $action;
            if (!is_array($data)) {
                $data = [];
            }
            $forceResend = !empty($data['force_resend_create']);

            // Si es prioridad alta (precio/stock), procesar inmediatamente
            if ($priority === 'high') {
                $this->logger->info('Cola: Prioridad ALTA - Procesando inmediatamente', [
                    'product_id' => $product_id,
                    'action' => $action
                ]);
                return $this->processImmediately($product_id, $action, $data);
            }

            // Ya en cola (pending o processing) → actualizar datos del más antiguo, no insertar otro
            $existing = Db::getInstance()->getRow('
                SELECT id, status
                FROM ' . _DB_PREFIX_ . 'yuju_sync_queue
                WHERE prestashop_product_id = ' . $product_id . '
                  AND action = "' . pSQL($action) . '"
                  AND status IN ("pending", "processing")
                ORDER BY id ASC
            ');

            if ($existing) {
                // Si está processing, no tocar el payload en vuelo; solo evitar duplicado
                if ((string) $existing['status'] === 'pending') {
                    Db::getInstance()->update('yuju_sync_queue', [
                        'data' => pSQL(json_encode($data)),
                        'priority' => pSQL($priority),
                        'created_at' => date('Y-m-d H:i:s'),
                    ], 'id = ' . (int) $existing['id']);
                }

                // Cancelar cualquier otro pending duplicado del mismo producto+acción
                $this->cancelDuplicatePendingItems($product_id, $action, (int) $existing['id']);

                $this->logger->info('Cola: Sin duplicar — registro activo actualizado/reutilizado', [
                    'queue_id' => $existing['id'],
                    'product_id' => $product_id,
                    'action' => $action,
                    'status' => $existing['status'],
                ]);

                return true;
            }

            // Create ya enviado y en espera de webhook: no encolar otro create
            if ($action === 'create' && !$forceResend) {
                $syncStatus = (string) Db::getInstance()->getValue(
                    'SELECT sync_status FROM ' . _DB_PREFIX_ . 'yuju_product_status
                    WHERE prestashop_product_id = ' . $product_id
                );
                if ($syncStatus === 'creating_in_yuju') {
                    $this->logger->info('Cola: Create omitido — producto ya en espera de webhook', [
                        'product_id' => $product_id,
                    ]);

                    return true;
                }
            }

            Db::getInstance()->insert('yuju_sync_queue', [
                'prestashop_product_id' => $product_id,
                'action' => pSQL($action),
                'priority' => pSQL($priority),
                'status' => 'pending',
                'data' => pSQL(json_encode($data)),
                'attempts' => 0,
                'max_attempts' => 3,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $newId = (int) Db::getInstance()->Insert_ID();
            if ($newId > 0) {
                $this->cancelDuplicatePendingItems($product_id, $action, $newId);
            }

            $this->logger->info('Cola: Producto agregado', [
                'product_id' => $product_id,
                'action' => $action,
                'priority' => $priority,
                'queue_id' => $newId,
            ]);

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
     * Marca como failed otros pending del mismo producto+acción (deja solo $keepId).
     *
     * @param int $product_id
     * @param string $action
     * @param int $keepId
     *
     * @return void
     */
    protected function cancelDuplicatePendingItems($product_id, $action, $keepId)
    {
        $product_id = (int) $product_id;
        $keepId = (int) $keepId;
        if ($product_id <= 0 || $keepId <= 0) {
            return;
        }

        Db::getInstance()->execute(
            'UPDATE ' . _DB_PREFIX_ . 'yuju_sync_queue
            SET status = "failed",
                error_message = "Cancelado: duplicado en cola (mismo producto/acción)",
                processed_at = "' . pSQL(date('Y-m-d H:i:s')) . '"
            WHERE prestashop_product_id = ' . $product_id . '
              AND action = "' . pSQL($action) . '"
              AND status = "pending"
              AND id <> ' . $keepId
        );
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
            // Pasar $data (p.ej. preferred_ps_category_id) para no perder el mapeo del contexto.
            $result = $product_manager->sendProductToYuju((int) $product_id, is_array($data) ? $data : []);
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
     * Tamaño de lote efectivo: siempre respeta YUJU_BATCH_SIZE de configuración
     * (AdminYujuConfiguration). Nunca supera ese tope aunque el caller pida más.
     *
     * Ese valor limita los envíos reales a la API Yuju por ejecución de cola.
     *
     * @param int|null $requested
     *
     * @return int
     */
    public function resolveBatchSize($requested = null)
    {
        if (!class_exists('YujuConfig', false)) {
            require_once dirname(__FILE__) . '/../config/config.php';
        }

        $configured = (int) YujuConfig::get('YUJU_BATCH_SIZE', 100);
        if ($configured < 1) {
            $configured = 100;
        }
        if ($configured > 500) {
            $configured = 500;
        }

        if ($requested === null || (int) $requested <= 0) {
            return $configured;
        }

        return min((int) $requested, $configured);
    }

    /**
     * Obtener lote de productos pendientes para procesar.
     *
     * @param int $batch_size Tamaño del lote (candidatos a evaluar)
     * @param string|array|null $actions Filtro opcional: 'create', 'update', 'delete' o lista
     *
     * @return array
     */
    public function getNextBatch($batch_size = 100, $actions = null)
    {
        $batch_size = (int) $batch_size;
        if ($batch_size < 1) {
            return [];
        }

        $actionFilter = '';
        if ($actions !== null && $actions !== '') {
            $list = is_array($actions) ? $actions : [$actions];
            $safe = [];
            foreach ($list as $action) {
                $action = (string) $action;
                if (in_array($action, ['create', 'update', 'delete'], true)) {
                    $safe[] = '"' . pSQL($action) . '"';
                }
            }
            if (!empty($safe)) {
                $actionFilter = ' AND action IN (' . implode(',', $safe) . ')';
            }
        }

        // Un solo item pendiente por producto+acción (el más antiguo),
        // por si quedaron duplicados históricos antes del dedupe.
        // Orden por tipo: delete → create → update (opción 5: carriles separados).
        $items = Db::getInstance()->executeS('
            SELECT q.*
            FROM ' . _DB_PREFIX_ . 'yuju_sync_queue q
            INNER JOIN (
                SELECT MIN(id) AS id
                FROM ' . _DB_PREFIX_ . 'yuju_sync_queue
                WHERE status = "pending"
                  AND attempts < max_attempts
                  ' . $actionFilter . '
                GROUP BY prestashop_product_id, action
            ) t ON t.id = q.id
            ORDER BY FIELD(q.action, "delete", "create", "update"),
                     q.priority DESC,
                     q.created_at ASC
            LIMIT ' . $batch_size
        );

        return $items ? $items : [];
    }
    
    /**
     * Procesar un lote de la cola respetando YUJU_BATCH_SIZE como tope de
     * envíos reales a Yuju (no de items locales).
     *
     * Carriles (opción 5):
     * 1) delete  2) create (throttle ~2 req/s)  3) update
     * Los update sin cambios (skipped_no_diff) no consumen cupo de API.
     *
     * @param int|null $batch_size Tope solicitado; se recorta a la config
     *
     * @return array Resultados del procesamiento
     */
    public function processBatch($batch_size = null)
    {
        require_once dirname(__FILE__) . '/YujuProductManager.php';
        require_once dirname(__FILE__) . '/YujuApiClient.php';

        $apiBudget = $this->resolveBatchSize($batch_size);
        $product_manager = new YujuProductManager();

        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'items' => [],
            'phase_totals_ms' => [
                'resolve_id_ms' => 0.0,
                'dup_check_ms' => 0.0,
                'prepare_ms' => 0.0,
                'validate_ms' => 0.0,
                'diff_ms' => 0.0,
                'api_ms' => 0.0,
                'persist_ms' => 0.0,
            ],
            'skipped_no_diff' => 0,
            'api_calls' => 0,
            'api_budget' => $apiBudget,
            'by_action' => [
                'delete' => ['processed' => 0, 'success' => 0, 'failed' => 0, 'api_calls' => 0],
                'create' => ['processed' => 0, 'success' => 0, 'failed' => 0, 'api_calls' => 0],
                'update' => ['processed' => 0, 'success' => 0, 'failed' => 0, 'api_calls' => 0, 'skipped_no_diff' => 0],
            ],
            'stopped_at_budget' => false,
        ];

        $this->logger->info('Cola: Procesando lote (tope envíos Yuju)', [
            'api_budget' => $apiBudget,
            'configured_batch_size' => $apiBudget,
        ]);

        // Mín. 500 ms entre llamadas reales a Yuju (límite ~2 req/s).
        $apiGapUs = 500000;
        $lastApiAt = null;

        foreach (['delete', 'create', 'update'] as $lane) {
            while ($stats['api_calls'] < $apiBudget) {
                $remainingBudget = $apiBudget - $stats['api_calls'];
                // En update se pueden drenar muchos skip sin API: traer más candidatos.
                $fetchLimit = ($lane === 'update')
                    ? min(max($remainingBudget * 5, $remainingBudget), 500)
                    : $remainingBudget;

                $batch = $this->getNextBatch($fetchLimit, $lane);
                if (empty($batch)) {
                    break;
                }

                $this->logger->info('Cola: Carril ' . $lane, [
                    'candidates' => count($batch),
                    'api_remaining' => $remainingBudget,
                ]);

                $apiBeforeLaneChunk = (int) $stats['api_calls'];
                $processedBefore = (int) $stats['processed'];

                foreach ($batch as $item) {
                    if ($stats['api_calls'] >= $apiBudget) {
                        $stats['stopped_at_budget'] = true;
                        break 3;
                    }

                    $this->processQueueItem($item, $product_manager, $stats, $lastApiAt, $apiGapUs);
                }

                // Evitar bucle infinito si no avanzamos (no debería ocurrir).
                if ((int) $stats['processed'] === $processedBefore
                    && (int) $stats['api_calls'] === $apiBeforeLaneChunk
                ) {
                    break;
                }

                // Create/delete: un fetch basta para el cupo restante.
                // Update: seguir si aún hay cupo (más skips/updates pendientes).
                if ($lane !== 'update') {
                    break;
                }
            }

            if ($stats['api_calls'] >= $apiBudget) {
                $stats['stopped_at_budget'] = true;
                break;
            }
        }

        if ($stats['processed'] === 0) {
            $this->logger->info('Cola: No hay elementos pendientes para procesar');
        }

        $this->logger->info('Cola: Lote procesado', [
            'processed' => $stats['processed'],
            'success' => $stats['success'],
            'failed' => $stats['failed'],
            'skipped_no_diff' => $stats['skipped_no_diff'],
            'api_calls' => $stats['api_calls'],
            'api_budget' => $stats['api_budget'],
            'stopped_at_budget' => $stats['stopped_at_budget'],
            'by_action' => $stats['by_action'],
            'phase_totals_ms' => $stats['phase_totals_ms'],
        ]);

        return $stats;
    }

    /**
     * Procesa un item de cola y actualiza $stats.
     * Respeta throttle entre envíos reales a Yuju.
     *
     * @param array $item
     * @param YujuProductManager $product_manager
     * @param array $stats
     * @param float|null $lastApiAt
     * @param int $apiGapUs
     *
     * @return void
     */
    private function processQueueItem($item, $product_manager, array &$stats, &$lastApiAt, $apiGapUs)
    {
        $action = (string) $item['action'];
        if (!isset($stats['by_action'][$action])) {
            $action = 'update';
        }

        $stats['processed']++;
        $stats['by_action'][$action]['processed']++;

        $this->updateQueueStatus($item['id'], 'processing');
        $this->setProductStatusForQueueAction(
            (int) $item['prestashop_product_id'],
            $action
        );

        $itemDetail = [
            'queue_id' => (int) $item['id'],
            'product_id' => (int) $item['prestashop_product_id'],
            'queue_action' => $action,
            'ok' => false,
            'duration_s' => 0,
            'api_action' => $action,
            'skipped_no_diff' => false,
            'timings' => [],
            'diff_fields' => null,
            'had_baseline' => null,
            'error' => null,
        ];

        try {
            $data = json_decode($item['data'], true);
            if (!is_array($data)) {
                $data = [];
            }
            if (empty($data['origin'])) {
                $data['origin'] = 'cola';
            }
            $result = false;
            $sync_duration = 0;
            $send_res = null;
            $madeApiCall = false;

            if ($action === 'delete') {
                $this->waitForApiGap($lastApiAt, $apiGapUs);
                $start_time = microtime(true);
                $del = $product_manager->removeProductFromYujuAndLocalStatus((int) $item['prestashop_product_id']);
                $sync_duration = microtime(true) - $start_time;
                $lastApiAt = microtime(true);
                $madeApiCall = true;
                $result = !empty($del['success']);
                $itemDetail['duration_s'] = round($sync_duration, 3);
                $itemDetail['api_action'] = 'delete';
                if (!$result) {
                    $itemDetail['error'] = $del['message'] ?? 'Error al eliminar en Yuju';
                    $stats['items'][] = $itemDetail;
                    $this->handleQueueError($item['id'], $del['message'] ?? 'Error al eliminar en Yuju');
                    ++$stats['failed'];
                    ++$stats['by_action'][$action]['failed'];
                    ++$stats['api_calls'];
                    ++$stats['by_action'][$action]['api_calls'];

                    return;
                }
            } else {
                // create: siempre throttle antes (1 request = 1 envío).
                // update: throttle solo si el gap vs la última API aún no se cumplió;
                // los skip sin diff no renuevan lastApiAt, así no frenan el drenaje.
                if ($action === 'create' || $lastApiAt !== null) {
                    $this->waitForApiGap($lastApiAt, $apiGapUs);
                }
                $start_time = microtime(true);
                $send_res = $product_manager->sendProductToYuju((int) $item['prestashop_product_id'], $data);
                $sync_duration = microtime(true) - $start_time;
                $result = is_array($send_res) && !empty($send_res['success']);
            }

            $itemDetail['duration_s'] = round($sync_duration, 3);
            if (is_array($send_res)) {
                $itemDetail['api_action'] = isset($send_res['action']) ? (string) $send_res['action'] : $action;
                $itemDetail['skipped_no_diff'] = !empty($send_res['skipped_no_diff']);
                $itemDetail['timings'] = isset($send_res['timings']) && is_array($send_res['timings']) ? $send_res['timings'] : [];
                $itemDetail['diff_fields'] = isset($send_res['diff_fields']) ? $send_res['diff_fields'] : null;
                $itemDetail['had_baseline'] = isset($send_res['had_baseline']) ? $send_res['had_baseline'] : null;
                if (!$result) {
                    $itemDetail['error'] = $send_res['error'] ?? ($send_res['message'] ?? 'Error');
                }
                foreach ($stats['phase_totals_ms'] as $phaseKey => $_) {
                    if (isset($itemDetail['timings'][$phaseKey])) {
                        $stats['phase_totals_ms'][$phaseKey] += (float) $itemDetail['timings'][$phaseKey];
                    }
                }
                if (!empty($send_res['skipped_no_diff'])) {
                    ++$stats['skipped_no_diff'];
                    if (isset($stats['by_action'][$action]['skipped_no_diff'])) {
                        ++$stats['by_action'][$action]['skipped_no_diff'];
                    }
                    // Skip local: no consume cupo YUJU_BATCH_SIZE ni renueva el throttle.
                } elseif (!empty($itemDetail['timings']['api_ms']) && (float) $itemDetail['timings']['api_ms'] > 0) {
                    $madeApiCall = true;
                    $lastApiAt = microtime(true);
                } elseif ($result && in_array($action, ['create', 'update'], true)) {
                    // Éxito sin timings.api_ms: contar como envío real a Yuju.
                    $madeApiCall = true;
                    $lastApiAt = microtime(true);
                } elseif (!$result && !empty($itemDetail['timings']['api_ms']) && (float) $itemDetail['timings']['api_ms'] > 0) {
                    $madeApiCall = true;
                    $lastApiAt = microtime(true);
                }
            }

            if ($madeApiCall) {
                ++$stats['api_calls'];
                ++$stats['by_action'][$action]['api_calls'];
            }

            if ($result) {
                $this->updateQueueStatus($item['id'], 'completed');
                $stats['success']++;
                $stats['by_action'][$action]['success']++;
                $itemDetail['ok'] = true;

                $this->logger->info('Cola: Producto procesado exitosamente', [
                    'queue_id' => $item['id'],
                    'product_id' => $item['prestashop_product_id'],
                    'action' => $action,
                    'duration_s' => round($sync_duration, 3),
                    'skipped_no_diff' => $itemDetail['skipped_no_diff'],
                    'api_action' => $itemDetail['api_action'],
                    'timings' => $itemDetail['timings'],
                ]);
            } else {
                $this->handleQueueError($item['id'], $itemDetail['error'] ?: 'Error al procesar producto');
                $stats['failed']++;
                $stats['by_action'][$action]['failed']++;
            }
            $stats['items'][] = $itemDetail;
        } catch (Exception $e) {
            $itemDetail['error'] = $e->getMessage();
            $stats['items'][] = $itemDetail;
            $this->handleQueueError($item['id'], $e->getMessage());
            $stats['failed']++;
            $stats['by_action'][$action]['failed']++;

            $this->logger->error('Cola: Error procesando producto', [
                'queue_id' => $item['id'],
                'product_id' => $item['prestashop_product_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Espera el gap mínimo entre llamadas HTTP a Yuju.
     *
     * @param float|null $lastApiAt
     * @param int $apiGapUs
     *
     * @return void
     */
    private function waitForApiGap(&$lastApiAt, $apiGapUs)
    {
        if ($lastApiAt === null) {
            return;
        }
        $elapsedUs = (int) ((microtime(true) - $lastApiAt) * 1000000);
        if ($elapsedUs < $apiGapUs) {
            usleep($apiGapUs - $elapsedUs);
        }
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
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" AND action = "create" THEN 1 ELSE 0 END) as pending_create,
                SUM(CASE WHEN status = "pending" AND action = "update" THEN 1 ELSE 0 END) as pending_update,
                SUM(CASE WHEN status = "pending" AND action = "delete" THEN 1 ELSE 0 END) as pending_delete
            FROM ' . _DB_PREFIX_ . 'yuju_sync_queue
        ');

        if (!is_array($stats)) {
            $stats = [];
        }
        foreach (['total', 'pending', 'processing', 'completed', 'failed', 'pending_create', 'pending_update', 'pending_delete'] as $key) {
            $stats[$key] = isset($stats[$key]) ? (int) $stats[$key] : 0;
        }

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
