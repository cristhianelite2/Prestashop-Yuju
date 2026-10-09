<?php
/**
 * 2024 Yuju Integration.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Yuju Integration Team
 * @copyright 2024 Yuju Integration
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuLogger.php';

class AdminYujuLogsController extends ModuleAdminController
{
    /**
     * Subdirectorios de logs que se muestran en el visor.
     * El visor es 100% por archivo (no lee de base de datos).
     */
    protected $log_subdirs = ['', 'sync_logs/', 'error_logs/', 'audit_reports/', 'oauth_attempts/'];

    protected $logger;

    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
        $this->meta_title = $this->trans('Yuju Logs', [], 'Modules.Prestashopyuju.Admin');
        $this->logger = new YujuLogger();
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign([
            'current_controller' => 'AdminYujuLogs',
            'current_index' => $this->context->link->getAdminLink('AdminYujuLogs'),
            'token' => Tools::getAdminTokenLite('AdminYujuLogs'),
        ]);

        $action = Tools::getValue('action', 'list');
        $filename = Tools::getValue('file');
        $lines = (int) Tools::getValue('lines', 500);

        if ($action === 'view' && $filename) {
            if (Tools::getValue('download')) {
                $this->downloadLogFile($filename);
                return;
            }

            $this->viewLogFile($filename, $lines);
        } elseif ($action === 'oauth') {
            $this->viewOAuthAttempts((int) Tools::getValue('page', 1));
        } else {
            $this->listLogFiles();
        }

        $this->setTemplate('logs.tpl');
    }

    protected function listLogFiles()
    {
        $logs = $this->getLogs();

        // Los intentos OAuth se muestran como un único grupo resumido.
        $oauth_group = $this->buildOAuthGroup($logs);
        $logs = array_values(array_filter($logs, function ($log) {
            return $log['subdir'] !== 'oauth_attempts/';
        }));

        $this->context->smarty->assign([
            'logs' => $logs,
            'oauth_group' => $oauth_group,
            'module_dir' => $this->module->getPathUri(),
            'current_action' => 'list',
            'current_index' => $this->context->link->getAdminLink('AdminYujuLogs'),
        ]);
    }

    /**
     * Agrupa todos los archivos de oauth_attempts/ en un único bloque con el
     * total de intentos, los fallidos y el intento más reciente.
     */
    protected function buildOAuthGroup($logs)
    {
        $oauth_logs = array_values(array_filter($logs, function ($log) {
            return $log['subdir'] === 'oauth_attempts/';
        }));

        if (empty($oauth_logs)) {
            return null;
        }

        $group = [
            'total' => count($oauth_logs),
            'failed' => 0,
            'success' => 0,
            'pending' => 0,
            'latest' => null,
        ];

        // $logs viene ordenado por fecha descendente: el primero es el más reciente.
        foreach ($oauth_logs as $index => $log) {
            $data = $this->readOAuthAttemptData($log['full_path']);

            $status = isset($data['status']) ? (string) $data['status'] : 'unknown';
            $http_code = isset($data['http_code']) ? (int) $data['http_code'] : 0;
            $curl_error = isset($data['curl_error']) ? (string) $data['curl_error'] : '';

            if ($this->isFailedOAuthAttempt($status, $http_code, $curl_error)) {
                $group['failed']++;
            } elseif ($status === 'success') {
                $group['success']++;
            } else {
                // 'start' u otros estados sin resultado definitivo.
                $group['pending']++;
            }

            if ($index === 0) {
                $group['latest'] = [
                    'filename' => $log['filename'],
                    'full_path' => $log['full_path'],
                    'status' => $status,
                    'is_failed' => $this->isFailedOAuthAttempt($status, $http_code, $curl_error),
                    'http_code' => $http_code,
                    'message' => isset($data['message']) ? (string) $data['message'] : '',
                    'curl_error' => $curl_error,
                    'client_id' => isset($data['client_id']) ? (string) $data['client_id'] : '',
                    'created_at' => isset($data['created_at']) ? (string) $data['created_at'] : $log['modified'],
                    'modified' => $log['modified'],
                ];
            }
        }

        return $group;
    }

    /**
     * Listado paginado de todos los intentos OAuth registrados (historial).
     */
    protected function viewOAuthAttempts($page = 1)
    {
        $attempts = $this->getOAuthAttemptList();

        $per_page = 20;
        $total = count($attempts);
        $pages = max(1, (int) ceil($total / $per_page));
        $page = min(max(1, $page), $pages);

        $failed = 0;
        foreach ($attempts as $attempt) {
            if ($attempt['is_failed']) {
                $failed++;
            }
        }

        $this->context->smarty->assign([
            'current_action' => 'oauth',
            'oauth_attempts' => array_slice($attempts, ($page - 1) * $per_page, $per_page),
            'oauth_total' => $total,
            'oauth_failed' => $failed,
            'oauth_success' => $total - $failed,
            'oauth_page' => $page,
            'oauth_pages' => $pages,
            'oauth_page_prev' => max(1, $page - 1),
            'oauth_page_next' => min($pages, $page + 1),
            'oauth_pager' => range(1, $pages),
            'module_dir' => $this->module->getPathUri(),
            'current_index' => $this->context->link->getAdminLink('AdminYujuLogs'),
        ]);
    }

    /**
     * Devuelve el resumen de cada intento OAuth ordenado del más reciente al
     * más antiguo.
     */
    protected function getOAuthAttemptList()
    {
        $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/oauth_attempts/';
        $attempts = [];

        if (!is_dir($log_dir)) {
            return $attempts;
        }

        $files = glob($log_dir . '*.json');

        if (empty($files)) {
            return $attempts;
        }

        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }

            $data = $this->readOAuthAttemptData(basename($file));

            $status = isset($data['status']) ? (string) $data['status'] : 'unknown';
            $http_code = isset($data['http_code']) ? (int) $data['http_code'] : 0;
            $curl_error = isset($data['curl_error']) ? (string) $data['curl_error'] : '';

            $attempts[] = [
                'filename' => basename($file),
                'full_path' => 'oauth_attempts/' . basename($file),
                'attempt_id' => isset($data['attempt_id']) ? (string) $data['attempt_id'] : basename($file, '.json'),
                'status' => $status,
                'is_failed' => $this->isFailedOAuthAttempt($status, $http_code, $curl_error),
                'http_code' => $http_code,
                'message' => isset($data['message']) ? (string) $data['message'] : '',
                'curl_error' => $curl_error,
                'url' => isset($data['url']) ? (string) $data['url'] : '',
                'client_id' => isset($data['client_id']) ? (string) $data['client_id'] : '',
                'created_at' => isset($data['created_at'])
                    ? (string) $data['created_at']
                    : date('Y-m-d H:i:s', filemtime($file)),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        return $attempts;
    }

    /**
     * Lee y decodifica un intento OAuth por su nombre de archivo.
     */
    protected function readOAuthAttemptData($filename)
    {
        $filepath = $this->resolveLogFile($filename);

        if (!$filepath) {
            return [];
        }

        $data = json_decode((string) file_get_contents($filepath), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Determina si un intento OAuth se considera fallido.
     */
    protected function isFailedOAuthAttempt($status, $http_code = 0, $curl_error = '')
    {
        if ($status === 'error') {
            return true;
        }

        if ($curl_error !== '') {
            return true;
        }

        return $http_code >= 400;
    }

    protected function viewLogFile($filename, $lines = 500)
    {
        $filepath = $this->resolveLogFile($filename);

        if (!$filepath) {
            $this->errors[] = $this->trans('Archivo no encontrado o no legible', [], 'Modules.Prestashopyuju.Admin');
            $this->listLogFiles();
            return;
        }

        $content = $this->readLogFile($filepath, $lines);
        $stats = $this->getLogStats($filepath);

        $this->context->smarty->assign([
            'current_action' => 'view',
            'view_filename' => basename($filepath),
            'view_is_json' => pathinfo($filepath, PATHINFO_EXTENSION) === 'json',
            'view_lines' => $lines,
            'log_content' => $content,
            'log_stats' => $stats,
            'module_dir' => $this->module->getPathUri(),
            'current_index' => $this->context->link->getAdminLink('AdminYujuLogs'),
        ]);
    }

    /**
     * Resuelve el nombre de archivo recibido a un fichero real dentro del
     * directorio de logs. Soporta alias sin fecha (p. ej. "error.log" apunta al
     * "error_YYYY-MM-DD.log" más reciente) para URLs antiguas.
     */
    protected function resolveLogFile($filename)
    {
        $filename = basename((string) $filename);

        if ($filename === '' || !preg_match('/^[\w\-\.]+$/', $filename)) {
            return null;
        }

        $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/';

        // 1. Coincidencia exacta en cualquier subdirectorio.
        foreach ($this->log_subdirs as $subdir) {
            $test_path = $log_dir . $subdir . $filename;

            if (is_file($test_path) && is_readable($test_path)) {
                return $test_path;
            }
        }

        // 2. Alias: buscar por prefijo y quedarse con el más reciente.
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $matches = [];

        foreach ($this->log_subdirs as $subdir) {
            $full_dir = $log_dir . $subdir;

            if (!is_dir($full_dir)) {
                continue;
            }

            foreach (['log', 'json'] as $extension) {
                $candidates = glob($full_dir . $stem . '*.' . $extension);
                if (!empty($candidates)) {
                    $matches = array_merge($matches, $candidates);
                }
            }
        }

        if (empty($matches)) {
            return null;
        }

        usort($matches, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $filepath = reset($matches);

        return (is_file($filepath) && is_readable($filepath)) ? $filepath : null;
    }

    protected function readLogFile($filepath, $lines = 500)
    {
        if (pathinfo($filepath, PATHINFO_EXTENSION) === 'json') {
            return $this->readJsonFile($filepath);
        }

        $content = [];

        if (filesize($filepath) > 10 * 1024 * 1024) { // > 10MB
            // Leer solo las últimas líneas para archivos grandes
            $handle = fopen($filepath, 'r');
            if ($handle) {
                $buffer = '';
                fseek($handle, 0, SEEK_END);
                $pos = ftell($handle);
                $line_count = 0;

                while ($pos > 0 && $line_count < $lines) {
                    $read_size = min(8192, $pos);
                    $pos -= $read_size;
                    fseek($handle, $pos);
                    $chunk = fread($handle, $read_size);
                    $buffer = $chunk . $buffer;
                    $line_count = substr_count($buffer, "\n");
                }
                fclose($handle);

                $file_lines = array_filter(explode("\n", $buffer));
                $content = array_slice($file_lines, -$lines);
            }
        } else {
            $file_lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $content = array_slice($file_lines, -$lines);
        }

        return array_reverse($content); // Más recientes primero
    }

    /**
     * Lee un archivo JSON (intentos OAuth) y lo devuelve formateado por líneas.
     */
    protected function readJsonFile($filepath)
    {
        $raw = file_get_contents($filepath);
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return explode("\n", (string) $raw);
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return explode("\n", $pretty);
    }

    /**
     * Envía el archivo de log como descarga.
     */
    protected function downloadLogFile($filename)
    {
        $filepath = $this->resolveLogFile($filename);

        if (!$filepath) {
            $this->errors[] = $this->trans('Archivo no encontrado o no legible', [], 'Modules.Prestashopyuju.Admin');
            $this->listLogFiles();
            $this->setTemplate('logs.tpl');

            return;
        }

        $download_name = basename($filepath);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($filepath);
        exit;
    }

    protected function getLogStats($filepath)
    {
        $size = filesize($filepath);

        $stats = [
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'modified' => date('Y-m-d H:i:s', filemtime($filepath)),
            'type' => pathinfo($filepath, PATHINFO_EXTENSION),
            'lines' => 0,
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
        ];

        if ($stats['type'] === 'json') {
            $decoded = json_decode((string) file_get_contents($filepath), true);

            if (is_array($decoded)) {
                $stats['lines'] = is_array($decoded) ? count($decoded, COUNT_RECURSIVE) : 0;

                if (isset($decoded['status'])) {
                    if ($decoded['status'] === 'error') {
                        $stats['errors'] = 1;
                    } elseif ($decoded['status'] === 'success') {
                        $stats['info'] = 1;
                    } else {
                        $stats['warnings'] = 1;
                    }
                }
            }

            return $stats;
        }

        // Contar líneas y niveles (muestra rápida de últimas 1000 líneas)
        $handle = fopen($filepath, 'r');
        if ($handle) {
            $line_count = 0;
            $error_count = 0;
            $warning_count = 0;
            $info_count = 0;

            // Ir al final y leer hacia atrás
            fseek($handle, 0, SEEK_END);
            $pos = ftell($handle);
            $buffer = '';
            $lines_read = 0;

            while ($pos > 0 && $lines_read < 1000) {
                $read_size = min(4096, $pos);
                $pos -= $read_size;
                fseek($handle, $pos);
                $chunk = fread($handle, $read_size);
                $buffer = $chunk . $buffer;

                $new_lines = substr_count($buffer, "\n");
                if ($new_lines > $line_count) {
                    $lines_read += $new_lines - $line_count;
                    $line_count = $new_lines;
                }

                if ($line_count >= 1000) break;
            }
            fclose($handle);

            $file_lines = array_filter(explode("\n", $buffer));
            $file_lines = array_slice($file_lines, -1000);

            $stats['lines'] = count($file_lines);

            foreach ($file_lines as $line) {
                if (stripos($line, '] error') !== false || stripos($line, '] critical') !== false) {
                    $error_count++;
                } elseif (stripos($line, '] warning') !== false) {
                    $warning_count++;
                } elseif (stripos($line, '] info') !== false) {
                    $info_count++;
                }
            }

            $stats['errors'] = $error_count;
            $stats['warnings'] = $warning_count;
            $stats['info'] = $info_count;
        }

        return $stats;
    }

    protected function getLogs()
    {
        $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/';
        $logs = [];

        if (is_dir($log_dir)) {
            foreach ($this->log_subdirs as $subdir) {
                $full_dir = $log_dir . $subdir;

                if (!is_dir($full_dir)) {
                    continue;
                }

                $files = scandir($full_dir);

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                    if (!in_array($extension, ['log', 'json'], true)) {
                        continue;
                    }

                    $filepath = $full_dir . $file;

                    if (!is_file($filepath)) {
                        continue;
                    }

                    $logs[] = [
                        'filename' => $file,
                        'subdir' => $subdir,
                        'full_path' => $subdir . $file,
                        'type' => $extension,
                        'is_error' => stripos($file, 'error') !== false,
                        'size' => filesize($filepath),
                        'size_human' => $this->formatBytes(filesize($filepath)),
                        'modified' => date('Y-m-d H:i:s', filemtime($filepath)),
                    ];
                }
            }
        }

        // Ordenar por fecha descendente
        usort($logs, function ($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });

        return $logs;
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }
}
