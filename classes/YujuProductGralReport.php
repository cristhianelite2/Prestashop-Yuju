<?php
/**
 * Reporte general de productos Yuju (products-gral-report).
 * Límite API: máximo 2 veces por día (créditos UTC 00:00).
 * Doc: https://api-docs.yuju.io/docs/obtener-informacion-general
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/YujuOAuth.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuProductGralReport
{
    const REPORT_TYPE = 'gral';
    const API_ENDPOINT = 'products-gral-report';
    const API_MAX_PER_DAY = 2;
    const CACHE_LATEST = 'yuju_products_gral.jsonl';

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
     * Día UTC actual (YYYY-MM-DD) usado por el límite de Yuju.
     *
     * @return string
     */
    public static function utcDay()
    {
        return gmdate('Y-m-d');
    }

    /**
     * Máximo de solicitudes diarias configurado (1 o 2).
     *
     * @return int
     */
    public static function getConfiguredMaxDaily()
    {
        $max = (int) YujuConfig::get('YUJU_GRAL_REPORT_MAX_DAILY', self::API_MAX_PER_DAY);
        if ($max < 1) {
            $max = 1;
        }
        if ($max > self::API_MAX_PER_DAY) {
            $max = self::API_MAX_PER_DAY;
        }

        return $max;
    }

    /**
     * ¿Existe la tabla de reportes?
     *
     * @return bool
     */
    public static function tableExists()
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $full = _DB_PREFIX_ . 'yuju_product_reports';
            $check = Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($full) . '"');
            $exists = !empty($check);
        } catch (Exception $e) {
            $exists = false;
        }

        return $exists;
    }

    /**
     * Solicitudes contabilizadas hoy (UTC) que consumen cupo.
     *
     * @return int
     */
    public static function getRequestsCountToday()
    {
        if (!self::tableExists()) {
            return 0;
        }

        $day = self::utcDay();
        try {
            $count = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
                 WHERE report_type = \'' . pSQL(self::REPORT_TYPE) . '\'
                   AND utc_day = \'' . pSQL($day) . '\'
                   AND status IN (\'pending\',\'processing\',\'completed\')'
            );
        } catch (Exception $e) {
            return 0;
        }

        return $count;
    }

    /**
     * @return array{allowed:bool,used:int,max:int,utc_day:string,message:?string}
     */
    public static function getQuotaStatus()
    {
        $used = self::getRequestsCountToday();
        $max = self::getConfiguredMaxDaily();
        $day = self::utcDay();
        $allowed = $used < $max;

        return [
            'allowed' => $allowed,
            'used' => $used,
            'max' => $max,
            'remaining' => max(0, $max - $used),
            'utc_day' => $day,
            'message' => $allowed
                ? null
                : 'Límite diario alcanzado (' . $used . '/' . $max . '). Los créditos se renuevan a las 00:00 UTC.',
        ];
    }

    /**
     * Días de la semana habilitados (ISO-8601: 1=lunes … 7=domingo).
     *
     * @return int[]
     */
    public static function getConfiguredWeekdays()
    {
        $raw = (string) YujuConfig::get('YUJU_GRAL_REPORT_WEEKDAYS', '1,2,3,4,5,6,7');
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $days = [];
        if (is_array($parts)) {
            foreach ($parts as $p) {
                $d = (int) $p;
                if ($d >= 1 && $d <= 7) {
                    $days[$d] = $d;
                }
            }
        }
        $days = array_values($days);
        sort($days);

        return $days;
    }

    /**
     * Día de la semana actual según zona horaria de la tienda (1=lun … 7=dom).
     *
     * @return int
     */
    public static function getShopWeekday()
    {
        $tzName = (string) Configuration::get('PS_TIMEZONE');
        try {
            if ($tzName !== '') {
                $dt = new DateTime('now', new DateTimeZone($tzName));

                return (int) $dt->format('N');
            }
        } catch (Exception $e) {
            // fallback
        }

        return (int) date('N');
    }

    /**
     * ¿Hoy (zona tienda) está entre los días configurados?
     *
     * @return bool
     */
    public static function isAllowedWeekdayToday()
    {
        $allowed = self::getConfiguredWeekdays();
        if ($allowed === []) {
            return false;
        }

        return in_array(self::getShopWeekday(), $allowed, true);
    }

    /**
     * ¿Corresponde solicitar reporte programado?
     *
     * @return bool
     */
    public static function isDue()
    {
        if (!(int) YujuConfig::get('YUJU_GRAL_REPORT_ENABLED', 0)) {
            return false;
        }
        if (!self::tableExists()) {
            return false;
        }
        if (!self::isAllowedWeekdayToday()) {
            return false;
        }

        $quota = self::getQuotaStatus();
        if (!$quota['allowed']) {
            return false;
        }

        try {
            $pending = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
                 WHERE report_type = \'' . pSQL(self::REPORT_TYPE) . '\'
                   AND status IN (\'pending\',\'processing\')'
            );
        } catch (Exception $e) {
            return false;
        }
        if ($pending > 0) {
            return false;
        }

        $last = (string) YujuConfig::get('YUJU_GRAL_REPORT_LAST_REQUEST_AT', '');
        if ($last === '') {
            return true;
        }

        $max = self::getConfiguredMaxDaily();
        // Repartir cupos a lo largo del día UTC (ej. 2 → cada ~12h)
        $intervalSeconds = (int) floor(86400 / max(1, $max));
        $elapsed = time() - strtotime($last);

        return $elapsed >= $intervalSeconds;
    }

    /**
     * Solicita generación del reporte (POST).
     *
     * @param string $trigger manual|scheduled|webhook
     *
     * @return array
     */
    public function requestReport($trigger = 'manual', $force = false)
    {
        $trigger = in_array($trigger, ['manual', 'scheduled', 'webhook'], true) ? $trigger : 'manual';
        $quota = self::getQuotaStatus();

        if (!$force && !$quota['allowed']) {
            return [
                'success' => false,
                'message' => $quota['message'],
                'quota' => $quota,
            ];
        }

        $pending = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
             WHERE report_type = \'' . pSQL(self::REPORT_TYPE) . '\'
               AND status IN (\'pending\',\'processing\')
             ORDER BY id DESC'
        );
        if ($pending) {
            return [
                'success' => true,
                'message' => 'Ya hay un reporte en curso (tarea ' . $pending['id_task'] . ').',
                'report_id' => (int) $pending['id'],
                'id_task' => $pending['id_task'],
                'status' => $pending['status'],
                'quota' => $quota,
                'already_running' => true,
            ];
        }

        $oauth = new YujuOAuth();
        $token = $oauth->getValidAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No hay token OAuth válido.'];
        }

        $now = date('Y-m-d H:i:s');
        $utcDay = self::utcDay();

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.tp.yuju.io/' . self::API_ENDPOINT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
                'Content-Length: 0',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $api = json_decode($response, true);
        $idTask = is_array($api) ? ($api['id_task'] ?? null) : null;

        if ($httpCode !== 200 || !$idTask) {
            $detail = is_array($api) ? json_encode($api, JSON_UNESCAPED_UNICODE) : (string) $response;
            $msg = 'Error al solicitar products-gral-report (HTTP ' . $httpCode . ')';
            if ($curlError) {
                $msg .= ': ' . $curlError;
            } elseif ($detail) {
                $msg .= ': ' . substr($detail, 0, 220);
            }

            return ['success' => false, 'message' => $msg, 'quota' => $quota];
        }

        $insert = [
            'id_task' => pSQL($idTask),
            'report_type' => pSQL(self::REPORT_TYPE),
            'trigger' => pSQL($trigger),
            'status' => 'processing',
            'products_count' => 0,
            'file_path' => '',
            'file_size' => 0,
            'download_url' => '',
            'error_message' => '',
            'meta_json' => pSQL(json_encode([
                'api_message' => $api['message'] ?? null,
            ], JSON_UNESCAPED_UNICODE)),
            'requested_at' => pSQL($now),
            'completed_at' => null,
            'utc_day' => pSQL($utcDay),
            'created_at' => pSQL($now),
            'updated_at' => pSQL($now),
        ];

        if (!Db::getInstance()->insert('yuju_product_reports', $insert)) {
            return [
                'success' => false,
                'message' => 'Se obtuvo id_task pero no se pudo guardar en histórico.',
                'id_task' => $idTask,
            ];
        }

        $reportId = (int) Db::getInstance()->Insert_ID();
        YujuConfig::set('YUJU_GRAL_REPORT_LAST_REQUEST_AT', $now, 'string');

        $this->logger->info('products-gral-report solicitado', [
            'report_id' => $reportId,
            'id_task' => $idTask,
            'trigger' => $trigger,
        ]);

        return [
            'success' => true,
            'message' => $api['message'] ?? 'Reporte en progreso. Se consultará el estado hasta completarlo.',
            'report_id' => $reportId,
            'id_task' => $idTask,
            'status' => 'processing',
            'quota' => self::getQuotaStatus(),
        ];
    }

    /**
     * Consulta estado de una tarea y descarga si COMPLETED.
     *
     * @param int $reportId
     *
     * @return array
     */
    public function pollAndDownload($reportId)
    {
        $report = $this->getReport((int) $reportId);
        if (!$report) {
            return ['success' => false, 'message' => 'Reporte no encontrado.'];
        }
        if (in_array($report['status'], ['completed', 'failed', 'rejected', 'limit_reached'], true)) {
            return [
                'success' => true,
                'report' => $report,
                'finished' => true,
            ];
        }
        if (empty($report['id_task'])) {
            return ['success' => false, 'message' => 'El reporte no tiene id_task.'];
        }

        return $this->pollTaskAndMaybeDownload($report['id_task'], (int) $report['id']);
    }

    /**
     * Procesa todas las tareas pendientes/procesando.
     *
     * @return array
     */
    public function processPending($limit = 5)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
             WHERE report_type = \'' . pSQL(self::REPORT_TYPE) . '\'
               AND status IN (\'pending\',\'processing\')
             ORDER BY id ASC
             LIMIT ' . (int) $limit
        );

        $results = [];
        if (!is_array($rows)) {
            return $results;
        }
        foreach ($rows as $row) {
            $results[] = $this->pollAndDownload((int) $row['id']);
        }

        return $results;
    }

    /**
     * Completa un reporte a partir del webhook (topic products-gral-report).
     *
     * @param string $idTask
     * @param string|null $url
     *
     * @return array
     */
    public function handleWebhook($idTask, $url = null)
    {
        $idTask = trim((string) $idTask);
        if ($idTask === '') {
            return ['success' => false, 'message' => 'id_task vacío en webhook.'];
        }

        $report = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_product_reports`
             WHERE id_task = \'' . pSQL($idTask) . '\''
        );

        if (!$report) {
            $now = date('Y-m-d H:i:s');
            Db::getInstance()->insert('yuju_product_reports', [
                'id_task' => pSQL($idTask),
                'report_type' => pSQL(self::REPORT_TYPE),
                'trigger' => 'webhook',
                'status' => 'processing',
                'products_count' => 0,
                'utc_day' => pSQL(self::utcDay()),
                'requested_at' => pSQL($now),
                'created_at' => pSQL($now),
                'updated_at' => pSQL($now),
            ]);
            $reportId = (int) Db::getInstance()->Insert_ID();
        } else {
            $reportId = (int) $report['id'];
            if ($report['status'] === 'completed' && !empty($report['file_path']) && file_exists($report['file_path'])) {
                return ['success' => true, 'report' => $report, 'already_done' => true];
            }
        }

        if ($url) {
            return $this->downloadAndStore($reportId, $idTask, $url);
        }

        return $this->pollTaskAndMaybeDownload($idTask, $reportId);
    }

    /**
     * @param string $idTask
     * @param int $reportId
     *
     * @return array
     */
    protected function pollTaskAndMaybeDownload($idTask, $reportId)
    {
        $oauth = new YujuOAuth();
        $token = $oauth->getValidAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No hay token OAuth válido.'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.tp.yuju.io/' . self::API_ENDPOINT . '/' . rawurlencode($idTask),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $api = json_decode($response, true);
        if ($httpCode !== 200 || !is_array($api)) {
            $this->updateReport($reportId, [
                'error_message' => 'Error consultando tarea (HTTP ' . $httpCode . '): ' . ($curlError ?: substr((string) $response, 0, 180)),
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo consultar el estado de la tarea.',
                'http_code' => $httpCode,
            ];
        }

        $status = strtoupper((string) ($api['status'] ?? ''));
        if ($status === 'COMPLETED' && !empty($api['url'])) {
            return $this->downloadAndStore($reportId, $idTask, $api['url']);
        }

        if (in_array($status, ['REJECTED', 'ERROR'], true)) {
            $map = $status === 'REJECTED' ? 'rejected' : 'failed';
            $this->updateReport($reportId, [
                'status' => $map,
                'error_message' => 'Tarea ' . $status,
                'completed_at' => date('Y-m-d H:i:s'),
                'meta_json' => json_encode($api, JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'success' => false,
                'message' => 'La tarea terminó en estado ' . $status,
                'report' => $this->getReport($reportId),
                'finished' => true,
            ];
        }

        $this->updateReport($reportId, [
            'status' => 'processing',
            'meta_json' => json_encode($api, JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'success' => true,
            'message' => 'Estado: ' . ($status ?: 'PROCESSING'),
            'status' => $status,
            'finished' => false,
            'report' => $this->getReport($reportId),
        ];
    }

    /**
     * Descarga JSONL (CloudFront signed URL) y lo guarda en histórico.
     *
     * @param int $reportId
     * @param string $idTask
     * @param string $url
     *
     * @return array
     */
    public function downloadAndStore($reportId, $idTask, $url)
    {
        $download = $this->downloadSignedFile($url);
        if ((int) $download['http_code'] !== 200 || empty($download['content'])) {
            // Reintento corto
            if ((int) $download['http_code'] === 403) {
                sleep(2);
                $download = $this->downloadSignedFile($url);
            }
        }

        if ((int) $download['http_code'] !== 200 || empty($download['content'])) {
            $this->updateReport($reportId, [
                'status' => 'failed',
                'download_url' => $url,
                'error_message' => 'Error al descargar JSONL (HTTP ' . (int) $download['http_code'] . ').',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => false,
                'message' => 'Error al descargar el archivo del reporte (HTTP ' . (int) $download['http_code'] . ').',
                'report' => $this->getReport($reportId),
            ];
        }

        $count = $this->countJsonlLines($download['content']);
        $stamp = date('Ymd_His');
        $fileName = 'gral_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $idTask) . '_' . $stamp . '.jsonl';
        $filePath = $this->reportsDir . $fileName;
        $bytes = file_put_contents($filePath, $download['content']);
        if ($bytes === false) {
            $this->updateReport($reportId, [
                'status' => 'failed',
                'error_message' => 'No se pudo guardar el archivo localmente.',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => false, 'message' => 'No se pudo guardar el JSONL en disco.'];
        }

        @file_put_contents($this->cacheDir . self::CACHE_LATEST, $download['content']);

        $now = date('Y-m-d H:i:s');
        $this->updateReport($reportId, [
            'status' => 'completed',
            'products_count' => $count,
            'file_path' => $filePath,
            'file_size' => (int) $bytes,
            'download_url' => $url,
            'error_message' => '',
            'completed_at' => $now,
            'meta_json' => json_encode([
                'lines' => $count,
                'saved_as' => $fileName,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        YujuConfig::set('YUJU_GRAL_REPORT_LAST_COMPLETED_AT', $now, 'string');

        $this->logger->info('products-gral-report descargado', [
            'report_id' => $reportId,
            'products_count' => $count,
            'file' => $fileName,
        ]);

        return [
            'success' => true,
            'message' => 'Reporte guardado (' . $count . ' productos).',
            'products_count' => $count,
            'file_path' => $filePath,
            'finished' => true,
            'report' => $this->getReport($reportId),
        ];
    }

    /**
     * @param string $url
     *
     * @return array{http_code:int,content:string|false}
     */
    protected function downloadSignedFile($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'User-Agent: PrestaShop-Yuju-Module/1.0',
            ],
        ]);
        $content = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['http_code' => $code, 'content' => $content];
    }

    /**
     * @param string $content
     *
     * @return int
     */
    protected function countJsonlLines($content)
    {
        $n = 0;
        $fh = fopen('php://memory', 'r+');
        if ($fh) {
            fwrite($fh, $content);
            rewind($fh);
            while (($line = fgets($fh)) !== false) {
                if (trim($line) !== '') {
                    ++$n;
                }
            }
            fclose($fh);
        }

        return $n;
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
     * Estadísticas para monitoreo.
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
            } elseif (in_array($r['status'], ['failed', 'rejected'], true)) {
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
     * Lectura paginada del JSONL para el visualizador.
     *
     * @param int $reportId
     * @param int $offset
     * @param int $limit
     * @param string $search
     *
     * @return array
     */
    public function browseProducts($reportId, $offset = 0, $limit = 50, $search = '')
    {
        $report = $this->getReport((int) $reportId);
        if (!$report || empty($report['file_path']) || !is_readable($report['file_path'])) {
            return [
                'success' => false,
                'message' => 'Archivo del reporte no disponible.',
            ];
        }

        $offset = max(0, (int) $offset);
        $limit = max(1, min(200, (int) $limit));
        $search = trim((string) $search);
        $searchLower = Tools::strtolower($search);

        $matched = [];
        $totalMatched = 0;
        $lineNo = 0;
        $fh = fopen($report['file_path'], 'r');
        if (!$fh) {
            return ['success' => false, 'message' => 'No se pudo abrir el JSONL.'];
        }

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            ++$lineNo;
            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }

            if ($searchLower !== '') {
                $hay = Tools::strtolower(
                    ($row['id'] ?? '') . ' ' .
                    ($row['id_product'] ?? '') . ' ' .
                    ($row['sku'] ?? '') . ' ' .
                    ($row['sku_simple'] ?? '') . ' ' .
                    ($row['name'] ?? '') . ' ' .
                    ($row['title'] ?? '')
                );
                if (strpos($hay, $searchLower) === false) {
                    // Buscar también en variaciones
                    $foundVar = false;
                    if (!empty($row['variations']) && is_array($row['variations'])) {
                        foreach ($row['variations'] as $var) {
                            if (!is_array($var)) {
                                continue;
                            }
                            $vh = Tools::strtolower(($var['sku'] ?? '') . ' ' . ($var['id'] ?? ''));
                            if (strpos($vh, $searchLower) !== false) {
                                $foundVar = true;
                                break;
                            }
                        }
                    }
                    if (!$foundVar) {
                        continue;
                    }
                }
            }

            if ($totalMatched >= $offset && count($matched) < $limit) {
                $matched[] = $this->summarizeProductRow($row, $lineNo);
            }
            ++$totalMatched;
        }
        fclose($fh);

        $reportType = (string) ($report['report_type'] ?? self::REPORT_TYPE);

        return [
            'success' => true,
            'report_id' => (int) $reportId,
            'report_type' => $reportType,
            'total' => $totalMatched,
            'offset' => $offset,
            'limit' => $limit,
            'products' => $matched,
            'report' => [
                'id' => (int) $report['id'],
                'id_task' => $report['id_task'],
                'report_type' => $reportType,
                'status' => $report['status'],
                'products_count' => (int) $report['products_count'],
                'requested_at' => $report['requested_at'],
                'completed_at' => $report['completed_at'],
            ],
        ];
    }

    /**
     * Detalle de un producto (línea) del JSONL.
     *
     * @param int $reportId
     * @param int $lineNo 1-based
     *
     * @return array
     */
    public function getProductByLine($reportId, $lineNo)
    {
        $report = $this->getReport((int) $reportId);
        if (!$report || empty($report['file_path']) || !is_readable($report['file_path'])) {
            return ['success' => false, 'message' => 'Archivo no disponible.'];
        }

        $target = max(1, (int) $lineNo);
        $fh = fopen($report['file_path'], 'r');
        if (!$fh) {
            return ['success' => false, 'message' => 'No se pudo abrir el JSONL.'];
        }

        $current = 0;
        while (($line = fgets($fh)) !== false) {
            if (trim($line) === '') {
                continue;
            }
            ++$current;
            if ($current === $target) {
                fclose($fh);
                $product = json_decode($line, true);

                return [
                    'success' => is_array($product),
                    'product' => $product,
                    'line' => $target,
                ];
            }
        }
        fclose($fh);

        return ['success' => false, 'message' => 'Línea no encontrada.'];
    }

    /**
     * @param array $row
     * @param int $lineNo
     *
     * @return array
     */
    protected function summarizeProductRow(array $row, $lineNo)
    {
        $images = [];
        if (!empty($row['images']) && is_array($row['images'])) {
            $images = $row['images'];
        } elseif (!empty($row['image'])) {
            $images = is_array($row['image']) ? $row['image'] : [$row['image']];
        }

        $variations = !empty($row['variations']) && is_array($row['variations']) ? count($row['variations']) : 0;

        return [
            'line' => (int) $lineNo,
            'id' => $row['id'] ?? ($row['id_product'] ?? null),
            'sku' => $row['sku'] ?? ($row['sku_simple'] ?? null),
            'name' => $row['name'] ?? ($row['title'] ?? ''),
            'stock' => isset($row['stock']) ? (int) $row['stock'] : null,
            'price' => isset($row['price']) ? (float) $row['price'] : null,
            'images_count' => count($images),
            'thumb' => !empty($images[0]) ? (is_string($images[0]) ? $images[0] : ($images[0]['url'] ?? null)) : null,
            'variations_count' => $variations,
            'active' => isset($row['active']) ? (bool) $row['active'] : null,
        ];
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
