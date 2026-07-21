<?php
/**
 * Reporte de oferta de productos Yuju (products-offer-report).
 * Contiene SKU, id_product, stock y precio. Límite API: cada 12 horas.
 * Doc: https://api-docs.yuju.io/docs/obtener-oferta-de-productos
 *
 * Se guarda en la misma tabla yuju_product_reports (report_type = offer),
 * en JSONL (1 oferta por línea) para reanálisis y visualizador.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/YujuOAuth.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuProductOfferReport
{
    const REPORT_TYPE = 'offer';
    const API_ENDPOINT = 'products-offer-report';
    /** Intervalo oficial Yuju entre descargas */
    const INTERVAL_SECONDS = 43200;
    const CACHE_LATEST = 'yuju_products.json';

    /** @var YujuLogger */
    protected $logger;

    /** @var string */
    protected $cacheDir;

    /** @var string */
    protected $reportsDir;

    public function __construct()
    {
        $this->logger = new YujuLogger();
        $this->cacheDir = dirname(__FILE__) . '/../cache/';
        $this->reportsDir = $this->cacheDir . 'product_reports/';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
        if (!is_dir($this->reportsDir)) {
            @mkdir($this->reportsDir, 0755, true);
        }
    }

    /**
     * @return bool
     */
    public static function tableExists()
    {
        return class_exists('YujuProductGralReport') && YujuProductGralReport::tableExists();
    }

    /**
     * Cupo: 1 descarga cada 12h (según docs Yuju).
     *
     * @return array
     */
    public static function getQuotaStatus()
    {
        $interval = self::INTERVAL_SECONDS;
        $lastAt = (string) YujuConfig::get('YUJU_OFFER_REPORT_LAST_REQUEST_AT', '');
        $apiUnlockAt = (string) YujuConfig::get('YUJU_OFFER_REPORT_UNLOCK_AT', '');
        $secondsLeft = 0;
        $allowed = true;
        $unlockAt = null;

        // Unlock exacto comunicado por la API Yuju (prioridad sobre el cálculo local)
        if ($apiUnlockAt !== '') {
            $unlockTs = (int) strtotime($apiUnlockAt);
            if ($unlockTs > time()) {
                $secondsLeft = $unlockTs - time();
                $unlockAt = date('Y-m-d H:i:s', $unlockTs);
                $allowed = false;
            } else {
                // Ya pasó: limpiar unlock y permitir
                try {
                    YujuConfig::set('YUJU_OFFER_REPORT_UNLOCK_AT', '', 'string');
                } catch (Exception $e) {
                    // ignore
                }
                $apiUnlockAt = '';
            }
        }

        // Fallo local (CloudFront 403, JSON inválido, etc.) sin unlock API → permite reintento
        if ($allowed && self::lastOfferAttemptFailedWithoutCompleted()) {
            return [
                'allowed' => true,
                'interval_hours' => (int) ($interval / 3600),
                'seconds_left' => 0,
                'remaining_human' => '0m',
                'unlock_at' => null,
                'last_request_at' => $lastAt !== '' ? $lastAt : null,
                'retry_after_fail' => true,
                'message' => null,
            ];
        }

        if ($allowed && $lastAt !== '') {
            $elapsed = time() - (int) strtotime($lastAt);
            if ($elapsed < $interval) {
                $allowed = false;
                $secondsLeft = $interval - max(0, $elapsed);
                $unlockAt = date('Y-m-d H:i:s', (int) strtotime($lastAt) + $interval);
            }
        }

        $remainingHuman = self::formatRemainingSeconds($secondsLeft);

        return [
            'allowed' => $allowed,
            'interval_hours' => (int) ($interval / 3600),
            'seconds_left' => (int) $secondsLeft,
            'remaining_human' => $remainingHuman,
            'unlock_at' => $unlockAt,
            'last_request_at' => $lastAt !== '' ? $lastAt : null,
            'api_unlock_at' => $apiUnlockAt !== '' ? $apiUnlockAt : null,
            'retry_after_fail' => false,
            'message' => $allowed
                ? null
                : 'products-offer-report disponible cada 12h. Faltan '
                    . $remainingHuman
                    . ($unlockAt ? ' (hasta ' . $unlockAt . ')' : ''),
        ];
    }

    /**
     * @param int $seconds
     *
     * @return string
     */
    public static function formatRemainingSeconds($seconds)
    {
        $seconds = max(0, (int) $seconds);
        if ($seconds <= 0) {
            return '0m';
        }

        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $secs = (int) ($seconds % 60);

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        if ($minutes > 0) {
            return $secs > 0 ? ($minutes . 'm ' . $secs . 's') : ($minutes . 'm');
        }

        return $secs . 's';
    }

    /**
     * @param int $limit
     *
     * @return array
     */
    public static function getHistory($limit = 50)
    {
        if (!self::tableExists()) {
            return [];
        }

        try {
            $rows = Db::getInstance()->executeS(
                'SELECT id, id_task, report_type, `trigger`, status, products_count, file_size,
                        requested_at, completed_at, utc_day, error_message, created_at
                 FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
                 WHERE report_type = \'' . pSQL(self::REPORT_TYPE) . '\'
                 ORDER BY id DESC
                 LIMIT ' . (int) $limit
            );
        } catch (Exception $e) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * Histórico unificado offer + gral para monitoreo / reanalizar.
     *
     * @param int $limit
     *
     * @return array
     */
    public static function getUnifiedHistory($limit = 50)
    {
        if (!self::tableExists()) {
            return [];
        }

        try {
            $rows = Db::getInstance()->executeS(
                'SELECT id, id_task, report_type, `trigger`, status, products_count, file_size,
                        requested_at, completed_at, utc_day, error_message, created_at
                 FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
                 ORDER BY id DESC
                 LIMIT ' . (int) $limit
            );
        } catch (Exception $e) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param int $limit
     *
     * @return array
     */
    public static function getMonitoringStats($limit = 40)
    {
        $history = self::getHistory($limit);
        $totals = [
            'reports_count' => count($history),
            'completed' => 0,
            'failed' => 0,
            'processing' => 0,
            'sum_products' => 0,
        ];
        foreach ($history as $r) {
            if ($r['status'] === 'completed') {
                ++$totals['completed'];
                $totals['sum_products'] += (int) $r['products_count'];
            } elseif (in_array($r['status'], ['failed', 'rejected', 'limit_reached'], true)) {
                ++$totals['failed'];
            } elseif (in_array($r['status'], ['pending', 'processing'], true)) {
                ++$totals['processing'];
            }
        }

        return [
            'history' => $history,
            'totals' => $totals,
            'quota' => self::getQuotaStatus(),
        ];
    }

    /**
     * @param int $id
     *
     * @return array|null
     */
    public function getReport($id)
    {
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_product_reports` WHERE id = ' . (int) $id
        );

        return is_array($row) ? $row : null;
    }

    /**
     * GET products-offer-report → descarga → guarda fila completed en histórico.
     *
     * @param string $trigger
     * @param bool $force Ignora cupo local (la API puede seguir bloqueando)
     *
     * @return array
     */
    public function requestAndDownload($trigger = 'manual', $force = false)
    {
        $trigger = in_array($trigger, ['manual', 'scheduled', 'webhook'], true) ? $trigger : 'manual';
        $quota = self::getQuotaStatus();
        $progress = $this->emptyProgressSteps();
        $canRetryFailed = self::lastOfferAttemptFailedWithoutCompleted();

        if (!$force && !$quota['allowed'] && !$canRetryFailed) {
            $last = $this->getLatestCompletedOffer();
            if ($last) {
                return [
                    'success' => true,
                    'reused' => true,
                    'message' => $quota['message'] . ' Reutilizando reporte #' . (int) $last['id'] . '.',
                    'report_id' => (int) $last['id'],
                    'id_task' => $last['id_task'],
                    'status' => 'completed',
                    'phase' => 'compare',
                    'quota' => $quota,
                    'report' => $last,
                    'progress' => [
                        'api_request' => ['ok' => true, 'label' => 'Cupo local cerrado', 'detail' => 'Reutilizando histórico'],
                        'got_url' => ['ok' => true, 'label' => 'URL firmada', 'detail' => 'No requerida (reuso)'],
                        'json_download' => ['ok' => true, 'label' => 'JSON', 'detail' => 'Archivo local #' . (int) $last['id']],
                        'file_save' => ['ok' => true, 'label' => 'Archivo', 'detail' => basename((string) ($last['file_path'] ?? ''))],
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => $quota['message'],
                'quota' => $quota,
                'progress' => [
                    'api_request' => [
                        'ok' => false,
                        'label' => 'Cupo local / API',
                        'detail' => $quota['message'],
                    ],
                    'got_url' => ['ok' => false, 'label' => 'URL firmada', 'detail' => 'No solicitada'],
                    'json_download' => ['ok' => false, 'label' => 'Descarga JSON', 'detail' => 'No iniciada'],
                    'file_save' => ['ok' => false, 'label' => 'Guardado local', 'detail' => 'Sin archivo'],
                ],
            ];
        }

        $oauth = new YujuOAuth();
        $token = $oauth->getValidAccessToken();
        if (!$token) {
            $progress['api_request'] = ['ok' => false, 'label' => 'Petición API', 'detail' => 'Sin token OAuth válido'];

            return ['success' => false, 'message' => 'No hay token OAuth válido.', 'quota' => $quota, 'progress' => $progress];
        }

        $previousLastRequest = (string) YujuConfig::get('YUJU_OFFER_REPORT_LAST_REQUEST_AT', '');
        $now = date('Y-m-d H:i:s');
        $syntheticTask = 'offer_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 8);

        $insert = [
            'id_task' => pSQL($syntheticTask),
            'report_type' => pSQL(self::REPORT_TYPE),
            'trigger' => pSQL($trigger),
            'status' => 'processing',
            'products_count' => 0,
            'file_path' => '',
            'file_size' => 0,
            'download_url' => '',
            'error_message' => '',
            'meta_json' => pSQL(json_encode(['endpoint' => self::API_ENDPOINT], JSON_UNESCAPED_UNICODE)),
            'requested_at' => pSQL($now),
            'completed_at' => null,
            'utc_day' => pSQL(gmdate('Y-m-d')),
            'created_at' => pSQL($now),
            'updated_at' => pSQL($now),
        ];

        if (!Db::getInstance()->insert('yuju_product_reports', $insert)) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el registro del reporte de ofertas.',
                'id_task' => $syntheticTask,
                'progress' => $progress,
            ];
        }

        $reportId = (int) Db::getInstance()->Insert_ID();

        $fetched = $this->fetchOfferPayload($token, $progress);
        if (empty($fetched['success'])) {
            // Fallback: cache local de sync_products si CloudFront falla
            $fromCache = $this->tryLoadOffersFromLocalCache();
            if (!empty($fromCache['success'])) {
                $progress['json_download'] = [
                    'ok' => true,
                    'label' => 'Descarga JSON',
                    'detail' => 'CloudFront falló; usando cache local yuju_products.json'
                        . (isset($fromCache['cache_age_sec'])
                            ? (' (edad ' . round($fromCache['cache_age_sec'] / 3600, 1) . 'h)')
                            : ''),
                ];

                // Cupo API ya se usó al pedir la URL aunque bajemos del cache
                if (!empty($fetched['got_url']) || !empty($fetched['api_quota_consumed'])) {
                    YujuConfig::set('YUJU_OFFER_REPORT_LAST_REQUEST_AT', $now, 'string');
                    YujuConfig::set(
                        'YUJU_OFFER_REPORT_UNLOCK_AT',
                        date('Y-m-d H:i:s', time() + self::INTERVAL_SECONDS),
                        'string'
                    );
                }

                return $this->persistOfferRows(
                    $reportId,
                    $syntheticTask,
                    $fromCache['offers'],
                    $fromCache['url'] ?? '',
                    true,
                    $progress
                );
            }

            $this->updateReport($reportId, [
                'status' => !empty($fetched['blocked']) ? 'limit_reached' : 'failed',
                'error_message' => $fetched['message'] ?? 'Error al descargar offer-report',
                'completed_at' => date('Y-m-d H:i:s'),
                'download_url' => $fetched['url'] ?? '',
                'meta_json' => json_encode([
                    'endpoint' => self::API_ENDPOINT,
                    'progress' => $progress,
                    'blocked' => !empty($fetched['blocked']),
                    'api_quota_consumed' => !empty($fetched['api_quota_consumed']) || !empty($fetched['got_url']),
                    'unlock_at' => $fetched['unlock_at'] ?? null,
                    'api_message' => $fetched['api_message'] ?? null,
                    'http_code' => $fetched['http_code'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if (!empty($fetched['blocked']) && !empty($fetched['unlock_at'])) {
                $this->syncApiUnlockAt($fetched['unlock_at']);
            } elseif (!empty($fetched['api_quota_consumed']) || !empty($fetched['got_url'])) {
                // La 1ª petición SÍ consumió cupo en Yuju aunque CloudFront fallara:
                // alinear espera local a 12h para no volver a pegarle a la API de inmediato.
                YujuConfig::set('YUJU_OFFER_REPORT_LAST_REQUEST_AT', $now, 'string');
                $estimatedUnlock = date('Y-m-d H:i:s', time() + self::INTERVAL_SECONDS);
                YujuConfig::set('YUJU_OFFER_REPORT_UNLOCK_AT', $estimatedUnlock, 'string');
                if (empty($fetched['unlock_at'])) {
                    $fetched['unlock_at'] = $estimatedUnlock;
                }
            } elseif (empty($fetched['blocked'])) {
                // Fallo antes de obtener URL (token, HTTP, etc.) → no quemar cupo
                YujuConfig::set('YUJU_OFFER_REPORT_LAST_REQUEST_AT', $previousLastRequest, 'string');
            } else {
                YujuConfig::set('YUJU_OFFER_REPORT_LAST_REQUEST_AT', $now, 'string');
            }

            return [
                'success' => false,
                'message' => $fetched['message'] ?? 'Error offer-report',
                'report_id' => $reportId,
                'id_task' => $syntheticTask,
                'quota' => self::getQuotaStatus(),
                'blocked' => !empty($fetched['blocked']) || !empty($fetched['api_quota_consumed']) || !empty($fetched['got_url']),
                'unlock_at' => $fetched['unlock_at'] ?? null,
                'api_message' => $fetched['api_message'] ?? null,
                'progress' => $progress,
                'got_url' => !empty($fetched['got_url']),
                'json_downloaded' => false,
            ];
        }

        // Cupo local + limpiar unlock API cuando ya hay JSON descargado con éxito
        YujuConfig::set('YUJU_OFFER_REPORT_LAST_REQUEST_AT', $now, 'string');
        YujuConfig::set('YUJU_OFFER_REPORT_UNLOCK_AT', '', 'string');

        return $this->persistOfferRows(
            $reportId,
            $syntheticTask,
            $fetched['offers'],
            $fetched['url'] ?? '',
            false,
            $progress
        );
    }

    /**
     * Último intento offer falló (no limit API vigente) → permitir reintento.
     * limit_reached solo reintenta si el unlock de la API ya pasó.
     *
     * @return bool
     */
    protected static function lastOfferAttemptFailedWithoutCompleted()
    {
        if (!self::tableExists()) {
            return false;
        }
        try {
            $last = Db::getInstance()->getRow(
                'SELECT status, requested_at, error_message FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
                 WHERE report_type = \'' . pSQL(self::REPORT_TYPE) . '\'
                 ORDER BY id DESC'
            );
        } catch (Exception $e) {
            return false;
        }
        if (!$last) {
            return false;
        }

        if ($last['status'] === 'failed') {
            return true;
        }

        if ($last['status'] === 'limit_reached') {
            $apiUnlockAt = (string) YujuConfig::get('YUJU_OFFER_REPORT_UNLOCK_AT', '');
            if ($apiUnlockAt !== '' && (int) strtotime($apiUnlockAt) > time()) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Persiste el desbloqueo declarado por la API Yuju.
     *
     * @param string|null $unlockAt
     */
    protected function syncApiUnlockAt($unlockAt)
    {
        if (!$unlockAt) {
            return;
        }
        $ts = (int) strtotime($unlockAt);
        if ($ts <= 0) {
            return;
        }
        $normalized = date('Y-m-d H:i:s', $ts);
        YujuConfig::set('YUJU_OFFER_REPORT_UNLOCK_AT', $normalized, 'string');
        // Alinear LAST_REQUEST_AT para que el cálculo local coincida con el unlock de API
        $alignedLast = date('Y-m-d H:i:s', $ts - self::INTERVAL_SECONDS);
        YujuConfig::set('YUJU_OFFER_REPORT_LAST_REQUEST_AT', $alignedLast, 'string');
    }

    /**
     * @return array
     */
    protected function emptyProgressSteps()
    {
        return [
            'api_request' => ['ok' => null, 'label' => 'Petición a products-offer-report', 'detail' => 'Pendiente'],
            'got_url' => ['ok' => null, 'label' => 'URL firmada (CloudFront)', 'detail' => 'Pendiente'],
            'json_download' => ['ok' => null, 'label' => 'Descarga del JSON', 'detail' => 'Pendiente'],
            'file_save' => ['ok' => null, 'label' => 'Guardado local JSONL', 'detail' => 'Pendiente'],
        ];
    }

    /**
     * @return array|null
     */
    protected function getLatestCompletedOffer()
    {
        $last = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
             WHERE report_type = \'' . pSQL(self::REPORT_TYPE) . '\' AND status = \'completed\'
             ORDER BY id DESC'
        );
        if ($last && !empty($last['file_path']) && is_readable($last['file_path'])) {
            return $last;
        }

        return null;
    }

    /**
     * Cache compartido con sync_products (yuju_products.json).
     * Acepta cache aunque esté “vencido” si CloudFront acaba de fallar.
     *
     * @param bool $allowStale
     *
     * @return array
     */
    protected function tryLoadOffersFromLocalCache($allowStale = true)
    {
        $file = $this->cacheDir . self::CACHE_LATEST;
        if (!is_readable($file)) {
            return ['success' => false];
        }
        $age = time() - (int) @filemtime($file);
        // Si no se permite stale y tiene > 12h, no usar
        if (!$allowStale && $age > self::INTERVAL_SECONDS) {
            return ['success' => false, 'message' => 'Cache local demasiado antiguo'];
        }

        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        $offers = $this->normalizeOffersList($decoded);
        if ($offers === null || empty($offers)) {
            return ['success' => false];
        }

        $this->logger->warning('offer-report CloudFront falló; usando cache local ' . self::CACHE_LATEST, [
            'count' => count($offers),
            'age_sec' => $age,
        ]);

        return [
            'success' => true,
            'offers' => $offers,
            'url' => 'local://' . self::CACHE_LATEST,
            'cache_age_sec' => $age,
        ];
    }

    /**
     * @param int $reportId
     * @param string $syntheticTask
     * @param array $offersRaw
     * @param string $downloadUrl
     * @param bool $fromCache
     * @param array|null $progress
     *
     * @return array
     */
    protected function persistOfferRows($reportId, $syntheticTask, $offersRaw, $downloadUrl, $fromCache = false, $progress = null)
    {
        if (!is_array($progress)) {
            $progress = $this->emptyProgressSteps();
        }

        $offers = $this->normalizeOffersList($offersRaw);
        if ($offers === null || empty($offers)) {
            $progress['file_save'] = ['ok' => false, 'label' => 'Guardado local', 'detail' => 'JSON de ofertas vacío o inválido'];
            $this->updateReport($reportId, [
                'status' => 'failed',
                'error_message' => 'JSON de ofertas vacío o inválido',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => false,
                'message' => 'JSON de ofertas vacío o inválido.',
                'report_id' => $reportId,
                'id_task' => $syntheticTask,
                'progress' => $progress,
                'json_downloaded' => true,
            ];
        }

        $jsonl = '';
        foreach ($offers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $jsonl .= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        $stamp = date('Ymd_His');
        $fileName = 'offer_' . $stamp . '_' . substr(md5($syntheticTask), 0, 6) . '.jsonl';
        $filePath = $this->reportsDir . $fileName;
        $bytes = file_put_contents($filePath, $jsonl);
        if ($bytes === false) {
            $progress['file_save'] = ['ok' => false, 'label' => 'Guardado local', 'detail' => 'No se pudo escribir en disco'];
            $this->updateReport($reportId, [
                'status' => 'failed',
                'error_message' => 'No se pudo guardar el JSONL localmente',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo guardar el JSONL en disco.',
                'report_id' => $reportId,
                'id_task' => $syntheticTask,
                'progress' => $progress,
            ];
        }

        if (!$fromCache) {
            @file_put_contents($this->cacheDir . self::CACHE_LATEST, json_encode($offers, JSON_UNESCAPED_UNICODE));
        }

        $count = count($offers);
        $doneAt = date('Y-m-d H:i:s');
        $progress['file_save'] = [
            'ok' => true,
            'label' => 'Guardado local JSONL',
            'detail' => $fileName . ' · ' . $count . ' filas · ' . (int) $bytes . ' bytes'
                . ($fromCache ? ' (desde cache local)' : ''),
        ];

        $this->updateReport($reportId, [
            'status' => 'completed',
            'products_count' => $count,
            'file_path' => $filePath,
            'file_size' => (int) $bytes,
            'download_url' => $downloadUrl,
            'error_message' => '',
            'completed_at' => $doneAt,
            'meta_json' => json_encode([
                'endpoint' => self::API_ENDPOINT,
                'lines' => $count,
                'saved_as' => $fileName,
                'from_cache' => $fromCache ? 1 : 0,
                'progress' => $progress,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        if (!$fromCache) {
            YujuConfig::set('YUJU_OFFER_REPORT_LAST_COMPLETED_AT', $doneAt, 'string');
            YujuConfig::set('YUJU_OFFER_REPORT_LAST_REQUEST_AT', $doneAt, 'string');
            YujuConfig::set('YUJU_OFFER_REPORT_UNLOCK_AT', '', 'string');
        }

        $this->logger->info('products-offer-report guardado', [
            'report_id' => $reportId,
            'products_count' => $count,
            'from_cache' => $fromCache,
        ]);

        return [
            'success' => true,
            'message' => $fromCache
                ? 'CloudFront falló (403); se usó cache local (' . $count . ' SKUs).'
                : 'Reporte de ofertas guardado (' . $count . ' SKUs).',
            'report_id' => $reportId,
            'id_task' => $syntheticTask,
            'status' => 'completed',
            'phase' => 'compare',
            'products_count' => $count,
            'from_cache' => $fromCache,
            'quota' => self::getQuotaStatus(),
            'report' => $this->getReport($reportId),
            'finished' => true,
            'progress' => $progress,
            'got_url' => true,
            'json_downloaded' => true,
        ];
    }

    /**
     * @param string $token
     * @param array $progress by-ref steps
     *
     * @return array
     */
    protected function fetchOfferPayload($token, array &$progress)
    {
        $requestUrl = 'https://api.tp.yuju.io/' . self::API_ENDPOINT;
        $urlResult = $this->requestOfferReportUrl($requestUrl, $token);

        if (empty($urlResult['success'])) {
            $progress['api_request'] = [
                'ok' => false,
                'label' => 'Petición a products-offer-report',
                'detail' => $urlResult['message'] ?? ('HTTP ' . (int) ($urlResult['http_code'] ?? 0)),
            ];
            $progress['got_url'] = [
                'ok' => false,
                'label' => 'URL firmada (CloudFront)',
                'detail' => !empty($urlResult['blocked'])
                    ? 'API bloqueada (límite 12h)'
                    : 'Sin URL en respuesta',
            ];
            $progress['json_download'] = [
                'ok' => false,
                'label' => 'Descarga del JSON',
                'detail' => 'No iniciada (sin URL)',
            ];

            return $urlResult;
        }

        $progress['api_request'] = [
            'ok' => true,
            'label' => 'Petición a products-offer-report',
            'detail' => 'HTTP 200 · URL recibida',
        ];
        $progress['got_url'] = [
            'ok' => true,
            'label' => 'URL firmada (CloudFront)',
            'detail' => 'Obtenida (intento 1)',
        ];

        $downloadUrl = $urlResult['url'];
        $gotUrlOnce = true;
        $download = $this->downloadOfferJson($downloadUrl);

        // CloudFront a veces da 403 en el 1.er intento (URL firmada recién emitida).
        // Reintentar LA MISMA URL — NO pedir otra a la API (eso gasta/bloquea el cupo 12h).
        $sameUrlAttempt = 1;
        while ((int) ($download['http_code'] ?? 0) === 403 && $sameUrlAttempt < 4) {
            ++$sameUrlAttempt;
            $this->logger->warning('CloudFront 403 en offer-report; reintento misma URL #' . $sameUrlAttempt, [
                'url_preview' => substr((string) $downloadUrl, 0, 120),
            ]);
            sleep(2);
            $download = $this->downloadOfferJson($downloadUrl);
        }

        $httpDl = (int) ($download['http_code'] ?? 0);
        if ($httpDl !== 200 || empty($download['content'])) {
            $progress['json_download'] = [
                'ok' => false,
                'label' => 'Descarga del JSON',
                'detail' => 'HTTP ' . $httpDl
                    . ' tras ' . $sameUrlAttempt . ' intento(s) sobre la misma URL firmada'
                    . (!empty($download['error']) ? (' · ' . $download['error']) : '')
                    . ($httpDl === 403
                        ? ' · CloudFront rechazó la URL. No se pidió otra URL para no quemar el cupo de 12h.'
                        : ''),
            ];

            return [
                'success' => false,
                'message' => 'La API entregó URL (cupo de 12h usado) pero no se pudo descargar el JSON (HTTP '
                    . $httpDl . ').'
                    . ($httpDl === 403
                        ? ' CloudFront 403: se reintentó la misma URL sin pedir otra (pedir otra bloquea la API).'
                        : '')
                    . ' Se intentará usar cache local si existe. Si no hay cache, ejecute sync de productos cuando el cupo se libere.',
                'url' => $downloadUrl,
                'got_url' => $gotUrlOnce,
                'cloudfront_403' => ($httpDl === 403),
                'http_code' => $httpDl,
                // No marcar blocked de API: el cupo sí se usó al pedir la 1ª URL
                'api_quota_consumed' => true,
            ];
        }

        $offers = json_decode($download['content'], true);
        if (!is_array($offers)) {
            $progress['json_download'] = [
                'ok' => false,
                'label' => 'Descarga del JSON',
                'detail' => 'HTTP 200 pero JSON inválido',
            ];

            return [
                'success' => false,
                'message' => 'JSON de ofertas inválido.',
                'url' => $downloadUrl,
                'got_url' => true,
                'api_quota_consumed' => true,
            ];
        }

        $progress['json_download'] = [
            'ok' => true,
            'label' => 'Descarga del JSON',
            'detail' => 'HTTP 200 · ' . strlen((string) $download['content']) . ' bytes'
                . ($sameUrlAttempt > 1 ? (' (ok en intento ' . $sameUrlAttempt . ')') : ''),
        ];

        return ['success' => true, 'offers' => $offers, 'url' => $downloadUrl, 'got_url' => true];
    }

    /**
     * @param string $requestUrl
     * @param string $token
     *
     * @return array
     */
    protected function requestOfferReportUrl($requestUrl, $token)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $apiResponse = json_decode($response, true);

        if ($httpCode === 200 && isset($apiResponse['message'])
            && strpos($apiResponse['message'], 'El reporte se puede procesar cada') !== false
        ) {
            $apiMsg = (string) $apiResponse['message'];
            $unlockAt = null;
            $remainingHuman = null;
            if (preg_match('/El desbloqueo termina\s+([0-9\-:\.\s]+)/u', $apiMsg, $m)) {
                $unlockRaw = trim($m[1]);
                $unlockTs = strtotime($unlockRaw);
                if ($unlockTs !== false) {
                    $unlockAt = date('Y-m-d H:i:s', $unlockTs);
                    $remainingHuman = self::formatRemainingSeconds(max(0, $unlockTs - time()));
                }
            }

            $msg = 'API bloqueada: el reporte de ofertas aún no está disponible.';
            if ($remainingHuman) {
                $msg .= ' Faltan ' . $remainingHuman;
                if ($unlockAt) {
                    $msg .= ' (hasta ' . $unlockAt . ')';
                }
            } else {
                $msg .= ' ' . $apiMsg;
            }

            return [
                'success' => false,
                'message' => $msg,
                'blocked' => true,
                'unlock_at' => $unlockAt,
                'remaining_human' => $remainingHuman,
                'api_message' => $apiMsg,
                'http_code' => $httpCode,
            ];
        }

        if ($httpCode !== 200 || empty($apiResponse['url'])) {
            $detail = is_array($apiResponse) ? json_encode($apiResponse, JSON_UNESCAPED_UNICODE) : (string) $response;

            return [
                'success' => false,
                'message' => 'Error al solicitar offer-report (HTTP ' . $httpCode . '): '
                    . ($curlError ?: substr($detail, 0, 180)),
                'http_code' => $httpCode,
                'api_raw' => substr($detail, 0, 400),
            ];
        }

        return ['success' => true, 'url' => $apiResponse['url'], 'http_code' => 200];
    }

    /**
     * Descarga JSON desde URL firmada CloudFront.
     * NO enviar Authorization (rompe la firma). Alineado con sync_products + OfferAuditor.
     *
     * @param string $downloadUrl
     *
     * @return array
     */
    protected function downloadOfferJson($downloadUrl)
    {
        $strategies = [
            // Exacto sync_products.php (sin CURLOPT_ENCODING)
            [
                'headers' => [
                    'Accept: application/json',
                    'User-Agent: PrestaShop-Yuju-Module/1.0',
                ],
                'encoding' => false,
            ],
            // Como YujuOfferAuditor (gzip automático)
            [
                'headers' => [
                    'Accept: application/json',
                    'User-Agent: PrestaShop-Yuju-Module/1.0',
                ],
                'encoding' => true,
            ],
            // Sin Accept estricto
            [
                'headers' => [
                    'User-Agent: PrestaShop-Yuju-Module/1.0',
                ],
                'encoding' => true,
            ],
            // Sin headers custom
            [
                'headers' => [],
                'encoding' => true,
            ],
            [
                'headers' => [],
                'encoding' => false,
            ],
        ];

        $lastCode = 0;
        $lastContent = false;
        $lastError = '';

        foreach ($strategies as $strategy) {
            $ch = curl_init();
            $opts = [
                CURLOPT_URL => $downloadUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ];
            if (!empty($strategy['encoding'])) {
                $opts[CURLOPT_ENCODING] = '';
            }
            if (!empty($strategy['headers'])) {
                $opts[CURLOPT_HTTPHEADER] = $strategy['headers'];
            }
            curl_setopt_array($ch, $opts);
            $content = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            $lastCode = $code;
            $lastContent = $content;
            $lastError = $err;
            if ($code === 200 && $content !== false && $content !== '') {
                return ['http_code' => $code, 'content' => $content, 'error' => ''];
            }
            if ($code !== 403 && $code !== 0) {
                break;
            }
        }

        // Último recurso: file_get_contents
        if ($lastCode === 403 || $lastCode === 0) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 180,
                    'header' => "User-Agent: PrestaShop-Yuju-Module/1.0\r\nAccept: application/json\r\n",
                    'ignore_errors' => true,
                    'follow_location' => 1,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $content = @file_get_contents($downloadUrl, false, $ctx);
            $code = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) {
                        $code = (int) $m[1];
                    }
                }
            }
            if ($code === 200 && $content) {
                return ['http_code' => 200, 'content' => $content, 'error' => ''];
            }
            if ($code > 0) {
                $lastCode = $code;
                $lastContent = $content;
            }
        }

        return [
            'http_code' => $lastCode,
            'content' => $lastContent,
            'error' => $lastError,
        ];
    }

    /**
     * @param array $offers
     *
     * @return array|null
     */
    protected function normalizeOffersList($offers)
    {
        if (!is_array($offers)) {
            return null;
        }
        if (isset($offers['products']) && is_array($offers['products'])) {
            $offers = $offers['products'];
        } elseif (isset($offers['data']) && is_array($offers['data'])) {
            $offers = $offers['data'];
        }
        if (!is_array($offers)) {
            return null;
        }

        return array_values($offers);
    }

    /**
     * @param int $id
     * @param array $fields
     */
    protected function updateReport($id, array $fields)
    {
        $data = ['updated_at' => pSQL(date('Y-m-d H:i:s'))];
        foreach ($fields as $k => $v) {
            if ($v === null) {
                $data[$k] = null;
            } elseif (is_int($v) || is_float($v)) {
                $data[$k] = $v;
            } else {
                $data[$k] = pSQL((string) $v, true);
            }
        }
        Db::getInstance()->update('yuju_product_reports', $data, 'id = ' . (int) $id);
    }
}
