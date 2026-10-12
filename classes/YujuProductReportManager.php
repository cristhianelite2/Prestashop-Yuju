<?php
/**
 * Coordinate Yuju's asynchronous general-products report.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuProductReportFileStore.php';

class YujuProductReportManager
{
    const STATE_CONFIG_KEY = 'YUJU_PRODUCTS_REPORT_STATE';
    const QUOTA_CONFIG_KEY = 'YUJU_PRODUCTS_REPORT_QUOTA';

    private $api_client;
    private $file_store;

    public function __construct($api_client = null, $file_store = null)
    {
        $this->api_client = $api_client ?: new YujuApiClient();
        $this->file_store = $file_store ?: new YujuProductReportFileStore();
    }

    /**
     * Start one report or resume the current task without issuing a duplicate POST.
     *
     * @return array
     */
    public function startOrResumeReport()
    {
        $this->file_store->cleanupExpired();
        $state = $this->getStoredState();

        $has_cached_report = !empty($state['cache_id'])
            && preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])
            && $this->file_store->reportExists($state['cache_id']);

        if (!empty($state['task_id']) && in_array($state['status'], ['CREATED', 'PROCESSING'], true)) {
            return [
                'success' => true,
                'status' => $state['status'],
                'task_id' => $state['task_id'],
                'requested_at' => isset($state['requested_at']) ? (int) $state['requested_at'] : null,
                'reused' => true,
            ];
        }

        if (!empty($state['task_id']) && $state['status'] === 'COMPLETED' && $has_cached_report) {
            return [
                'success' => true,
                'status' => 'COMPLETED',
                'task_id' => $state['task_id'],
                'requested_at' => isset($state['requested_at']) ? (int) $state['requested_at'] : null,
                'reused' => true,
            ];
        }

        if (!empty($state['task_id']) && $state['status'] === 'COMPLETED' && !$has_cached_report) {
            $state['status'] = 'DOWNLOAD_EXPIRED';
            $state['cache_id'] = null;
            $this->storeJson(self::STATE_CONFIG_KEY, $state);
        }

        if ($state['status'] === 'DOWNLOAD_ERROR' && !empty($state['task_id'])) {
            return [
                'success' => false,
                'status' => 'DOWNLOAD_ERROR',
                'task_id' => $state['task_id'],
                'message' => 'El reporte ya está listo, pero su descarga falló la validación. Reintenta la descarga del reporte; no inicies otra tarea.',
            ];
        }

        $requested_at = isset($state['requested_at']) ? (int) $state['requested_at'] : 0;
        $request_day = isset($state['request_day']) ? $state['request_day'] : '';
        $today = gmdate('Y-m-d');

        if ($state['status'] === 'UNKNOWN' && $request_day === $today) {
            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'Yuju pudo haber iniciado el reporte, pero no se recibió el ID de tarea. No se repetirá automáticamente para evitar consumir otra solicitud diaria; intenta de nuevo mañana UTC.',
                'requested_at' => $requested_at,
            ];
        }

        $quota = $this->getDailyQuota($today);

        if ($quota['count'] >= 2) {
            return [
                'success' => false,
                'status' => 'RATE_LIMITED',
                'message' => 'Ya se solicitaron los dos reportes generales permitidos por Yuju para hoy (UTC). El límite se restablece mañana a las 00:00 UTC.',
                'requested_at' => $requested_at,
            ];
        }

        ++$quota['count'];
        $state = [
            'status' => 'REQUESTING',
            'task_id' => null,
            'requested_at' => time(),
            'request_day' => $today,
        ];

        if (!$this->storeJson(self::QUOTA_CONFIG_KEY, $quota)
            || !$this->storeJson(self::STATE_CONFIG_KEY, $state)
        ) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'No se pudo guardar el control local del reporte; no se llamó a Yuju.',
            ];
        }

        try {
            $response = $this->api_client->createProductGeneralReport();
        } catch (Exception $e) {
            $state['status'] = 'UNKNOWN';
            $this->storeJson(self::STATE_CONFIG_KEY, $state);

            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'No se pudo confirmar si Yuju inició el reporte. No se repetirá automáticamente para evitar duplicar la solicitud diaria.',
                'requested_at' => $state['requested_at'],
            ];
        }

        if (empty($response['success'])) {
            $state['status'] = 'UNKNOWN';
            $this->storeJson(self::STATE_CONFIG_KEY, $state);

            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'Yuju no confirmó el inicio del reporte. No se repetirá automáticamente para evitar duplicar la solicitud diaria.',
                'requested_at' => $state['requested_at'],
            ];
        }

        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $task_id = isset($data['id_task']) ? (string) $data['id_task'] : '';

        if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $task_id)) {
            $state['status'] = 'UNKNOWN';
            $this->storeJson(self::STATE_CONFIG_KEY, $state);

            return [
                'success' => false,
                'status' => 'UNKNOWN',
                'message' => 'Yuju respondió sin un ID de tarea válido. No se repetirá automáticamente para evitar consumir la cuota.',
                'requested_at' => $state['requested_at'],
            ];
        }

        $state['task_id'] = $task_id;
        $state['status'] = isset($data['status']) ? strtoupper((string) $data['status']) : 'CREATED';

        if (!in_array($state['status'], ['CREATED', 'PROCESSING', 'COMPLETED'], true)) {
            $state['status'] = 'CREATED';
        }

        if (!$this->storeJson(self::STATE_CONFIG_KEY, $state)) {
            return [
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Yuju creó el reporte, pero no se pudo guardar su estado local. No vuelvas a iniciarlo; revisa el registro antes de consumir otra solicitud.',
                'requested_at' => $state['requested_at'],
            ];
        }

        return [
            'success' => true,
            'status' => $state['status'],
            'task_id' => $task_id,
            'requested_at' => $state['requested_at'],
            'reused' => false,
        ];
    }

    /**
     * Poll a saved task. The download URL is returned only to server-side callers.
     *
     * @return array
     */
    public function pollReport($retry_download = false)
    {
        $state = $this->getStoredState();

        if ($state['status'] === 'DOWNLOAD_ERROR' && !$retry_download) {
            return [
                'success' => false,
                'status' => 'DOWNLOAD_ERROR',
                'task_id' => $state['task_id'],
                'message' => 'El reporte está listo, pero la descarga necesita revisión o reintento manual.',
            ];
        }

        if ($state['status'] === 'COMPLETED' && !empty($state['cache_id'])
            && preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])
            && $this->file_store->reportExists($state['cache_id'])
        ) {
            return $this->getCachedReportStatus($state);
        }

        if (empty($state['task_id'])) {
            return ['success' => false, 'status' => $state['status'], 'message' => 'No hay un reporte activo para consultar.'];
        }

        try {
            $response = $this->api_client->getProductGeneralReportStatus($state['task_id']);
        } catch (Exception $e) {
            return ['success' => false, 'status' => $state['status'], 'message' => 'No se pudo consultar el estado del reporte. Intenta nuevamente.'];
        }

        if (empty($response['success']) || empty($response['data']) || !is_array($response['data'])) {
            return [
                'success' => false,
                'status' => $state['status'],
                'message' => 'Yuju no devolvió un estado válido para el reporte.',
            ];
        }

        $data = $response['data'];
        $status = isset($data['status']) ? strtoupper((string) $data['status']) : '';

        if (!in_array($status, ['CREATED', 'PROCESSING', 'COMPLETED', 'REJECTED', 'ERROR'], true)) {
            return ['success' => false, 'status' => $state['status'], 'message' => 'Yuju devolvió un estado de reporte desconocido.'];
        }

        if ($status === 'COMPLETED') {
            $report_url = isset($data['url']) && is_string($data['url']) ? $data['url'] : '';

            if ($report_url === '') {
                return ['success' => false, 'status' => 'ERROR', 'message' => 'El reporte terminó, pero Yuju no devolvió su URL de descarga.'];
            }

            try {
                $download = $this->file_store->downloadAndValidate($report_url);
            } catch (Exception $e) {
                $state['status'] = 'DOWNLOAD_ERROR';
                $state['download_error'] = 'download_failed';
                $this->storeJson(self::STATE_CONFIG_KEY, $state);

                return [
                    'success' => false,
                    'status' => 'DOWNLOAD_ERROR',
                    'message' => 'No se pudo descargar o validar el reporte. Reintenta la descarga; no se volverá a generar el reporte ni a consumir cuota.',
                ];
            }

            $state['status'] = 'COMPLETED';
            $state['cache_id'] = $download['cache_id'];
            $state['product_count'] = (int) $download['product_count'];
            $state['report_size_bytes'] = (int) $download['size_bytes'];
            $state['report_downloaded_at'] = (int) $download['downloaded_at'];

            if (!$this->storeJson(self::STATE_CONFIG_KEY, $state)) {
                $this->file_store->deleteReport($download['cache_id']);

                return ['success' => false, 'status' => 'ERROR', 'message' => 'No se pudo guardar el reporte descargado en el estado local.'];
            }

            return $this->getCachedReportStatus($state);
        }

        $state['status'] = $status;
        if (!$this->storeJson(self::STATE_CONFIG_KEY, $state)) {
            return ['success' => false, 'status' => 'ERROR', 'message' => 'No se pudo guardar el estado actualizado del reporte.'];
        }

        return [
            'success' => $status !== 'ERROR' && $status !== 'REJECTED',
            'status' => $status,
            'task_id' => $state['task_id'],
            'message' => isset($data['message']) && is_scalar($data['message']) ? (string) $data['message'] : null,
        ];
    }

    /**
     * Read validated products from the current private report cache.
     *
     * @return Generator
     */
    public function getCachedReportProducts()
    {
        $state = $this->getStoredState();

        if ($state['status'] !== 'COMPLETED' || empty($state['cache_id'])
            || !preg_match('/^[a-f0-9]{32}$/', (string) $state['cache_id'])
            || !$this->file_store->reportExists($state['cache_id'])
        ) {
            throw new RuntimeException('No validated general-products report is cached.');
        }

        return $this->file_store->readProducts($state['cache_id']);
    }

    protected function getCachedReportStatus($state)
    {
        return [
            'success' => true,
            'status' => 'COMPLETED',
            'task_id' => $state['task_id'],
            'product_count' => isset($state['product_count']) ? (int) $state['product_count'] : 0,
            'report_size_bytes' => isset($state['report_size_bytes']) ? (int) $state['report_size_bytes'] : 0,
            'report_downloaded_at' => isset($state['report_downloaded_at']) ? (int) $state['report_downloaded_at'] : 0,
            'report_cached' => true,
        ];
    }

    public function getStoredState()
    {
        $state = json_decode((string) Configuration::get(self::STATE_CONFIG_KEY), true);

        return is_array($state)
            ? array_merge(['status' => '', 'task_id' => null], $state)
            : ['status' => '', 'task_id' => null];
    }

    protected function getDailyQuota($today)
    {
        $quota = json_decode((string) Configuration::get(self::QUOTA_CONFIG_KEY), true);

        if (!is_array($quota) || !isset($quota['date_utc']) || $quota['date_utc'] !== $today) {
            return ['date_utc' => $today, 'count' => 0];
        }

        return ['date_utc' => $today, 'count' => max(0, min(2, (int) ($quota['count'] ?? 0)))];
    }

    protected function storeJson($key, $value)
    {
        return Configuration::updateValue($key, json_encode($value));
    }
}
