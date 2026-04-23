<?php
/**
 * 2024 Yuju Integration - Webhook Storage Manager
 *
 * Gestiona el almacenamiento de webhooks recibidos en archivos JSON
 * sin modificar la base de datos.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class YujuWebhookStorage
{
    private $cache_dir;
    private $webhooks_file;
    private $max_webhooks = 100; // Máximo número de webhooks a guardar

    public function __construct()
    {
        $this->cache_dir = dirname(__FILE__, 2) . '/cache/';
        $this->webhooks_file = $this->cache_dir . 'yuju_webhooks_received.json';
        
        // Crear directorio cache si no existe
        if (!is_dir($this->cache_dir)) {
            if (!mkdir($this->cache_dir, 0755, true)) {
                error_log('YujuWebhookStorage: Failed to create cache directory: ' . $this->cache_dir);
            }
        }
        
        // Crear archivo si no existe
        if (!file_exists($this->webhooks_file)) {
            $result = @file_put_contents($this->webhooks_file, json_encode([], JSON_PRETTY_PRINT));
            if ($result === false) {
                error_log('YujuWebhookStorage: Failed to create webhooks file: ' . $this->webhooks_file);
                error_log('YujuWebhookStorage: Cache dir exists: ' . (is_dir($this->cache_dir) ? 'YES' : 'NO'));
                error_log('YujuWebhookStorage: Cache dir writable: ' . (is_writable($this->cache_dir) ? 'YES' : 'NO'));
            } else {
                error_log('YujuWebhookStorage: Created webhooks file successfully: ' . $this->webhooks_file);
            }
        }
    }

    /**
     * Guarda un webhook recibido.
     * 
     * @param array $headers Headers del webhook
     * @param mixed $payload Payload del webhook
     * @return bool Éxito de la operación
     */
    public function saveWebhook($headers, $payload = null)
    {
        try {
            $webhooks = $this->getAllWebhooks();
            
            // Extraer información clave del webhook
            $topic = $headers['x-yuju-topic'] ?? 'unknown';
            $resource_id = $headers['x-yuju-resource'] ?? null;
            $webhook_id = $headers['x-yuju-id'] ?? null;
            
            // Buscar si ya existe este webhook (mismo topic + resource_id)
            $existing_index = null;
            if ($resource_id && $topic !== 'unknown') {
                foreach ($webhooks as $index => $wh) {
                    if ($wh['topic'] === $topic && $wh['resource_id'] === $resource_id) {
                        $existing_index = $index;
                        break;
                    }
                }
            }
            
            if ($existing_index !== null) {
                // Webhook duplicado encontrado - incrementar contador de intentos
                $webhooks[$existing_index]['attempts']++;
                $webhooks[$existing_index]['last_attempt_at'] = date('Y-m-d H:i:s');
                $webhooks[$existing_index]['last_attempt_timestamp'] = time();
                
                // Actualizar el webhook_id del último intento
                if ($webhook_id) {
                    $webhooks[$existing_index]['last_webhook_id'] = $webhook_id;
                }
                
                // Actualizar el payload con el último recibido
                $webhooks[$existing_index]['payload'] = $payload;
                $webhooks[$existing_index]['headers'] = $headers;
                
                // Guardar historial de intentos (opcional, hasta 10 últimos)
                if (!isset($webhooks[$existing_index]['attempt_history'])) {
                    $webhooks[$existing_index]['attempt_history'] = [];
                }
                
                $webhooks[$existing_index]['attempt_history'][] = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'webhook_id' => $webhook_id,
                    'yuju_attempts' => $headers['x-yuju-attempts'] ?? 1,
                ];
                
                // Mantener solo últimos 10 intentos en historial
                if (count($webhooks[$existing_index]['attempt_history']) > 10) {
                    $webhooks[$existing_index]['attempt_history'] = array_slice(
                        $webhooks[$existing_index]['attempt_history'], 
                        -10
                    );
                }
                
                // Mover el webhook al inicio del array (más reciente primero)
                $updated_webhook = $webhooks[$existing_index];
                unset($webhooks[$existing_index]);
                $webhooks = array_values($webhooks); // Reindexar
                array_unshift($webhooks, $updated_webhook);
                
            } else {
                // Webhook nuevo - crear entrada
                $webhook_data = [
                    'id' => uniqid('wh_', true),
                    'received_at' => date('Y-m-d H:i:s'),
                    'timestamp' => time(),
                    'topic' => $topic,
                    'resource_id' => $resource_id,
                    'webhook_id' => $webhook_id,
                    'id_account' => $headers['x-yuju-id-account'] ?? null,
                    'id_shop' => $headers['x-yuju-id-shop'] ?? null,
                    'id_channel' => $headers['x-yuju-id-channel'] ?? null,
                    'attempts' => 1,
                    'last_attempt_at' => date('Y-m-d H:i:s'),
                    'last_attempt_timestamp' => time(),
                    'received_timestamp' => $headers['x-yuju-received'] ?? null,
                    'send_timestamp' => $headers['x-yuju-send'] ?? null,
                    'sku' => $headers['x-yuju-sku'] ?? null,
                    'sku_simple' => $headers['x-yuju-sku-simple'] ?? null,
                    'id_parent' => $headers['x-yuju-id-parent'] ?? null,
                    'headers' => $headers,
                    'payload' => $payload,
                    'attempt_history' => [
                        [
                            'timestamp' => date('Y-m-d H:i:s'),
                            'webhook_id' => $webhook_id,
                            'yuju_attempts' => $headers['x-yuju-attempts'] ?? 1,
                        ]
                    ]
                ];
                
                // Agregar al inicio del array
                array_unshift($webhooks, $webhook_data);
            }
            
            // Mantener solo los últimos N webhooks
            if (count($webhooks) > $this->max_webhooks) {
                $webhooks = array_slice($webhooks, 0, $this->max_webhooks);
            }
            
            // Guardar al archivo
            return file_put_contents(
                $this->webhooks_file, 
                json_encode($webhooks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) !== false;
            
        } catch (Exception $e) {
            error_log('Error saving webhook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todos los webhooks guardados.
     * 
     * @return array Lista de webhooks
     */
    public function getAllWebhooks()
    {
        if (!file_exists($this->webhooks_file)) {
            return [];
        }
        
        $content = file_get_contents($this->webhooks_file);
        $webhooks = json_decode($content, true);
        
        return is_array($webhooks) ? $webhooks : [];
    }

    /**
     * Obtiene los últimos N webhooks.
     * 
     * @param int $limit Número de webhooks a obtener
     * @return array Lista de webhooks
     */
    public function getRecentWebhooks($limit = 20)
    {
        $webhooks = $this->getAllWebhooks();
        return array_slice($webhooks, 0, $limit);
    }

    /**
     * Obtiene webhooks por topic.
     * 
     * @param string $topic Topic a filtrar
     * @param int $limit Número de webhooks a obtener
     * @return array Lista de webhooks filtrados
     */
    public function getWebhooksByTopic($topic, $limit = 20)
    {
        $webhooks = $this->getAllWebhooks();
        $filtered = array_filter($webhooks, function($wh) use ($topic) {
            return isset($wh['topic']) && $wh['topic'] === $topic;
        });
        
        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * Obtiene estadísticas de webhooks.
     * 
     * @return array Estadísticas
     */
    public function getStats()
    {
        $webhooks = $this->getAllWebhooks();
        $total = count($webhooks);
        
        // Agrupar por topic
        $by_topic = [];
        $last_24h = 0;
        $now = time();
        
        foreach ($webhooks as $wh) {
            $topic = $wh['topic'] ?? 'unknown';
            
            if (!isset($by_topic[$topic])) {
                $by_topic[$topic] = 0;
            }
            $by_topic[$topic]++;
            
            // Contar últimas 24 horas
            if (isset($wh['timestamp']) && ($now - $wh['timestamp']) <= 86400) {
                $last_24h++;
            }
        }
        
        return [
            'total' => $total,
            'last_24h' => $last_24h,
            'by_topic' => $by_topic,
            'last_received' => !empty($webhooks) ? $webhooks[0]['received_at'] : null
        ];
    }

    /**
     * Actualiza un webhook con el resultado de procesamiento.
     * 
     * @param string $topic Topic del webhook
     * @param string $resource_id ID del recurso
     * @param array $processing_result Resultado del procesamiento
     * @return bool Éxito de la operación
     */
    public function updateWebhookProcessing($topic, $resource_id, $processing_result)
    {
        try {
            $webhooks = $this->getAllWebhooks();
            
            // Buscar el webhook por topic y resource_id
            $updated = false;
            foreach ($webhooks as $index => $wh) {
                if ($wh['topic'] === $topic && $wh['resource_id'] === $resource_id) {
                    // Actualizar con información de procesamiento
                    $webhooks[$index]['processing_status'] = $processing_result['success'] ? 'completed' : 'failed';
                    $webhooks[$index]['processing_result'] = $processing_result;
                    $webhooks[$index]['processed_at'] = date('Y-m-d H:i:s');
                    $webhooks[$index]['processed_timestamp'] = time();
                    
                    // Si es una orden y se creó exitosamente, guardar el ID de PrestaShop
                    if (isset($processing_result['prestashop_order_id'])) {
                        $webhooks[$index]['prestashop_order_id'] = $processing_result['prestashop_order_id'];
                        $webhooks[$index]['order_created'] = true;
                    }
                    
                    // Guardar detalles de error si existen
                    if (!$processing_result['success']) {
                        $webhooks[$index]['error_message'] = $processing_result['message'] ?? 'Unknown error';
                        $webhooks[$index]['error_details'] = $processing_result['details'] ?? null;
                        if (isset($processing_result['error'])) {
                            $webhooks[$index]['error'] = $processing_result['error'];
                        }
                    }
                    
                    // Guardar detalles de creación (customer, address, cart, order)
                    if (isset($processing_result['details'])) {
                        $webhooks[$index]['creation_details'] = [
                            'customer' => [
                                'id' => $processing_result['details']['customer_id'] ?? null,
                                'existed' => $processing_result['details']['customer_existed'] ?? false,
                                'error' => $processing_result['details']['customer_error'] ?? null,
                            ],
                            'address' => [
                                'id' => $processing_result['details']['shipping_address_id'] ?? null,
                                'existed' => $processing_result['details']['address_existed'] ?? false,
                                'error' => $processing_result['details']['address_error'] ?? null,
                            ],
                            'cart' => [
                                'id' => $processing_result['details']['cart_id'] ?? null,
                                'existed' => $processing_result['details']['cart_existed'] ?? false,
                                'products_added' => $processing_result['details']['cart_products_added'] ?? 0,
                                'products_failed' => $processing_result['details']['cart_products_failed'] ?? [],
                                'error' => $processing_result['details']['cart_error'] ?? null,
                            ],
                            'order' => [
                                'id' => $processing_result['details']['order_id'] ?? null,
                                'error' => $processing_result['details']['order_error'] ?? null,
                            ],
                            'mapping' => [
                                'id' => $processing_result['details']['mapping_id'] ?? null,
                                'error' => $processing_result['details']['mapping_error'] ?? null,
                            ],
                        ];
                    }
                    
                    $updated = true;
                    break;
                }
            }
            
            if ($updated) {
                // Guardar al archivo
                return file_put_contents(
                    $this->webhooks_file, 
                    json_encode($webhooks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ) !== false;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('Error updating webhook processing: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpia webhooks antiguos.
     * 
     * @param int $days Días de antigüedad
     * @return int Número de webhooks eliminados
     */
    public function cleanOldWebhooks($days = 30)
    {
        $webhooks = $this->getAllWebhooks();
        $cutoff = time() - ($days * 86400);
        
        $filtered = array_filter($webhooks, function($wh) use ($cutoff) {
            return isset($wh['timestamp']) && $wh['timestamp'] > $cutoff;
        });
        
        $removed = count($webhooks) - count($filtered);
        
        if ($removed > 0) {
            file_put_contents(
                $this->webhooks_file, 
                json_encode(array_values($filtered), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
        
        return $removed;
    }

    /**
     * Elimina todos los webhooks.
     * 
     * @return bool Éxito de la operación
     */
    public function clearAll()
    {
        return file_put_contents($this->webhooks_file, json_encode([], JSON_PRETTY_PRINT)) !== false;
    }
}
