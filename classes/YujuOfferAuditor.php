<?php
/**
 * Auditor de ofertas Yuju vs PrestaShop.
 * PrestaShop es la fuente de verdad: solo se corrige Yuju (nunca se escribe en PS).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/YujuLogger.php';
require_once dirname(__FILE__) . '/YujuOAuth.php';
require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuProductGralReport.php';
require_once dirname(__FILE__) . '/YujuProductOfferReport.php';
require_once dirname(__FILE__) . '/../config/config.php';

class YujuOfferAuditor
{
    const CACHE_OFFERS_FILE = 'yuju_products.json';
    const CACHE_MAX_AGE = 43200; // 12h (legacy offer-report; la auditoría usa gral-report)

    /** Mensaje cuando Yuju rechazaría un cambio de precio abrupto (0 o >50%). */
    const PRICE_SECURITY_MSG = 'yuju por cuestiones de seguridad no permite cambios abruptos de precio mayores al 50%';

    /** @var YujuApiClient */
    protected $api;

    /** @var YujuLogger */
    protected $logger;

    /** @var string */
    protected $cacheDir;

    public function __construct()
    {
        $this->api = new YujuApiClient();
        $this->logger = new YujuLogger();
        $this->cacheDir = dirname(__FILE__) . '/../cache/';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * ¿Debe ejecutarse la auditoría programada ahora?
     */
    public static function isDue()
    {
        if (!(int) YujuConfig::get('YUJU_AUDIT_ENABLED', 0)) {
            return false;
        }

        $running = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_audit_runs` WHERE `status` = \'running\''
        );
        if ($running > 0) {
            return false;
        }

        $schedule = (string) YujuConfig::get('YUJU_AUDIT_SCHEDULE', 'daily');
        $times = max(1, (int) YujuConfig::get('YUJU_AUDIT_TIMES_PER_PERIOD', 1));
        $lastRun = (string) YujuConfig::get('YUJU_AUDIT_LAST_RUN_AT', '');

        $periodSeconds = self::schedulePeriodSeconds($schedule);
        $interval = (int) max(60, floor($periodSeconds / $times));

        if ($lastRun === '') {
            return true;
        }

        $lastTs = strtotime($lastRun);
        if ($lastTs === false) {
            return true;
        }

        return (time() - $lastTs) >= $interval;
    }

    public static function markScheduledExecuted()
    {
        YujuConfig::set('YUJU_AUDIT_LAST_RUN_AT', date('Y-m-d H:i:s'), 'string');
    }

    /**
     * @param string $schedule
     * @return int
     */
    protected static function schedulePeriodSeconds($schedule)
    {
        switch ($schedule) {
            case 'hourly':
                return 3600;
            case 'weekly':
                return 7 * 86400;
            case 'daily':
            default:
                return 86400;
        }
    }

    /**
     * Inicia un run de auditoría.
     *
     * Fuente: reporte guardado en yuju_product_reports (offer o gral).
     * - options['report_id']: reanaliza un JSON ya descargado
     * - options['apply_fixes']: si false, solo reporta diferencias
     *
     * @param string $trigger scheduled|manual
     * @param string $mode silent|visual
     * @param array|null $fieldOverrides optional keys stock/price/images
     * @param array $options
     *
     * @return array
     */
    public function startRun($trigger = 'manual', $mode = 'silent', $fieldOverrides = null, array $options = [])
    {
        $reportId = isset($options['report_id']) ? (int) $options['report_id'] : 0;
        if ($reportId <= 0) {
            return [
                'success' => false,
                'needs_report' => true,
                'message' => 'Solicite un reporte (ofertas o general) o elija uno guardado para reanalizar.',
            ];
        }

        return $this->startRunFromSavedReport($reportId, $trigger, $mode, $fieldOverrides, $options);
    }

    /**
     * Alias compat: inicia desde cualquier reporte guardado (offer o gral).
     *
     * @param int $reportId
     * @param string $trigger
     * @param string $mode
     * @param array|null $fieldOverrides
     * @param array $options
     *
     * @return array
     */
    public function startRunFromGralReport($reportId, $trigger = 'manual', $mode = 'silent', $fieldOverrides = null, array $options = [])
    {
        return $this->startRunFromSavedReport($reportId, $trigger, $mode, $fieldOverrides, $options);
    }

    /**
     * Inicia auditoría a partir de un reporte ya guardado (products-offer-report o products-gral-report).
     *
     * @param int $reportId
     * @param string $trigger
     * @param string $mode
     * @param array|null $fieldOverrides
     * @param array $options
     *
     * @return array
     */
    public function startRunFromSavedReport($reportId, $trigger = 'manual', $mode = 'silent', $fieldOverrides = null, array $options = [])
    {
        $auditStock = $fieldOverrides !== null
            ? (!empty($fieldOverrides['stock']) ? 1 : 0)
            : (int) YujuConfig::get('YUJU_AUDIT_STOCK', 1);
        $auditPrice = $fieldOverrides !== null
            ? (!empty($fieldOverrides['price']) ? 1 : 0)
            : (int) YujuConfig::get('YUJU_AUDIT_PRICE', 1);
        $auditImages = $fieldOverrides !== null
            ? (!empty($fieldOverrides['images']) ? 1 : 0)
            : (int) YujuConfig::get('YUJU_AUDIT_IMAGES', 0);

        $lookup = new YujuProductGralReport();
        $reportMeta = $lookup->getReport((int) $reportId);
        if (!$reportMeta) {
            return ['success' => false, 'message' => 'Reporte #' . (int) $reportId . ' no encontrado.'];
        }

        $reportType = (string) ($reportMeta['report_type'] ?? 'gral');
        // Ofertas no traen imágenes: si reanalizan un offer con “Imágenes”, se ignora
        if ($reportType === YujuProductOfferReport::REPORT_TYPE) {
            $auditImages = 0;
        }

        if (!$auditStock && !$auditPrice && !$auditImages) {
            return [
                'success' => false,
                'message' => 'Debe habilitar al menos un campo a auditar (stock, precio o imágenes).',
            ];
        }

        $applyFixes = !empty($options['apply_fixes']);
        if ($reportType === YujuProductOfferReport::REPORT_TYPE) {
            $loaded = $this->loadProductsFromOfferReport((int) $reportId);
            $source = 'products-offer-report';
        } else {
            $loaded = $this->loadProductsFromGralReport((int) $reportId);
            $source = 'products-gral-report';
        }

        if (empty($loaded['success'])) {
            return [
                'success' => false,
                'message' => $loaded['message'] ?? 'No se pudo leer el reporte.',
            ];
        }

        $offers = $loaded['offers'];
        $total = count($offers);
        $report = $loaded['report'];

        $now = date('Y-m-d H:i:s');
        $insert = [
            'trigger' => pSQL(in_array($trigger, ['scheduled', 'manual'], true) ? $trigger : 'manual'),
            'mode' => pSQL(in_array($mode, ['silent', 'visual'], true) ? $mode : 'silent'),
            'audit_stock' => (int) $auditStock,
            'audit_price' => (int) $auditPrice,
            'audit_images' => (int) $auditImages,
            'status' => 'running',
            'total' => (int) $total,
            'processed' => 0,
            'matched' => 0,
            'diff_found' => 0,
            'fixed' => 0,
            'errors' => 0,
            'not_found' => 0,
            'started_at' => pSQL($now),
            'summary_json' => pSQL(json_encode([
                'source' => $source,
                'report_type' => $reportType,
                'report_id' => (int) $reportId,
                'id_task' => $report['id_task'] ?? null,
                'apply_fixes' => $applyFixes ? 1 : 0,
                'reanalyze' => !empty($options['reanalyze']) ? 1 : 0,
                'products_in_report' => (int) ($report['products_count'] ?? $total),
            ], JSON_UNESCAPED_UNICODE)),
            'created_at' => pSQL($now),
            'updated_at' => pSQL($now),
        ];

        if (!Db::getInstance()->insert('yuju_audit_runs', $insert)) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el registro de auditoría.',
            ];
        }

        $runId = (int) Db::getInstance()->Insert_ID();
        $this->writeRunOffersJsonl($runId, $offers, [
            'apply_fixes' => $applyFixes,
            'report_id' => (int) $reportId,
            'report_type' => $reportType,
        ]);

        $this->logger->info('Auditoría iniciada desde ' . $source, [
            'run_id' => $runId,
            'report_id' => (int) $reportId,
            'report_type' => $reportType,
            'total' => $total,
            'apply_fixes' => $applyFixes,
        ]);

        return [
            'success' => true,
            'run_id' => $runId,
            'total' => $total,
            'report_id' => (int) $reportId,
            'id_task' => $report['id_task'] ?? null,
            'apply_fixes' => $applyFixes,
            'source' => $source,
            'report_type' => $reportType,
            'reanalyze' => !empty($options['reanalyze']),
        ];
    }

    /**
     * Lee JSONL de un products-offer-report (1 oferta plana por línea).
     *
     * @param int $reportId
     *
     * @return array
     */
    public function loadProductsFromOfferReport($reportId)
    {
        $service = new YujuProductOfferReport();
        $report = $service->getReport((int) $reportId);
        if (!$report) {
            return ['success' => false, 'message' => 'Reporte #' . (int) $reportId . ' no encontrado.'];
        }
        if (($report['report_type'] ?? '') !== YujuProductOfferReport::REPORT_TYPE) {
            return ['success' => false, 'message' => 'El reporte #' . (int) $reportId . ' no es de tipo offer.'];
        }
        if ($report['status'] !== 'completed') {
            return [
                'success' => false,
                'message' => 'El reporte #' . (int) $reportId . ' aún no está completado (estado: ' . $report['status'] . ').',
                'report' => $report,
            ];
        }
        if (empty($report['file_path']) || !is_readable($report['file_path'])) {
            return ['success' => false, 'message' => 'Archivo JSONL del reporte de ofertas no disponible en disco.'];
        }

        $offers = [];
        $fh = fopen($report['file_path'], 'r');
        if (!$fh) {
            return ['success' => false, 'message' => 'No se pudo abrir el JSONL de ofertas.'];
        }

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (!is_array($row)) {
                continue;
            }
            $offers[] = [
                'id' => $row['id'] ?? ($row['id_product'] ?? null),
                'sku' => $row['sku'] ?? ($row['sku_simple'] ?? null),
                'sku_simple' => $row['sku_simple'] ?? null,
                'parent_id' => $row['parent_id'] ?? ($row['id_parent'] ?? null),
                'stock' => isset($row['stock']) ? (int) $row['stock'] : null,
                'price' => isset($row['price']) ? (float) $row['price'] : null,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'images' => [],
            ];
        }
        fclose($fh);

        if (empty($offers)) {
            return ['success' => false, 'message' => 'El reporte de ofertas no contiene filas.', 'report' => $report];
        }

        return ['success' => true, 'offers' => $offers, 'report' => $report];
    }

    /**
     * Lee JSONL de un reporte gral y lo aplana a filas comparables (sku/stock/price/images).
     *
     * @param int $reportId
     *
     * @return array
     */
    public function loadProductsFromGralReport($reportId)
    {
        $service = new YujuProductGralReport();
        $report = $service->getReport((int) $reportId);
        if (!$report) {
            return ['success' => false, 'message' => 'Reporte #' . (int) $reportId . ' no encontrado.'];
        }
        if ($report['status'] !== 'completed') {
            return [
                'success' => false,
                'message' => 'El reporte #' . (int) $reportId . ' aún no está completado (estado: ' . $report['status'] . ').',
                'report' => $report,
            ];
        }
        if (empty($report['file_path']) || !is_readable($report['file_path'])) {
            return ['success' => false, 'message' => 'Archivo JSONL del reporte no disponible en disco.'];
        }

        $offers = [];
        $fh = fopen($report['file_path'], 'r');
        if (!$fh) {
            return ['success' => false, 'message' => 'No se pudo abrir el JSONL.'];
        }

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $product = json_decode($line, true);
            if (!is_array($product)) {
                continue;
            }
            foreach ($this->flattenGralProduct($product) as $row) {
                $offers[] = $row;
            }
        }
        fclose($fh);

        if (empty($offers)) {
            return ['success' => false, 'message' => 'El JSONL no contiene productos útiles para comparar.'];
        }

        return [
            'success' => true,
            'offers' => $offers,
            'report' => $report,
        ];
    }

    /**
     * Convierte un producto (y variaciones) del gral-report en filas estilo oferta.
     *
     * @param array $product
     *
     * @return array[]
     */
    protected function flattenGralProduct(array $product)
    {
        $parentImages = [];
        if (!empty($product['images']) && is_array($product['images'])) {
            $parentImages = $product['images'];
        }

        $rows = [];
        $variations = (!empty($product['variations']) && is_array($product['variations']))
            ? $product['variations']
            : [];

        if (!empty($variations)) {
            foreach ($variations as $var) {
                if (!is_array($var)) {
                    continue;
                }
                $rows[] = [
                    'id' => $var['id'] ?? ($product['id'] ?? null),
                    'id_product' => $product['id'] ?? null,
                    'parent_id' => $product['id'] ?? null,
                    'sku' => $var['sku'] ?? ($var['sku_simple'] ?? ($product['sku'] ?? null)),
                    'sku_simple' => $var['sku_simple'] ?? ($product['sku_simple'] ?? null),
                    'stock' => isset($var['stock']) ? $var['stock'] : ($product['stock'] ?? 0),
                    'price' => isset($var['price']) ? $var['price'] : ($product['price'] ?? 0),
                    'images' => !empty($var['images']) && is_array($var['images']) ? $var['images'] : $parentImages,
                    'name' => $product['name'] ?? '',
                ];
            }

            return $rows;
        }

        $rows[] = [
            'id' => $product['id'] ?? ($product['id_product'] ?? null),
            'id_product' => $product['id'] ?? null,
            'sku' => $product['sku'] ?? ($product['sku_simple'] ?? null),
            'sku_simple' => $product['sku_simple'] ?? null,
            'stock' => $product['stock'] ?? 0,
            'price' => $product['price'] ?? 0,
            'images' => $parentImages,
            'name' => $product['name'] ?? '',
        ];

        return $rows;
    }

    /**
     * Procesa un chunk de ofertas del run.
     *
     * @param int $runId
     * @param int $limit
     * @return array
     */
    public function processChunk($runId, $limit = null)
    {
        $runId = (int) $runId;
        $tRequest = microtime(true);
        $budget = max(5, (int) YujuConfig::get('YUJU_AUDIT_REQUEST_BUDGET', 25));

        // Reanálisis / solo comparación local: sin API → presupuesto y lotes más grandes
        $metaPeek = $this->readRunMeta($runId);
        $applyFixesPeek = !empty($metaPeek['apply_fixes']);
        if (!$applyFixesPeek) {
            $budget = max($budget, 55);
            if ($limit === null) {
                $limit = 500;
            }
        }

        $allRows = [];
        $timingAgg = [
            'compare_ms' => 0,
            'mass_ms' => 0,
            'put_ms' => 0,
            'db_ms' => 0,
            'offers_mass' => 0,
            'puts' => 0,
            'inner_chunks' => 0,
        ];
        $last = null;

        do {
            $last = $this->processChunkInner($runId, $limit);
            if (empty($last['success'])) {
                return $last;
            }
            if (!empty($last['rows']) && is_array($last['rows'])) {
                $allRows = array_merge($allRows, $last['rows']);
            }
            if (!empty($last['timing']) && is_array($last['timing'])) {
                foreach (['compare_ms', 'mass_ms', 'put_ms', 'db_ms'] as $k) {
                    $timingAgg[$k] += (int) ($last['timing'][$k] ?? 0);
                }
                $timingAgg['offers_mass'] += (int) ($last['timing']['offers_mass'] ?? 0);
                $timingAgg['puts'] += (int) ($last['timing']['puts'] ?? 0);
                $timingAgg['inner_chunks']++;
            }
            if (!empty($last['finished']) || !empty($last['paused'])) {
                break;
            }
            // Con presupuesto residual, seguir en la misma petición AJAX (menos bootstrap PS)
        } while ((microtime(true) - $tRequest) < $budget);

        $timingAgg['request_ms'] = (int) round((microtime(true) - $tRequest) * 1000);
        $last['rows'] = $allRows;
        $last['timing'] = $timingAgg;
        if (isset($last['chunk']) && is_array($last['chunk'])) {
            $last['chunk']['size'] = count($allRows);
        }

        return $last;
    }

    /**
     * Procesa un solo lote del JSONL (comparación local + correcciones batch).
     *
     * @param int $runId
     * @param int|null $limit
     *
     * @return array
     */
    protected function processChunkInner($runId, $limit = null)
    {
        $runId = (int) $runId;
        $run = $this->getRun($runId);
        if (!$run) {
            return ['success' => false, 'message' => 'Run no encontrado.'];
        }
        if (in_array($run['status'], ['completed', 'failed', 'cancelled'], true)) {
            return [
                'success' => true,
                'finished' => true,
                'progress' => $this->getProgress($runId),
                'rows' => [],
            ];
        }
        if ($run['status'] === 'paused') {
            return [
                'success' => true,
                'paused' => true,
                'finished' => false,
                'progress' => $this->getProgress($runId),
                'rows' => [],
            ];
        }

        $meta = $this->readRunMeta($runId);
        if ($meta === null) {
            $this->failRun($runId, 'Archivo de ofertas del run no disponible.');
            return ['success' => false, 'message' => 'Archivo de ofertas del run no disponible.'];
        }
        $applyFixes = !empty($meta['apply_fixes']);

        if ($limit === null) {
            $limit = (int) YujuConfig::get('YUJU_AUDIT_CHUNK_SIZE', $applyFixes ? 200 : 500);
        }
        // Sin correcciones API se puede procesar lotes mayores en memoria
        $limit = max(1, min($applyFixes ? 400 : 800, (int) $limit));

        $offset = (int) $run['processed'];
        $t0 = microtime(true);
        $read = $this->readOffersSlice($runId, $offset, $limit, $meta);
        if ($read === null || !isset($read['slice'])) {
            $this->failRun($runId, 'No se pudo leer el JSONL del run.');
            return ['success' => false, 'message' => 'No se pudo leer el JSONL del run.'];
        }
        $slice = $read['slice'];
        if (isset($read['cursor_byte'])) {
            $meta['cursor_byte'] = (int) $read['cursor_byte'];
            $meta['cursor_line'] = $offset + count($slice);
            @file_put_contents($this->getRunMetaPath($runId), json_encode($meta, JSON_UNESCAPED_UNICODE));
        }

        if (empty($slice)) {
            $now = date('Y-m-d H:i:s');
            Db::getInstance()->update('yuju_audit_runs', [
                'status' => 'completed',
                'finished_at' => pSQL($now),
                'duration_seconds' => (int) $this->computeDurationSeconds($run['started_at'], $now, $run),
                'updated_at' => pSQL($now),
            ], 'id = ' . (int) $runId);
            YujuConfig::set('YUJU_AUDIT_LAST_RUN_AT', $now, 'string');
            $this->cleanupRunOffers($runId);

            return [
                'success' => true,
                'finished' => true,
                'paused' => false,
                'progress' => $this->getProgress($runId),
                'rows' => [],
                'chunk' => ['size' => 0, 'diffs' => 0],
            ];
        }

        $counters = [
            'matched' => (int) $run['matched'],
            'diff_found' => (int) $run['diff_found'],
            'fixed' => (int) $run['fixed'],
            'errors' => (int) $run['errors'],
            'not_found' => (int) $run['not_found'],
        ];

        $auditStock = (int) $run['audit_stock'];
        $auditPrice = (int) $run['audit_price'];
        $auditImages = (int) $run['audit_images'];
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $idShop = (int) Context::getContext()->shop->id;

        $ctx = $this->preloadProductContext($slice, $idLang, $idShop);
        $pendingDetails = [];
        $massOffers = []; // sku => ['sku','stock?','price?']
        $individualPuts = []; // index => ['yuju_id'=>, 'payload'=>]
        $diffCount = 0;

        foreach ($slice as $offer) {
            $detail = $this->auditSingleOfferFast(
                $offer,
                $auditStock,
                $auditPrice,
                $auditImages,
                $idLang,
                $applyFixes,
                $ctx
            );
            $detail['id_audit_run'] = $runId;

            // Separar correcciones: stock/precio → masivo; imágenes → PUT individual
            if ($applyFixes && !empty($detail['_fix_payload']) && is_array($detail['_fix_payload'])) {
                $payload = $detail['_fix_payload'];
                $yujuId = $detail['yuju_product_id'] ?? null;
                $hasImages = !empty($payload['images']);
                $hasOffer = isset($payload['stock']) || isset($payload['price']);

                if ($hasImages && $yujuId) {
                    $individualPuts[count($pendingDetails)] = [
                        'yuju_id' => $yujuId,
                        'payload' => $payload,
                    ];
                } elseif ($hasOffer && !empty($detail['sku'])) {
                    $row = ['sku' => (string) $detail['sku']];
                    if (isset($payload['stock'])) {
                        $row['stock'] = (int) $payload['stock'];
                    }
                    if (isset($payload['price'])) {
                        $row['price'] = (float) $payload['price'];
                    }
                    if ($yujuId) {
                        $row['_yuju_id'] = (string) $yujuId;
                    }
                    $massOffers[(string) $detail['sku']] = $row;
                    $detail['_mass_sku'] = (string) $detail['sku'];
                } elseif ($yujuId && !empty($payload)) {
                    $individualPuts[count($pendingDetails)] = [
                        'yuju_id' => $yujuId,
                        'payload' => $payload,
                    ];
                }
            }
            unset($detail['_fix_payload']);
            if (!empty($detail['price_update_blocked'])) {
                $this->appendPriceSecurityNote($detail);
            }

            $pendingDetails[] = $detail;
        }
        $compareMs = (int) round((microtime(true) - $t0) * 1000);

        $massMs = 0;
        $putMs = 0;
        $offersMassCount = count($massOffers);
        $putsCount = count($individualPuts);

        if ($applyFixes && !empty($massOffers)) {
            $tMass = microtime(true);
            $massPayload = [];
            foreach ($massOffers as $row) {
                $clean = ['sku' => $row['sku']];
                if (isset($row['stock'])) {
                    $clean['stock'] = $row['stock'];
                }
                if (isset($row['price'])) {
                    $clean['price'] = $row['price'];
                }
                if (isset($row['_yuju_id'])) {
                    $clean['_yuju_id'] = $row['_yuju_id'];
                }
                $massPayload[] = $clean;
            }
            $massResult = $this->applyMassOfferFixes($massPayload);
            $massMs = (int) round((microtime(true) - $tMass) * 1000);
            $skuOk = $massResult['sku_ok'] ?? [];
            $skuErr = $massResult['sku_err'] ?? [];
            $globalErr = !empty($massResult['error']) ? (string) $massResult['error'] : null;

            foreach ($pendingDetails as &$detail) {
                if (empty($detail['_mass_sku'])) {
                    continue;
                }
                $sku = (string) $detail['_mass_sku'];
                unset($detail['_mass_sku']);
                if (isset($skuErr[$sku])) {
                    $detail['result'] = 'diff_error';
                    $detail['message'] = 'Error masivo Yuju: ' . $skuErr[$sku];
                    $detail['error_detail'] = ['type' => 'mass_offer', 'message' => $skuErr[$sku]];
                    $this->appendPriceSecurityNote($detail);
                    continue;
                }
                if (isset($skuOk[$sku]) || (!empty($massResult['success']) && empty($globalErr))) {
                    $detail['result'] = 'diff_fixed';
                    $fields = [];
                    if (isset($massOffers[$sku]['stock'])) {
                        $fields[] = 'stock';
                    }
                    if (isset($massOffers[$sku]['price'])) {
                        $fields[] = 'price';
                    }
                    $detail['message'] = 'Corregido en Yuju (masivo): ' . implode(', ', $fields);
                    $this->appendPriceSecurityNote($detail);
                    continue;
                }
                $detail['result'] = 'diff_error';
                $detail['message'] = 'Error masivo Yuju: ' . ($globalErr ?: 'respuesta incompleta');
                $detail['error_detail'] = ['type' => 'mass_offer', 'message' => $globalErr ?: 'respuesta incompleta'];
                $this->appendPriceSecurityNote($detail);
            }
            unset($detail);
        } else {
            foreach ($pendingDetails as &$detail) {
                unset($detail['_mass_sku']);
            }
            unset($detail);
        }

        if ($applyFixes && !empty($individualPuts)) {
            $tPut = microtime(true);
            foreach ($individualPuts as $idx => $put) {
                $response = $this->api->updateProduct($put['yuju_id'], $put['payload']);
                if (!isset($pendingDetails[$idx])) {
                    continue;
                }
                $detail = &$pendingDetails[$idx];
                $diffs = [];
                if (!empty($detail['diffs_json'])) {
                    $decoded = json_decode($detail['diffs_json'], true);
                    if (is_array($decoded)) {
                        $diffs = $decoded;
                    }
                }
                $fields = array_values(array_filter(array_map(static function ($d) {
                    return is_array($d) && isset($d['field']) ? $d['field'] : null;
                }, $diffs)));

                if (!empty($response['success'])) {
                    $detail['result'] = 'diff_fixed';
                    $detail['message'] = 'Corregido en Yuju: ' . implode(', ', $fields);
                    $this->appendPriceSecurityNote($detail);
                } else {
                    $detail['result'] = 'diff_error';
                    $msg = $response['message'] ?? ('HTTP ' . ($response['http_code'] ?? '?'));
                    $detail['message'] = 'Error al corregir en Yuju: ' . $msg;
                    $this->appendPriceSecurityNote($detail);
                    $detail['error_detail'] = [
                        'type' => 'yuju_api',
                        'http_code' => $response['http_code'] ?? null,
                        'message' => $msg,
                        'response' => $response['data'] ?? ($response['raw'] ?? $response),
                        'payload' => $put['payload'],
                        'yuju_product_id' => $put['yuju_id'],
                    ];
                    $diffs[] = ['_error' => $detail['error_detail']];
                    $detail['diffs_json'] = json_encode($diffs, JSON_UNESCAPED_UNICODE);
                }
                unset($detail);
            }
            $putMs = (int) round((microtime(true) - $tPut) * 1000);
        }

        $uiRows = [];
        foreach ($pendingDetails as $detail) {
            $result = $detail['result'];
            if ($result === 'matched') {
                $counters['matched']++;
            } elseif ($result === 'not_found') {
                $counters['not_found']++;
            } elseif ($result === 'diff_fixed') {
                $counters['diff_found']++;
                $counters['fixed']++;
                ++$diffCount;
            } elseif ($result === 'diff_found') {
                $counters['diff_found']++;
                ++$diffCount;
            } else {
                $counters['diff_found']++;
                $counters['errors']++;
                ++$diffCount;
            }
            // No streamer filas "matched" al navegador (JSON/DOM enorme). KPI + export usan BD.
            if ($result !== 'matched') {
                $uiRows[] = $this->formatRowForUi($detail);
            }
        }

        $tDb = microtime(true);
        $this->insertDetailsBatch($pendingDetails);

        $processed = $offset + count($slice);
        $total = (int) $run['total'];
        $finished = $processed >= $total;
        $now = date('Y-m-d H:i:s');
        $duration = $this->computeDurationSeconds($run['started_at'], $now, $run);

        $update = [
            'processed' => (int) $processed,
            'matched' => (int) $counters['matched'],
            'diff_found' => (int) $counters['diff_found'],
            'fixed' => (int) $counters['fixed'],
            'errors' => (int) $counters['errors'],
            'not_found' => (int) $counters['not_found'],
            'updated_at' => pSQL($now),
            'duration_seconds' => (int) $duration,
        ];

        if ($finished) {
            $update['status'] = 'completed';
            $update['finished_at'] = pSQL($now);
            YujuConfig::set('YUJU_AUDIT_LAST_RUN_AT', $now, 'string');
            $this->cleanupRunOffers($runId);
        }

        Db::getInstance()->update('yuju_audit_runs', $update, 'id = ' . (int) $runId);
        $dbMs = (int) round((microtime(true) - $tDb) * 1000);

        return [
            'success' => true,
            'finished' => $finished,
            'paused' => false,
            'progress' => $this->getProgress($runId),
            'rows' => $uiRows,
            'chunk' => [
                'size' => count($slice),
                'diffs' => $diffCount,
            ],
            'timing' => [
                'compare_ms' => $compareMs,
                'mass_ms' => $massMs,
                'put_ms' => $putMs,
                'db_ms' => $dbMs,
                'offers_mass' => $offersMassCount,
                'puts' => $putsCount,
            ],
        ];
    }

    /**
     * Envía correcciones stock/precio por POST /products-offer y espera cierre de tarea.
     *
     * @param array $offers
     *
     * @return array{success:bool,sku_ok:array,sku_err:array,error:?string}
     */
    protected function applyMassOfferFixes(array $offers)
    {
        $empty = ['success' => false, 'sku_ok' => [], 'sku_err' => [], 'error' => null];
        if (empty($offers)) {
            $empty['success'] = true;

            return $empty;
        }

        $submit = $this->api->massUpdateOffers($offers);
        if (!empty($submit['rejected']) && !empty($submit['current_id_task'])) {
            // Esperar a que termine la tarea en curso y reintentar una vez
            $this->waitMassOfferTask((string) $submit['current_id_task'], 90);
            $submit = $this->api->massUpdateOffers($offers);
        }

        if (empty($submit['success'])) {
            // Fallback: PUT individual (más lento, pero no deja sin corregir)
            $this->logger->warning('Mass offer update falló, fallback a PUT individual', [
                'message' => $submit['message'] ?? 'unknown',
                'count' => count($offers),
            ]);

            return $this->fallbackIndividualOfferPuts($offers);
        }

        $idTask = $submit['id_task'] ?? null;
        if (!$idTask) {
            // SKIPPED o aceptado sin task
            if (($submit['data']['status'] ?? '') === 'SKIPPED') {
                return ['success' => true, 'sku_ok' => [], 'sku_err' => [], 'error' => null];
            }
            // Sin id_task pero CREATED raro: marcar OK optimista
            $ok = [];
            foreach ($offers as $o) {
                if (!empty($o['sku'])) {
                    $ok[(string) $o['sku']] = true;
                }
            }

            return ['success' => true, 'sku_ok' => $ok, 'sku_err' => [], 'error' => null];
        }

        $status = $this->waitMassOfferTask((string) $idTask, 120);
        if (empty($status['success'])) {
            return [
                'success' => false,
                'sku_ok' => [],
                'sku_err' => [],
                'error' => $status['message'] ?? 'Timeout esperando products-offer',
            ];
        }

        $data = $status['data'] ?? [];
        $taskStatus = strtoupper((string) ($data['status'] ?? ''));
        $skuOk = [];
        $skuErr = [];

        if (!empty($data['details']) && is_array($data['details'])) {
            foreach ($data['details'] as $row) {
                if (empty($row['sku'])) {
                    continue;
                }
                $sku = (string) $row['sku'];
                $errs = [];
                if (!empty($row['errors']) && is_array($row['errors'])) {
                    $errs = $row['errors'];
                }
                if (!empty($errs)) {
                    $skuErr[$sku] = implode('; ', array_map('strval', $errs));
                } else {
                    $skuOk[$sku] = true;
                }
            }
        }

        // SKUs sin mención en details → éxito si tarea COMPLETED
        if (in_array($taskStatus, ['COMPLETED', 'COMPLETE', 'DONE', 'SUCCESS'], true) || ($data['errors'] ?? 0) == 0) {
            foreach ($offers as $o) {
                if (empty($o['sku'])) {
                    continue;
                }
                $sku = (string) $o['sku'];
                if (!isset($skuErr[$sku])) {
                    $skuOk[$sku] = true;
                }
            }
        }

        return [
            'success' => empty($skuErr) || !empty($skuOk),
            'sku_ok' => $skuOk,
            'sku_err' => $skuErr,
            'error' => null,
            'id_task' => $idTask,
            'task_status' => $taskStatus,
        ];
    }

    /**
     * @param string $idTask
     * @param int $maxSeconds
     *
     * @return array
     */
    protected function waitMassOfferTask($idTask, $maxSeconds = 120)
    {
        $deadline = time() + max(5, (int) $maxSeconds);
        $last = null;
        while (time() <= $deadline) {
            $last = $this->api->getMassUpdateOffersStatus($idTask);
            if (empty($last['success'])) {
                usleep(1500000);
                continue;
            }
            $st = strtoupper((string) ($last['data']['status'] ?? ''));
            if (in_array($st, ['COMPLETED', 'COMPLETE', 'DONE', 'SUCCESS', 'FAILED', 'ERROR', 'REJECTED'], true)) {
                if (in_array($st, ['FAILED', 'ERROR', 'REJECTED'], true)) {
                    return [
                        'success' => false,
                        'message' => 'Tarea products-offer: ' . $st,
                        'data' => $last['data'] ?? null,
                    ];
                }

                return $last;
            }
            usleep(1500000);
        }

        return [
            'success' => false,
            'message' => 'Timeout esperando products-offer/' . $idTask,
            'data' => $last['data'] ?? null,
        ];
    }

    /**
     * Fallback lento: un PUT por SKU (solo stock/precio).
     *
     * @param array $offers
     *
     * @return array
     */
    protected function fallbackIndividualOfferPuts(array $offers)
    {
        $skuOk = [];
        $skuErr = [];
        foreach ($offers as $o) {
            if (empty($o['sku'])) {
                continue;
            }
            $sku = (string) $o['sku'];
            $yujuId = !empty($o['_yuju_id']) ? (string) $o['_yuju_id'] : null;
            if (!$yujuId) {
                $yujuId = Db::getInstance()->getValue(
                    'SELECT yuju_product_id FROM `' . _DB_PREFIX_ . 'yuju_product_status` yps
                     INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = yps.prestashop_product_id
                     WHERE p.reference = \'' . pSQL($sku) . '\' AND yps.yuju_product_id IS NOT NULL AND yps.yuju_product_id != \'\''
                );
            }
            if (!$yujuId) {
                $skuErr[$sku] = 'Sin ID Yuju para fallback PUT';
                continue;
            }
            $payload = [];
            if (isset($o['stock'])) {
                $payload['stock'] = (int) $o['stock'];
            }
            if (isset($o['price'])) {
                $payload['price'] = (float) $o['price'];
            }
            $resp = $this->api->updateProduct($yujuId, $payload);
            if (!empty($resp['success'])) {
                $skuOk[$sku] = true;
            } else {
                $skuErr[$sku] = $resp['message'] ?? ('HTTP ' . ($resp['http_code'] ?? '?'));
            }
        }

        return [
            'success' => !empty($skuOk),
            'sku_ok' => $skuOk,
            'sku_err' => $skuErr,
            'error' => empty($skuOk) ? 'Fallback PUT falló para todos' : null,
        ];
    }

    /**
     * Pausa un run en curso.
     *
     * @param int $runId
     *
     * @return array
     */
    public function pauseRun($runId)
    {
        $run = $this->getRun((int) $runId);
        if (!$run) {
            return ['success' => false, 'message' => 'Run no encontrado.'];
        }
        if ($run['status'] !== 'running') {
            return ['success' => false, 'message' => 'Solo se pueden pausar auditorías en ejecución.'];
        }

        $summary = [];
        if (!empty($run['summary_json'])) {
            $decoded = json_decode($run['summary_json'], true);
            if (is_array($decoded)) {
                $summary = $decoded;
            }
        }
        $summary['paused_at'] = date('Y-m-d H:i:s');
        $summary['pause_accum'] = (int) ($summary['pause_accum'] ?? 0);

        Db::getInstance()->update('yuju_audit_runs', [
            'status' => 'paused',
            'summary_json' => pSQL(json_encode($summary, JSON_UNESCAPED_UNICODE), true),
            'duration_seconds' => (int) $this->computeDurationSeconds($run['started_at'], date('Y-m-d H:i:s'), $run),
            'updated_at' => pSQL(date('Y-m-d H:i:s')),
        ], 'id = ' . (int) $runId);

        return ['success' => true, 'progress' => $this->getProgress((int) $runId)];
    }

    /**
     * Reanuda un run pausado.
     *
     * @param int $runId
     *
     * @return array
     */
    public function resumeRun($runId)
    {
        $run = $this->getRun((int) $runId);
        if (!$run) {
            return ['success' => false, 'message' => 'Run no encontrado.'];
        }
        if ($run['status'] !== 'paused') {
            return ['success' => false, 'message' => 'La auditoría no está pausada.'];
        }

        $summary = [];
        if (!empty($run['summary_json'])) {
            $decoded = json_decode($run['summary_json'], true);
            if (is_array($decoded)) {
                $summary = $decoded;
            }
        }
        if (!empty($summary['paused_at'])) {
            $pausedSec = max(0, time() - strtotime($summary['paused_at']));
            $summary['pause_accum'] = (int) ($summary['pause_accum'] ?? 0) + $pausedSec;
            unset($summary['paused_at']);
        }

        Db::getInstance()->update('yuju_audit_runs', [
            'status' => 'running',
            'summary_json' => pSQL(json_encode($summary, JSON_UNESCAPED_UNICODE), true),
            'updated_at' => pSQL(date('Y-m-d H:i:s')),
        ], 'id = ' . (int) $runId);

        return ['success' => true, 'progress' => $this->getProgress((int) $runId)];
    }

    /**
     * Exporta detalles del run a CSV (Excel-compatible).
     *
     * @param int $runId
     * @param string|null $resultFilter
     * @param string $search
     *
     * @return array{success:bool,csv?:string,filename?:string,message?:string}
     */
    public function exportRunCsv($runId, $resultFilter = null, $search = '')
    {
        $run = $this->getRun((int) $runId);
        if (!$run) {
            return ['success' => false, 'message' => 'Run no encontrado.'];
        }

        $details = self::getRunDetails((int) $runId, $resultFilter, 100000, 0, $search);
        $fh = fopen('php://temp', 'r+');
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fh, [
            'SKU', 'Nombre', 'Resultado', 'ID PS', 'ID Yuju',
            'Stock PS', 'Stock Yuju', 'Precio PS', 'Precio Yuju',
            'Imgs PS', 'Imgs Yuju', 'Diferencias', 'Mensaje',
        ], ';');

        foreach ($details as $d) {
            fputcsv($fh, [
                $d['sku'] ?? '',
                $d['product_name'] ?? '',
                $d['result'] ?? '',
                $d['prestashop_product_id'] ?? '',
                $d['yuju_product_id'] ?? '',
                $d['ps_stock'] ?? '',
                $d['yuju_stock'] ?? '',
                $d['ps_price'] ?? '',
                $d['yuju_price'] ?? '',
                $d['ps_images_count'] ?? '',
                $d['yuju_images_count'] ?? '',
                $d['diffs_json'] ?? '',
                $d['message'] ?? '',
            ], ';');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return [
            'success' => true,
            'csv' => $csv,
            'filename' => 'yuju_auditoria_run_' . (int) $runId . '.csv',
        ];
    }

    /**
     * Ejecuta el run completo (cron silencioso).
     *
     * @param int $runId
     * @param int $maxSeconds
     * @return array
     */
    public function runUntilComplete($runId, $maxSeconds = 240)
    {
        $started = time();
        $chunkSize = (int) YujuConfig::get('YUJU_AUDIT_CHUNK_SIZE', 200);
        $last = null;

        while (true) {
            if ((time() - $started) >= $maxSeconds) {
                break;
            }
            $last = $this->processChunk($runId, $chunkSize);
            if (empty($last['success']) || !empty($last['finished']) || !empty($last['paused'])) {
                break;
            }
        }

        return $last ?: ['success' => false, 'message' => 'No se procesó ningún chunk.'];
    }

    /**
     * @param int $runId
     * @return array
     */
    public function getProgress($runId)
    {
        $run = $this->getRun((int) $runId);
        if (!$run) {
            return [
                'run_id' => (int) $runId,
                'status' => 'missing',
                'total' => 0,
                'processed' => 0,
                'percent' => 0,
            ];
        }

        $total = max(0, (int) $run['total']);
        $processed = max(0, (int) $run['processed']);
        $percent = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
        $endRef = !empty($run['finished_at']) ? $run['finished_at'] : date('Y-m-d H:i:s');
        $duration = isset($run['duration_seconds']) && $run['duration_seconds'] !== null
            ? (int) $run['duration_seconds']
            : $this->computeDurationSeconds($run['started_at'], $endRef, $run);

        return [
            'run_id' => (int) $run['id'],
            'status' => $run['status'],
            'trigger' => $run['trigger'],
            'mode' => $run['mode'],
            'audit_stock' => (int) $run['audit_stock'],
            'audit_price' => (int) $run['audit_price'],
            'audit_images' => (int) $run['audit_images'],
            'total' => $total,
            'processed' => $processed,
            'matched' => (int) $run['matched'],
            'diff_found' => (int) $run['diff_found'],
            'fixed' => (int) $run['fixed'],
            'errors' => (int) $run['errors'],
            'not_found' => (int) $run['not_found'],
            'percent' => $percent,
            'started_at' => $run['started_at'],
            'finished_at' => $run['finished_at'],
            'duration_seconds' => $duration,
            'duration_human' => $this->formatDuration($duration),
            'error_message' => $run['error_message'],
        ];
    }

    /**
     * @param int $runId
     * @return array|false
     */
    public function getRun($runId)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_audit_runs` WHERE `id` = ' . (int) $runId
        );
    }

    /**
     * Estadísticas agregadas para monitoreo.
     *
     * @param int $limit
     * @return array
     */
    public static function getMonitoringStats($limit = 30)
    {
        $limit = max(1, min(100, (int) $limit));
        $runs = Db::getInstance()->executeS(
            'SELECT `id`, `trigger`, `mode`, `status`, `total`, `processed`, `matched`, `diff_found`, `fixed`, `errors`, `not_found`,
                    `audit_stock`, `audit_price`, `audit_images`, `started_at`, `finished_at`, `duration_seconds`
             FROM `' . _DB_PREFIX_ . 'yuju_audit_runs`
             ORDER BY `id` DESC
             LIMIT ' . (int) $limit
        );
        if (!is_array($runs)) {
            $runs = [];
        }
        foreach ($runs as &$runRow) {
            $dur = isset($runRow['duration_seconds']) ? (int) $runRow['duration_seconds'] : 0;
            if ($dur <= 0 && !empty($runRow['started_at'])) {
                $end = !empty($runRow['finished_at']) ? strtotime($runRow['finished_at']) : time();
                $start = strtotime($runRow['started_at']);
                if ($start) {
                    $dur = max(0, $end - $start);
                }
            }
            $runRow['duration_seconds'] = $dur;
            $m = floor($dur / 60);
            $s = $dur % 60;
            $runRow['duration_human'] = $m . 'm ' . str_pad((string) $s, 2, '0', STR_PAD_LEFT) . 's';
        }
        unset($runRow);

        $diffTypes = Db::getInstance()->getRow(
            'SELECT
                SUM(CASE WHEN diffs_json LIKE \'%\"field\":\"stock\"%\' THEN 1 ELSE 0 END) AS stock_diffs,
                SUM(CASE WHEN diffs_json LIKE \'%\"field\":\"price\"%\' THEN 1 ELSE 0 END) AS price_diffs,
                SUM(CASE WHEN diffs_json LIKE \'%\"field\":\"images\"%\' THEN 1 ELSE 0 END) AS images_diffs
             FROM `' . _DB_PREFIX_ . 'yuju_audit_run_details`
             WHERE `result` IN (\'diff_fixed\', \'diff_error\')'
        );

        $totals = Db::getInstance()->getRow(
            'SELECT
                COUNT(*) AS runs_count,
                COALESCE(SUM(matched), 0) AS sum_matched,
                COALESCE(SUM(diff_found), 0) AS sum_diff,
                COALESCE(SUM(fixed), 0) AS sum_fixed,
                COALESCE(SUM(errors), 0) AS sum_errors
             FROM `' . _DB_PREFIX_ . 'yuju_audit_runs`
             WHERE `status` = \'completed\''
        );

        $sumDiff = (int) ($totals['sum_diff'] ?? 0);
        $sumFixed = (int) ($totals['sum_fixed'] ?? 0);
        $fixRate = $sumDiff > 0 ? round(($sumFixed / $sumDiff) * 100, 1) : 100;

        return [
            'runs' => $runs,
            'totals' => [
                'runs_count' => (int) ($totals['runs_count'] ?? 0),
                'sum_matched' => (int) ($totals['sum_matched'] ?? 0),
                'sum_diff' => $sumDiff,
                'sum_fixed' => $sumFixed,
                'sum_errors' => (int) ($totals['sum_errors'] ?? 0),
                'fix_rate' => $fixRate,
            ],
            'diff_types' => [
                'stock' => (int) ($diffTypes['stock_diffs'] ?? 0),
                'price' => (int) ($diffTypes['price_diffs'] ?? 0),
                'images' => (int) ($diffTypes['images_diffs'] ?? 0),
            ],
        ];
    }

    /**
     * @param int $runId
     * @param string|null $resultFilter
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getRunDetails($runId, $resultFilter = null, $limit = 100, $offset = 0, $search = '')
    {
        $where = 'id_audit_run = ' . (int) $runId;
        $allowed = ['matched', 'diff_fixed', 'diff_found', 'diff_error', 'not_found'];
        if ($resultFilter === 'diff') {
            $where .= ' AND result IN (\'diff_fixed\',\'diff_found\',\'diff_error\')';
        } elseif ($resultFilter && in_array($resultFilter, $allowed, true)) {
            $where .= ' AND result = \'' . pSQL($resultFilter) . '\'';
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $like = pSQL('%' . $search . '%');
            $where .= ' AND (sku LIKE \'' . $like . '\' OR product_name LIKE \'' . $like . '\' OR message LIKE \'' . $like . '\')';
        }

        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_audit_run_details`
             WHERE ' . $where . '
             ORDER BY id ASC
             LIMIT ' . (int) $offset . ', ' . (int) $limit
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Carga ofertas desde cache o API offer-report.
     * Prefiere cache local (compartido con sync_products). Si la descarga
     * CloudFront falla (403), reutiliza cache aunque esté vencido.
     *
     * @return array
     */
    protected function loadOffers()
    {
        $cacheFile = $this->cacheDir . self::CACHE_OFFERS_FILE;
        $fromCache = false;
        $cacheAge = null;
        $offers = null;
        $fetchMessage = null;

        if (file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            if ($cacheAge <= self::CACHE_MAX_AGE) {
                $decoded = $this->readOffersFromFile($cacheFile);
                if ($decoded !== null) {
                    $offers = $decoded;
                    $fromCache = true;
                }
            }
        }

        if ($offers === null) {
            $fetched = $this->fetchOfferReport();
            if (!empty($fetched['success']) && is_array($fetched['offers'])) {
                $offers = $fetched['offers'];
                @file_put_contents($cacheFile, json_encode($offers, JSON_UNESCAPED_UNICODE));
                $fromCache = false;
                $cacheAge = 0;
            } else {
                $fetchMessage = $fetched['message'] ?? 'No se pudo descargar el reporte de ofertas.';
                // Fallback: cualquier cache usable (aunque > 12h)
                $decoded = $this->readOffersFromFile($cacheFile);
                if ($decoded !== null) {
                    $offers = $decoded;
                    $fromCache = true;
                    $cacheAge = file_exists($cacheFile) ? (time() - filemtime($cacheFile)) : null;
                    $this->logger->warning('Auditoría usando cache de respaldo: ' . $fetchMessage);
                } else {
                    return [
                        'success' => false,
                        'message' => $fetchMessage . ' Ejecute primero la sincronización de productos o espere a que el offer-report esté disponible.',
                    ];
                }
            }
        }

        $offers = $this->normalizeOffersList($offers);
        if ($offers === null) {
            return ['success' => false, 'message' => 'Formato de ofertas inválido.'];
        }

        return [
            'success' => true,
            'offers' => $offers,
            'from_cache' => $fromCache,
            'cache_age' => $cacheAge,
            'fetch_warning' => $fromCache && $fetchMessage ? $fetchMessage : null,
        ];
    }

    /**
     * @param string $file
     *
     * @return array|null
     */
    protected function readOffersFromFile($file)
    {
        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        return $decoded;
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
     * Descarga offer-report (mismo flujo que sync_products + reintento 403).
     * CloudFront usa URL firmada: no enviar Authorization.
     *
     * @return array
     */
    protected function fetchOfferReport()
    {
        $oauth = new YujuOAuth();
        $token = $oauth->getValidAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No hay token OAuth válido.'];
        }

        $requestUrl = 'https://api.tp.yuju.io/products-offer-report';
        $urlResult = $this->requestOfferReportUrl($requestUrl, $token);
        if (empty($urlResult['success'])) {
            return $urlResult;
        }

        $downloadUrl = $urlResult['url'];
        $download = $this->downloadOfferJson($downloadUrl);

        // Reintento como en sync_products: 403 → nueva URL → descargar otra vez
        if ((int) ($download['http_code'] ?? 0) === 403) {
            $this->logger->warning('CloudFront 403 en offer-report, reintentando con nueva URL...');
            sleep(2);
            $urlResult = $this->requestOfferReportUrl($requestUrl, $token);
            if (!empty($urlResult['success'])) {
                $downloadUrl = $urlResult['url'];
                $download = $this->downloadOfferJson($downloadUrl);
            }
        }

        if ((int) ($download['http_code'] ?? 0) !== 200 || empty($download['content'])) {
            return [
                'success' => false,
                'message' => 'Error al descargar JSON de ofertas (HTTP ' . (int) ($download['http_code'] ?? 0) . ').',
            ];
        }

        $offers = json_decode($download['content'], true);
        if (!is_array($offers)) {
            return ['success' => false, 'message' => 'JSON de ofertas inválido.'];
        }

        return ['success' => true, 'offers' => $offers];
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

        if ($httpCode === 200 && isset($apiResponse['message']) && strpos($apiResponse['message'], 'El reporte se puede procesar cada') !== false) {
            return [
                'success' => false,
                'message' => 'API bloqueada: ' . $apiResponse['message'],
                'blocked' => true,
            ];
        }

        if ($httpCode !== 200 || empty($apiResponse['url'])) {
            $detail = is_array($apiResponse) ? json_encode($apiResponse, JSON_UNESCAPED_UNICODE) : (string) $response;

            return [
                'success' => false,
                'message' => 'Error al solicitar offer-report (HTTP ' . $httpCode . '): ' . ($curlError ?: substr($detail, 0, 180)),
            ];
        }

        return ['success' => true, 'url' => $apiResponse['url']];
    }

    /**
     * Descarga JSON desde CloudFront (signed URL). Sin Authorization.
     *
     * @param string $downloadUrl
     *
     * @return array{http_code:int,content:string|false,error:string}
     */
    protected function downloadOfferJson($downloadUrl)
    {
        $attempts = [
            // Igual que sync_products
            [
                'Accept: application/json',
                'User-Agent: PrestaShop-Yuju-Module/1.0',
            ],
            // Algunos CDN firman rechazo con Accept "application/json"
            [
                'User-Agent: PrestaShop-Yuju-Module/1.0',
            ],
            // Último recurso: sin headers custom
            [],
        ];

        $lastCode = 0;
        $lastContent = false;
        $lastError = '';

        foreach ($attempts as $headers) {
            $ch = curl_init();
            $opts = [
                CURLOPT_URL => $downloadUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_ENCODING => '',
            ];
            if (!empty($headers)) {
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
            curl_setopt_array($ch, $opts);
            $content = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $lastCode = $code;
            $lastContent = $content;
            $lastError = $error;

            if ($code === 200 && !empty($content)) {
                return ['http_code' => $code, 'content' => $content, 'error' => ''];
            }
            // Si no es 403, no tiene sentido rotar headers
            if ($code !== 403 && $code !== 0) {
                break;
            }
        }

        return ['http_code' => $lastCode, 'content' => $lastContent, 'error' => $lastError];
    }

    /**
     * Audita una oferta individual y corrige en Yuju si hay diferencias.
     *
     * @param array $offer
     * @param int $auditStock
     * @param int $auditPrice
     * @param int $auditImages
     * @param int $idLang
     * @param bool $applyFixes si false, solo reporta (diff_found) sin PUT a Yuju
     *
     * @return array detail row (sin id_audit_run aún)
     */
    protected function auditSingleOffer(array $offer, $auditStock, $auditPrice, $auditImages, $idLang, $applyFixes = true)
    {
        $sku = $offer['sku'] ?? $offer['sku_simple'] ?? null;
        $yujuId = $offer['id'] ?? $offer['id_product'] ?? null;

        $detail = [
            'prestashop_product_id' => null,
            'yuju_product_id' => $yujuId ? (string) $yujuId : null,
            'sku' => $sku ? (string) $sku : null,
            'sku_simple' => isset($offer['sku_simple']) ? (string) $offer['sku_simple'] : null,
            'parent_id' => isset($offer['parent_id'])
                ? (string) $offer['parent_id']
                : (isset($offer['id_product']) ? (string) $offer['id_product'] : null),
            'ps_stock' => null,
            'yuju_stock' => isset($offer['stock']) ? (int) $offer['stock'] : null,
            'ps_price' => null,
            'yuju_price' => isset($offer['price']) ? (float) $offer['price'] : null,
            'ps_images_count' => null,
            'yuju_images_count' => null,
            'diffs_json' => null,
            'result' => 'matched',
            'message' => '',
        ];

        $idProduct = $this->resolvePrestaShopProductId($sku, $yujuId);
        if (!$idProduct) {
            $detail['result'] = 'not_found';
            $detail['message'] = 'Producto no encontrado en PrestaShop';
            return $detail;
        }

        $detail['prestashop_product_id'] = (int) $idProduct;
        if (!$detail['yuju_product_id']) {
            $mapped = Db::getInstance()->getValue(
                'SELECT yuju_product_id FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                 WHERE prestashop_product_id = ' . (int) $idProduct . ' AND yuju_product_id IS NOT NULL AND yuju_product_id != \'\''
            );
            if ($mapped) {
                $detail['yuju_product_id'] = (string) $mapped;
                $yujuId = $mapped;
            }
        }

        $psStock = (int) StockAvailable::getQuantityAvailableByProduct((int) $idProduct);
        $product = new Product((int) $idProduct, false, $idLang);
        $psPrice = (float) $product->price;

        $detail['ps_stock'] = $psStock;
        $detail['ps_price'] = $psPrice;

        $diffs = [];
        $updatePayload = [];

        if ($auditStock) {
            $yujuStock = (int) ($offer['stock'] ?? 0);
            $detail['yuju_stock'] = $yujuStock;
            if ($psStock !== $yujuStock) {
                $diffs[] = [
                    'field' => 'stock',
                    'yuju' => $yujuStock,
                    'prestashop' => $psStock,
                ];
                $updatePayload['stock'] = $psStock;
            }
        }

        if ($auditPrice) {
            $yujuPrice = (float) ($offer['price'] ?? 0);
            $detail['yuju_price'] = $yujuPrice;
            if (abs($psPrice - $yujuPrice) > 0.01) {
                $diffs[] = [
                    'field' => 'price',
                    'yuju' => $yujuPrice,
                    'prestashop' => $psPrice,
                ];
                if ($this->isAbruptPriceChangeBlocked($psPrice, $yujuPrice)) {
                    $detail['price_update_blocked'] = true;
                } else {
                    $updatePayload['price'] = $psPrice;
                }
            }
        }

        if ($auditImages) {
            $psImages = $this->getPrestaShopImageUrls((int) $idProduct, $idLang, $product);
            $detail['ps_images_count'] = count($psImages);

            $yujuImages = $this->getYujuImageUrls($yujuId, $offer);
            $detail['yuju_images_count'] = count($yujuImages);

            if (!$this->imagesMatch($psImages, $yujuImages)) {
                $diffs[] = [
                    'field' => 'images',
                    'yuju' => count($yujuImages),
                    'prestashop' => count($psImages),
                ];
                if (!empty($psImages)) {
                    $updatePayload['images'] = $psImages;
                }
            }
        }

        if (empty($diffs)) {
            $detail['result'] = 'matched';
            $detail['message'] = 'Sin diferencias';
            return $detail;
        }

        $detail['diffs_json'] = json_encode($diffs, JSON_UNESCAPED_UNICODE);

        // Solo comparación (pruebas / reanálisis): no escribir en Yuju
        if (!$applyFixes) {
            $detail['result'] = 'diff_found';
            $detail['message'] = 'Diferencia detectada (solo comparación, no se corrigió Yuju): '
                . implode(', ', array_column($diffs, 'field'));
            $this->appendPriceSecurityNote($detail);

            return $detail;
        }

        if (!$yujuId) {
            $detail['result'] = 'diff_error';
            $detail['message'] = 'Hay diferencias pero falta ID de Yuju para corregir';
            $this->appendPriceSecurityNote($detail);
            return $detail;
        }

        if (empty($updatePayload)) {
            // Solo precio bloqueado por seguridad (0 o >50%): no llamar API
            if (!empty($detail['price_update_blocked'])) {
                $detail['result'] = 'diff_found';
                $detail['message'] = self::PRICE_SECURITY_MSG;
                return $detail;
            }
            $detail['result'] = 'diff_error';
            $detail['message'] = 'Hay diferencias pero no hay payload de corrección';
            return $detail;
        }

        // Nunca modificar PrestaShop: solo PUT a Yuju con valores PS
        $response = $this->api->updateProduct($yujuId, $updatePayload);
        if (!empty($response['success'])) {
            $detail['result'] = 'diff_fixed';
            $fieldsFixed = array_keys($updatePayload);
            $detail['message'] = 'Corregido en Yuju: ' . implode(', ', $fieldsFixed);
            $this->appendPriceSecurityNote($detail);
        } else {
            $detail['result'] = 'diff_error';
            $detail['message'] = 'Error al corregir en Yuju: ' . ($response['message'] ?? 'HTTP ' . ($response['http_code'] ?? '?'));
            $this->appendPriceSecurityNote($detail);
        }

        return $detail;
    }

    /**
     * @param string|null $sku
     * @param string|null $yujuId
     * @return int|null
     */
    protected function resolvePrestaShopProductId($sku, $yujuId)
    {
        if ($sku) {
            $id = (int) Db::getInstance()->getValue(
                'SELECT id_product FROM `' . _DB_PREFIX_ . 'product` WHERE reference = \'' . pSQL($sku) . '\''
            );
            if ($id > 0) {
                return $id;
            }
            // Combinaciones: reference en product_attribute
            $id = (int) Db::getInstance()->getValue(
                'SELECT id_product FROM `' . _DB_PREFIX_ . 'product_attribute` WHERE reference = \'' . pSQL($sku) . '\''
            );
            if ($id > 0) {
                return $id;
            }
        }

        if ($yujuId) {
            $id = (int) Db::getInstance()->getValue(
                'SELECT prestashop_product_id FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                 WHERE yuju_product_id = \'' . pSQL((string) $yujuId) . '\''
            );
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    protected function getPrestaShopImageUrls($idProduct, $idLang, Product $product)
    {
        $images = Image::getImages($idLang, $idProduct);
        if (empty($images)) {
            return [];
        }

        $urls = [];
        $context = Context::getContext();
        $linkRewrite = is_array($product->link_rewrite)
            ? ($product->link_rewrite[$idLang] ?? reset($product->link_rewrite))
            : $product->link_rewrite;

        foreach ($images as $image) {
            $urls[] = $context->link->getImageLink($linkRewrite, $image['id_image'], 'large_default');
        }

        return $urls;
    }

    /**
     * @param string|null $yujuId
     * @param array $offer
     * @return string[]
     */
    protected function getYujuImageUrls($yujuId, array $offer)
    {
        if (!empty($offer['images']) && is_array($offer['images'])) {
            return $this->normalizeImageList($offer['images']);
        }

        if (!$yujuId) {
            return [];
        }

        try {
            $response = $this->api->getProduct($yujuId);
            if (empty($response['success'])) {
                return [];
            }
            $data = $response['data'] ?? [];
            if (isset($data['images']) && is_array($data['images'])) {
                return $this->normalizeImageList($data['images']);
            }
            if (isset($data['product']['images']) && is_array($data['product']['images'])) {
                return $this->normalizeImageList($data['product']['images']);
            }
        } catch (Exception $e) {
            $this->logger->warning('No se pudieron obtener imágenes Yuju: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * @param array $images
     * @return string[]
     */
    protected function normalizeImageList(array $images)
    {
        $urls = [];
        foreach ($images as $img) {
            if (is_string($img) && $img !== '') {
                $urls[] = $img;
            } elseif (is_array($img)) {
                $url = $img['url'] ?? $img['src'] ?? $img['link'] ?? null;
                if ($url) {
                    $urls[] = (string) $url;
                }
            }
        }
        return $urls;
    }

    /**
     * Compara por cantidad y basename de URLs (ignora query/CDN host).
     *
     * @param string[] $ps
     * @param string[] $yuju
     * @return bool
     */
    protected function imagesMatch(array $ps, array $yuju)
    {
        if (count($ps) !== count($yuju)) {
            return false;
        }
        if (count($ps) === 0) {
            return true;
        }

        $norm = function ($url) {
            $path = parse_url($url, PHP_URL_PATH);
            return strtolower(basename($path ?: $url));
        };

        $a = array_map($norm, $ps);
        $b = array_map($norm, $yuju);
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * Versión rápida: usa mapa precargado (sin N+1 queries) y evita ObjectModel salvo PUT.
     *
     * @param array $offer
     * @param int $auditStock
     * @param int $auditPrice
     * @param int $auditImages
     * @param int $idLang
     * @param bool $applyFixes
     * @param array $ctx
     *
     * @return array
     */
    protected function auditSingleOfferFast(array $offer, $auditStock, $auditPrice, $auditImages, $idLang, $applyFixes, array $ctx)
    {
        $sku = isset($offer['sku']) ? (string) $offer['sku'] : (isset($offer['sku_simple']) ? (string) $offer['sku_simple'] : null);
        $yujuId = $offer['id'] ?? ($offer['id_product'] ?? null);
        $name = isset($offer['name']) ? (string) $offer['name'] : '';

        $detail = [
            'prestashop_product_id' => null,
            'yuju_product_id' => $yujuId ? (string) $yujuId : null,
            'sku' => $sku,
            'sku_simple' => isset($offer['sku_simple']) ? (string) $offer['sku_simple'] : null,
            'parent_id' => isset($offer['parent_id'])
                ? (string) $offer['parent_id']
                : (isset($offer['id_product']) ? (string) $offer['id_product'] : null),
            'product_name' => $name,
            'ps_stock' => null,
            'yuju_stock' => isset($offer['stock']) ? (int) $offer['stock'] : null,
            'ps_price' => null,
            'yuju_price' => isset($offer['price']) ? (float) $offer['price'] : null,
            'ps_images_count' => null,
            'yuju_images_count' => null,
            'diffs_json' => null,
            'result' => 'matched',
            'message' => '',
            'error_detail' => null,
        ];

        $idProduct = null;
        if ($sku !== null && $sku !== '' && isset($ctx['sku_map'][$sku])) {
            $idProduct = (int) $ctx['sku_map'][$sku];
        }
        if (!$idProduct && $yujuId && isset($ctx['yuju_map'][(string) $yujuId])) {
            $idProduct = (int) $ctx['yuju_map'][(string) $yujuId];
        }
        if (!$idProduct) {
            $detail['result'] = 'not_found';
            $detail['message'] = 'Producto no encontrado en PrestaShop';
            return $detail;
        }

        $detail['prestashop_product_id'] = $idProduct;
        if (!$detail['yuju_product_id'] && isset($ctx['ps_yuju_map'][$idProduct])) {
            $detail['yuju_product_id'] = (string) $ctx['ps_yuju_map'][$idProduct];
            $yujuId = $detail['yuju_product_id'];
        }
        if ($name === '' && isset($ctx['names'][$idProduct])) {
            $detail['product_name'] = (string) $ctx['names'][$idProduct];
        }

        $psStock = isset($ctx['stocks'][$idProduct]) ? (int) $ctx['stocks'][$idProduct] : 0;
        $psPrice = isset($ctx['prices'][$idProduct]) ? (float) $ctx['prices'][$idProduct] : 0.0;
        $detail['ps_stock'] = $psStock;
        $detail['ps_price'] = $psPrice;

        $diffs = [];
        $updatePayload = [];

        if ($auditStock) {
            $yujuStock = (int) ($offer['stock'] ?? 0);
            $detail['yuju_stock'] = $yujuStock;
            if ($psStock !== $yujuStock) {
                $diffs[] = ['field' => 'stock', 'yuju' => $yujuStock, 'prestashop' => $psStock];
                $updatePayload['stock'] = $psStock;
            }
        }

        if ($auditPrice) {
            $yujuPrice = (float) ($offer['price'] ?? 0);
            $detail['yuju_price'] = $yujuPrice;
            if (abs($psPrice - $yujuPrice) > 0.01) {
                $diffs[] = ['field' => 'price', 'yuju' => $yujuPrice, 'prestashop' => $psPrice];
                if ($this->isAbruptPriceChangeBlocked($psPrice, $yujuPrice)) {
                    $detail['price_update_blocked'] = true;
                } else {
                    $updatePayload['price'] = $psPrice;
                }
            }
        }

        if ($auditImages) {
            $psImgCount = isset($ctx['image_counts'][$idProduct]) ? (int) $ctx['image_counts'][$idProduct] : 0;
            $yujuImages = [];
            if (!empty($offer['images']) && is_array($offer['images'])) {
                $yujuImages = $this->normalizeImageList($offer['images']);
            }
            $detail['ps_images_count'] = $psImgCount;
            $detail['yuju_images_count'] = count($yujuImages);

            if ($psImgCount !== count($yujuImages)) {
                $diffs[] = [
                    'field' => 'images',
                    'yuju' => count($yujuImages),
                    'prestashop' => $psImgCount,
                ];
                // Solo construir URLs si hay que empujar a Yuju
                if ($applyFixes && $psImgCount > 0) {
                    $updatePayload['images'] = $this->getPrestaShopImageUrlsFast($idProduct, $idLang, $ctx);
                }
            }
        }

        if (empty($diffs)) {
            $detail['result'] = 'matched';
            $detail['message'] = 'Sin diferencias';
            return $detail;
        }

        $detail['diffs_json'] = json_encode($diffs, JSON_UNESCAPED_UNICODE);

        if (!$applyFixes) {
            $detail['result'] = 'diff_found';
            $detail['message'] = 'Diferencia detectada: ' . implode(', ', array_column($diffs, 'field'));
            $this->appendPriceSecurityNote($detail);
            return $detail;
        }

        if (!$yujuId) {
            $detail['result'] = 'diff_error';
            $detail['message'] = 'Hay diferencias pero falta ID de Yuju para corregir';
            $detail['error_detail'] = ['type' => 'missing_yuju_id'];
            $this->appendPriceSecurityNote($detail);
            return $detail;
        }
        if (empty($updatePayload)) {
            if (!empty($detail['price_update_blocked'])) {
                $detail['result'] = 'diff_found';
                $detail['message'] = self::PRICE_SECURITY_MSG;
                return $detail;
            }
            $detail['result'] = 'diff_error';
            $detail['message'] = 'Hay diferencias pero no hay payload de corrección';
            $detail['error_detail'] = ['type' => 'empty_payload'];
            return $detail;
        }

        // No llamar API aquí: processChunkInner hace mass-update (stock/precio) o PUT (imágenes)
        $detail['_fix_payload'] = $updatePayload;
        $detail['result'] = 'diff_found';
        $detail['message'] = 'Pendiente corrección en Yuju: ' . implode(', ', array_keys($updatePayload));
        $this->appendPriceSecurityNote($detail);

        return $detail;
    }

    /**
     * Bloquea envío de precio si es 0 o el cambio relativo vs Yuju supera 50%.
     *
     * @param float $psPrice
     * @param float $yujuPrice
     *
     * @return bool
     */
    protected function isAbruptPriceChangeBlocked($psPrice, $yujuPrice)
    {
        $psPrice = (float) $psPrice;
        $yujuPrice = (float) $yujuPrice;
        if ($psPrice <= 0.0 || $yujuPrice <= 0.0) {
            return true;
        }

        return (abs($psPrice - $yujuPrice) / $yujuPrice) > 0.5;
    }

    /**
     * Añade al message (tooltip de estado) la nota de seguridad de precio.
     *
     * @param array $detail
     */
    protected function appendPriceSecurityNote(array &$detail)
    {
        if (empty($detail['price_update_blocked'])) {
            return;
        }
        $note = self::PRICE_SECURITY_MSG;
        $current = trim((string) ($detail['message'] ?? ''));
        if ($current === '' || stripos($current, $note) !== false) {
            $detail['message'] = $current !== '' ? $current : $note;
            return;
        }
        $detail['message'] = $current . ' · ' . $note;
    }

    /**
     * Precarga mapas SKU/stock/precio/imágenes para un lote.
     *
     * @param array $slice
     * @param int $idLang
     * @param int $idShop
     *
     * @return array
     */
    protected function preloadProductContext(array $slice, $idLang, $idShop)
    {
        $skus = [];
        $yujuIds = [];
        foreach ($slice as $offer) {
            if (!empty($offer['sku'])) {
                $skus[] = (string) $offer['sku'];
            }
            if (!empty($offer['sku_simple'])) {
                $skus[] = (string) $offer['sku_simple'];
            }
            if (!empty($offer['id'])) {
                $yujuIds[] = (string) $offer['id'];
            }
        }
        $skus = array_values(array_unique(array_filter($skus)));
        $yujuIds = array_values(array_unique(array_filter($yujuIds)));

        $ctx = [
            'sku_map' => [],
            'yuju_map' => [],
            'ps_yuju_map' => [],
            'stocks' => [],
            'prices' => [],
            'names' => [],
            'image_counts' => [],
            'link_rewrites' => [],
        ];

        if (!empty($skus)) {
            $in = implode(',', array_map(static function ($s) {
                return '\'' . pSQL($s) . '\'';
            }, $skus));

            $rows = Db::getInstance()->executeS(
                'SELECT id_product, reference, price FROM `' . _DB_PREFIX_ . 'product`
                 WHERE reference IN (' . $in . ')'
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $ctx['sku_map'][$r['reference']] = (int) $r['id_product'];
                    $ctx['prices'][(int) $r['id_product']] = (float) $r['price'];
                }
            }

            $rows = Db::getInstance()->executeS(
                'SELECT id_product, reference FROM `' . _DB_PREFIX_ . 'product_attribute`
                 WHERE reference IN (' . $in . ')'
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (!isset($ctx['sku_map'][$r['reference']])) {
                        $ctx['sku_map'][$r['reference']] = (int) $r['id_product'];
                    }
                }
            }
        }

        if (!empty($yujuIds)) {
            $in = implode(',', array_map(static function ($s) {
                return '\'' . pSQL($s) . '\'';
            }, $yujuIds));
            $rows = Db::getInstance()->executeS(
                'SELECT prestashop_product_id, yuju_product_id FROM `' . _DB_PREFIX_ . 'yuju_product_status`
                 WHERE yuju_product_id IN (' . $in . ')'
            );
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $ctx['yuju_map'][(string) $r['yuju_product_id']] = (int) $r['prestashop_product_id'];
                    $ctx['ps_yuju_map'][(int) $r['prestashop_product_id']] = (string) $r['yuju_product_id'];
                }
            }
        }

        $productIds = array_values(array_unique(array_filter(array_merge(
            array_values($ctx['sku_map']),
            array_values($ctx['yuju_map'])
        ))));

        if (empty($productIds)) {
            return $ctx;
        }

        $idList = implode(',', array_map('intval', $productIds));

        // Precios faltantes
        $rows = Db::getInstance()->executeS(
            'SELECT id_product, price FROM `' . _DB_PREFIX_ . 'product` WHERE id_product IN (' . $idList . ')'
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ctx['prices'][(int) $r['id_product']] = (float) $r['price'];
            }
        }

        // Stock (producto sin combinación)
        $rows = Db::getInstance()->executeS(
            'SELECT id_product, SUM(quantity) AS qty FROM `' . _DB_PREFIX_ . 'stock_available`
             WHERE id_product IN (' . $idList . ') AND id_product_attribute = 0
             GROUP BY id_product'
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ctx['stocks'][(int) $r['id_product']] = (int) $r['qty'];
            }
        }

        // Nombres
        $rows = Db::getInstance()->executeS(
            'SELECT id_product, name, link_rewrite FROM `' . _DB_PREFIX_ . 'product_lang`
             WHERE id_product IN (' . $idList . ') AND id_lang = ' . (int) $idLang
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ctx['names'][(int) $r['id_product']] = (string) $r['name'];
                $ctx['link_rewrites'][(int) $r['id_product']] = (string) $r['link_rewrite'];
            }
        }

        // Conteo de imágenes (rápido)
        $rows = Db::getInstance()->executeS(
            'SELECT id_product, COUNT(*) AS c FROM `' . _DB_PREFIX_ . 'image`
             WHERE id_product IN (' . $idList . ')
             GROUP BY id_product'
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ctx['image_counts'][(int) $r['id_product']] = (int) $r['c'];
            }
        }

        // Mapeo PS→Yuju restante
        $rows = Db::getInstance()->executeS(
            'SELECT prestashop_product_id, yuju_product_id FROM `' . _DB_PREFIX_ . 'yuju_product_status`
             WHERE prestashop_product_id IN (' . $idList . ')
               AND yuju_product_id IS NOT NULL AND yuju_product_id != \'\''
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ctx['ps_yuju_map'][(int) $r['prestashop_product_id']] = (string) $r['yuju_product_id'];
            }
        }

        return $ctx;
    }

    /**
     * @param int $idProduct
     * @param int $idLang
     * @param array $ctx
     *
     * @return string[]
     */
    protected function getPrestaShopImageUrlsFast($idProduct, $idLang, array $ctx)
    {
        $images = Image::getImages($idLang, $idProduct);
        if (empty($images)) {
            return [];
        }
        $linkRewrite = $ctx['link_rewrites'][$idProduct] ?? 'product';
        $link = Context::getContext()->link;
        $urls = [];
        foreach ($images as $image) {
            $urls[] = $link->getImageLink($linkRewrite, $image['id_image'], 'large_default');
        }

        return $urls;
    }

    protected function insertDetailsBatch(array $details)
    {
        if (empty($details)) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $chunks = array_chunk($details, 80);
        foreach ($chunks as $chunk) {
            $values = [];
            foreach ($chunk as $detail) {
                $values[] = '(' .
                    (int) $detail['id_audit_run'] . ',' .
                    ($detail['prestashop_product_id'] !== null ? (int) $detail['prestashop_product_id'] : 'NULL') . ',' .
                    ($detail['yuju_product_id'] !== null ? '\'' . pSQL($detail['yuju_product_id']) . '\'' : 'NULL') . ',' .
                    ($detail['sku'] !== null ? '\'' . pSQL($detail['sku']) . '\'' : 'NULL') . ',' .
                    (!empty($detail['product_name']) ? '\'' . pSQL($detail['product_name']) . '\'' : 'NULL') . ',' .
                    ($detail['ps_stock'] !== null ? (int) $detail['ps_stock'] : 'NULL') . ',' .
                    ($detail['yuju_stock'] !== null ? (int) $detail['yuju_stock'] : 'NULL') . ',' .
                    ($detail['ps_price'] !== null ? (float) $detail['ps_price'] : 'NULL') . ',' .
                    ($detail['yuju_price'] !== null ? (float) $detail['yuju_price'] : 'NULL') . ',' .
                    ($detail['ps_images_count'] !== null ? (int) $detail['ps_images_count'] : 'NULL') . ',' .
                    ($detail['yuju_images_count'] !== null ? (int) $detail['yuju_images_count'] : 'NULL') . ',' .
                    ($detail['diffs_json'] !== null ? '\'' . pSQL($detail['diffs_json'], true) . '\'' : 'NULL') . ',' .
                    '\'' . pSQL($detail['result']) . '\',' .
                    '\'' . pSQL($detail['message'], true) . '\',' .
                    '\'' . pSQL($now) . '\'' .
                ')';
            }

            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'yuju_audit_run_details`
                (`id_audit_run`,`prestashop_product_id`,`yuju_product_id`,`sku`,`product_name`,
                 `ps_stock`,`yuju_stock`,`ps_price`,`yuju_price`,`ps_images_count`,`yuju_images_count`,
                 `diffs_json`,`result`,`message`,`created_at`)
                VALUES ' . implode(',', $values);

            // Fallback si aún no existe product_name
            if (!Db::getInstance()->execute($sql)) {
                foreach ($chunk as $detail) {
                    $this->insertDetail($detail);
                }
            }
        }
    }

    protected function insertDetail(array $detail)
    {
        $row = [
            'id_audit_run' => (int) $detail['id_audit_run'],
            'prestashop_product_id' => $detail['prestashop_product_id'] !== null ? (int) $detail['prestashop_product_id'] : null,
            'yuju_product_id' => $detail['yuju_product_id'] !== null ? pSQL($detail['yuju_product_id']) : null,
            'sku' => $detail['sku'] !== null ? pSQL($detail['sku']) : null,
            'product_name' => !empty($detail['product_name']) ? pSQL($detail['product_name']) : null,
            'ps_stock' => $detail['ps_stock'] !== null ? (int) $detail['ps_stock'] : null,
            'yuju_stock' => $detail['yuju_stock'] !== null ? (int) $detail['yuju_stock'] : null,
            'ps_price' => $detail['ps_price'] !== null ? (float) $detail['ps_price'] : null,
            'yuju_price' => $detail['yuju_price'] !== null ? (float) $detail['yuju_price'] : null,
            'ps_images_count' => $detail['ps_images_count'] !== null ? (int) $detail['ps_images_count'] : null,
            'yuju_images_count' => $detail['yuju_images_count'] !== null ? (int) $detail['yuju_images_count'] : null,
            'diffs_json' => $detail['diffs_json'] !== null ? pSQL($detail['diffs_json'], true) : null,
            'result' => pSQL($detail['result']),
            'message' => pSQL($detail['message'], true),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        foreach ($row as $k => $v) {
            if ($v === null) {
                unset($row[$k]);
            }
        }

        Db::getInstance()->insert('yuju_audit_run_details', $row);
    }

    protected function formatRowForUi(array $detail)
    {
        $diffs = [];
        $errorDetail = $detail['error_detail'] ?? null;
        if (!empty($detail['diffs_json'])) {
            $decoded = json_decode($detail['diffs_json'], true);
            if (is_array($decoded)) {
                $clean = [];
                foreach ($decoded as $d) {
                    if (isset($d['_error'])) {
                        $errorDetail = $d['_error'];
                        continue;
                    }
                    $clean[] = $d;
                }
                $diffs = $clean;
            }
        }

        return [
            'sku' => $detail['sku'],
            'sku_simple' => $detail['sku_simple'] ?? null,
            'parent_id' => $detail['parent_id'] ?? null,
            'product_name' => $detail['product_name'] ?? '',
            'prestashop_product_id' => $detail['prestashop_product_id'],
            'yuju_product_id' => $detail['yuju_product_id'],
            'group_key' => $this->buildUiGroupKey($detail),
            'is_variation' => $this->isVariationDetail($detail),
            'result' => $detail['result'],
            'message' => $detail['message'],
            'ps_stock' => $detail['ps_stock'],
            'yuju_stock' => $detail['yuju_stock'],
            'ps_price' => $detail['ps_price'],
            'yuju_price' => $detail['yuju_price'],
            'ps_images_count' => $detail['ps_images_count'],
            'yuju_images_count' => $detail['yuju_images_count'],
            'diffs' => $diffs,
            'error_detail' => $errorDetail,
        ];
    }

    /**
     * Clave para agrupar variaciones bajo el mismo producto padre en la UI.
     *
     * @param array $detail
     *
     * @return string
     */
    protected function buildUiGroupKey(array $detail)
    {
        if (!empty($detail['prestashop_product_id'])) {
            return 'ps:' . (int) $detail['prestashop_product_id'];
        }
        if (!empty($detail['parent_id'])) {
            return 'yuju:' . (string) $detail['parent_id'];
        }
        if (!empty($detail['sku_simple'])) {
            return 'simple:' . (string) $detail['sku_simple'];
        }
        if (!empty($detail['sku'])) {
            return 'sku:' . (string) $detail['sku'];
        }

        return 'row:' . md5(json_encode([
            $detail['yuju_product_id'] ?? '',
            $detail['message'] ?? '',
        ]));
    }

    /**
     * @param array $detail
     *
     * @return bool
     */
    protected function isVariationDetail(array $detail)
    {
        if (!empty($detail['parent_id'])) {
            return true;
        }
        $sku = isset($detail['sku']) ? (string) $detail['sku'] : '';
        $simple = isset($detail['sku_simple']) ? (string) $detail['sku_simple'] : '';
        if ($sku !== '' && $simple !== '' && $sku !== $simple) {
            return true;
        }

        return false;
    }

    protected function failRun($runId, $message)
    {
        $run = $this->getRun((int) $runId);
        $now = date('Y-m-d H:i:s');
        Db::getInstance()->update('yuju_audit_runs', [
            'status' => 'failed',
            'error_message' => pSQL($message),
            'finished_at' => pSQL($now),
            'duration_seconds' => (int) $this->computeDurationSeconds(
                $run ? $run['started_at'] : $now,
                $now,
                $run ?: []
            ),
            'updated_at' => pSQL($now),
        ], 'id = ' . (int) $runId);
        $this->cleanupRunOffers((int) $runId);
    }

    protected function computeDurationSeconds($startedAt, $endedAt, $run = [])
    {
        if (empty($startedAt)) {
            return 0;
        }
        $start = strtotime($startedAt);
        $end = strtotime($endedAt);
        if (!$start || !$end) {
            return 0;
        }
        $raw = max(0, $end - $start);
        $summary = [];
        if (!empty($run['summary_json'])) {
            $decoded = json_decode($run['summary_json'], true);
            if (is_array($decoded)) {
                $summary = $decoded;
            }
        }
        $pauseAccum = (int) ($summary['pause_accum'] ?? 0);
        if (!empty($summary['paused_at'])) {
            $pauseAccum += max(0, time() - strtotime($summary['paused_at']));
        }

        return max(0, $raw - $pauseAccum);
    }

    protected function formatDuration($seconds)
    {
        $seconds = max(0, (int) $seconds);
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        if ($h > 0) {
            return $h . 'h ' . $m . 'm ' . str_pad((string) $s, 2, '0', STR_PAD_LEFT) . 's';
        }

        return $m . 'm ' . str_pad((string) $s, 2, '0', STR_PAD_LEFT) . 's';
    }

    protected function getRunMetaPath($runId)
    {
        return $this->cacheDir . 'audit_run_' . (int) $runId . '_meta.json';
    }

    protected function getRunOffersPath($runId)
    {
        // Legacy JSON monolítico (compat)
        return $this->cacheDir . 'audit_run_' . (int) $runId . '_offers.json';
    }

    protected function getRunOffersJsonlPath($runId)
    {
        return $this->cacheDir . 'audit_run_' . (int) $runId . '_offers.jsonl';
    }

    protected function writeRunOffersJsonl($runId, array $offers, array $meta)
    {
        $jsonl = $this->getRunOffersJsonlPath($runId);
        $fh = fopen($jsonl, 'wb');
        if ($fh) {
            foreach ($offers as $offer) {
                fwrite($fh, json_encode($offer, JSON_UNESCAPED_UNICODE) . "\n");
            }
            fclose($fh);
        }
        $meta['format'] = 'jsonl';
        $meta['total'] = count($offers);
        file_put_contents($this->getRunMetaPath($runId), json_encode($meta, JSON_UNESCAPED_UNICODE));
    }

    protected function readRunMeta($runId)
    {
        $metaPath = $this->getRunMetaPath($runId);
        if (file_exists($metaPath)) {
            $meta = json_decode(file_get_contents($metaPath), true);
            if (is_array($meta)) {
                return $meta;
            }
        }

        // Compat con formato antiguo (JSON único con offers[])
        $legacy = $this->getRunOffersPath($runId);
        if (file_exists($legacy)) {
            $data = json_decode(file_get_contents($legacy), true);
            if (is_array($data)) {
                if (isset($data['offers']) && is_array($data['offers'])) {
                    return [
                        'apply_fixes' => !empty($data['apply_fixes']),
                        'report_id' => $data['report_id'] ?? null,
                        'format' => 'legacy_json',
                    ];
                }

                return ['apply_fixes' => true, 'format' => 'legacy_list'];
            }
        }

        return null;
    }

    /**
     * Lee solo un slice del JSONL (o legacy) sin cargar todo en memoria.
     *
     * @param int $runId
     * @param int $offset
     * @param int $limit
     * @param array $meta
     *
     * @return array|null {slice: array, cursor_byte?: int}
     */
    protected function readOffersSlice($runId, $offset, $limit, array $meta = [])
    {
        $jsonl = $this->getRunOffersJsonlPath($runId);
        if (file_exists($jsonl)) {
            $slice = [];
            $fh = fopen($jsonl, 'rb');
            if (!$fh) {
                return null;
            }

            $cursorByte = isset($meta['cursor_byte']) ? (int) $meta['cursor_byte'] : 0;
            $cursorLine = isset($meta['cursor_line']) ? (int) $meta['cursor_line'] : 0;

            if ($cursorByte > 0 && $cursorLine === $offset) {
                fseek($fh, $cursorByte);
            } else {
                // Re-sincronizar desde inicio hasta offset (raro: resume tras falla)
                $i = 0;
                while ($i < $offset && ($line = fgets($fh)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }
                    ++$i;
                }
            }

            while (count($slice) < $limit && ($line = fgets($fh)) !== false) {
                if (trim($line) === '') {
                    continue;
                }
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $slice[] = $row;
                }
            }
            $newCursor = ftell($fh);
            fclose($fh);

            return ['slice' => $slice, 'cursor_byte' => (int) $newCursor];
        }

        // Legacy: parse completo una vez (solo runs antiguos)
        $legacy = $this->getRunOffersPath($runId);
        if (!file_exists($legacy)) {
            return null;
        }
        $data = json_decode(file_get_contents($legacy), true);
        if (!is_array($data)) {
            return null;
        }
        $offers = isset($data['offers']) && is_array($data['offers']) ? $data['offers'] : $data;

        return ['slice' => array_slice($offers, $offset, $limit)];
    }

    protected function cleanupRunOffers($runId)
    {
        foreach ([
            $this->getRunOffersPath($runId),
            $this->getRunOffersJsonlPath($runId),
            $this->getRunMetaPath($runId),
        ] as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
