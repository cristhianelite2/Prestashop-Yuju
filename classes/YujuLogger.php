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

class YujuLogger
{
    public const LOG_LEVEL_DEBUG = 'debug';
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_WARNING = 'warning';
    public const LOG_LEVEL_ERROR = 'error';
    public const LOG_LEVEL_CRITICAL = 'critical';

    private $log_directory;
    private $max_file_size = 10485760; // 10MB
    private $max_files = 10;
    private $date_format = 'Y-m-d H:i:s';

    public function __construct()
    {
        $this->log_directory = dirname(__FILE__) . '/../logs/';
        $this->ensureLogDirectoryExists();
    }

    /**
     * Registra un mensaje en los logs.
     */
    public function log($level, $message, $context = [])
    {
        $log_entry = $this->formatLogEntry($level, $message, $context);

        // Log en archivo
        $this->writeToFile($level, $log_entry);

        // Log en base de datos para ciertos niveles
        if (in_array($level, [self::LOG_LEVEL_ERROR, self::LOG_LEVEL_CRITICAL])) {
            $this->writeToDatabase($level, $message, $context);
        }
    }

    /**
     * Log de debug.
     */
    public function debug($message, $context = [])
    {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log de información.
     */
    public function info($message, $context = [])
    {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }

    /**
     * Log de advertencia.
     */
    public function warning($message, $context = [])
    {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context);
    }

    /**
     * Log de error.
     */
    public function error($message, $context = [])
    {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }

    /**
     * Log crítico.
     */
    public function critical($message, $context = [])
    {
        $this->log(self::LOG_LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Formatea una entrada de log.
     */
    private function formatLogEntry($level, $message, $context)
    {
        $timestamp = date($this->date_format);
        $level_upper = strtoupper($level);

        $log_entry = "[{$timestamp}] {$level_upper}: {$message}";

        if (!empty($context)) {
            $log_entry .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        return $log_entry . PHP_EOL;
    }

    /**
     * Escribe el log en archivo.
     */
    private function writeToFile($level, $log_entry)
    {
        $filename = $this->getLogFilename($level);
        $filepath = $this->log_directory . $filename;

        // Verificar tamaño del archivo y rotar si es necesario
        if (file_exists($filepath) && filesize($filepath) > $this->max_file_size) {
            $this->rotateLogFile($filepath);
        }

        file_put_contents($filepath, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Escribe el log en la base de datos.
     */
    private function writeToDatabase($level, $message, $context)
    {
        try {
            $data = [
                'log_type' => 'system',
                'action' => 'log',
                'entity_id' => 0,
                'entity_type' => 'system',
                'status' => ($level === self::LOG_LEVEL_ERROR || $level === self::LOG_LEVEL_CRITICAL) ? 'error' : 'warning',
                'message' => $message,
                'request_data' => json_encode($context),
                'response_data' => null,
                'execution_time' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            Db::getInstance()->insert('yuju_sync_logs', $data);
        } catch (Exception $e) {
            // Si falla el log en BD, al menos escribir en archivo
            $error_log = '[' . date($this->date_format) . '] ERROR: Failed to write to database: ' . $e->getMessage() . PHP_EOL;
            file_put_contents($this->log_directory . 'error.log', $error_log, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Obtiene el nombre del archivo de log según el nivel.
     */
    private function getLogFilename($level)
    {
        $date = date('Y-m-d');

        switch ($level) {
            case self::LOG_LEVEL_ERROR:
            case self::LOG_LEVEL_CRITICAL:
                return 'error_' . $date . '.log';
            case self::LOG_LEVEL_WARNING:
                return 'warning_' . $date . '.log';
            case self::LOG_LEVEL_DEBUG:
                return 'debug_' . $date . '.log';
            default:
                return 'info_' . $date . '.log';
        }
    }

    /**
     * Rota los archivos de log cuando superan el tamaño máximo.
     */
    private function rotateLogFile($filepath)
    {
        $pathinfo = pathinfo($filepath);
        $base_name = $pathinfo['filename'];
        $extension = $pathinfo['extension'];
        $directory = $pathinfo['dirname'];

        // Mover archivos existentes
        for ($i = $this->max_files - 1; $i > 0; --$i) {
            $old_file = $directory . '/' . $base_name . '.' . $i . '.' . $extension;
            $new_file = $directory . '/' . $base_name . '.' . ($i + 1) . '.' . $extension;

            if (file_exists($old_file)) {
                if ($i === $this->max_files - 1) {
                    unlink($old_file); // Eliminar el más antiguo
                } else {
                    rename($old_file, $new_file);
                }
            }
        }

        // Mover el archivo actual
        $rotated_file = $directory . '/' . $base_name . '.1.' . $extension;
        rename($filepath, $rotated_file);
    }

    /**
     * Asegura que el directorio de logs existe.
     */
    private function ensureLogDirectoryExists()
    {
        if (!is_dir($this->log_directory)) {
            mkdir($this->log_directory, 0755, true);
        }

        // Crear subdirectorios
        $subdirs = ['sync_logs', 'error_logs', 'audit_reports'];

        foreach ($subdirs as $subdir) {
            $subdir_path = $this->log_directory . $subdir;

            if (!is_dir($subdir_path)) {
                mkdir($subdir_path, 0755, true);
            }
        }

        // Crear archivo .htaccess para proteger los logs
        $htaccess_content = 'Order deny,allow\nDeny from all';
        file_put_contents($this->log_directory . '.htaccess', $htaccess_content);
    }

    /**
     * Obtiene los logs de un archivo específico.
     */
    public function getLogsFromFile($filename, $lines = 100)
    {
        $filepath = $this->log_directory . $filename;

        if (!file_exists($filepath)) {
            return [];
        }

        $file_lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines > 0) {
            $file_lines = array_slice($file_lines, -$lines);
        }

        return array_reverse($file_lines); // Más recientes primero
    }

    /**
     * Obtiene los logs de la base de datos.
     */
    public function getLogsFromDatabase($filters = [], $limit = 100, $offset = 0)
    {
        $where_conditions = ['1=1'];

        if (isset($filters['log_type'])) {
            $where_conditions[] = 'log_type = \'' . pSQL($filters['log_type']) . '\'';
        }

        if (isset($filters['status'])) {
            $where_conditions[] = 'status = \'' . pSQL($filters['status']) . '\'';
        }

        if (isset($filters['entity_type'])) {
            $where_conditions[] = 'entity_type = \'' . pSQL($filters['entity_type']) . '\'';
        }

        if (isset($filters['date_from'])) {
            $where_conditions[] = 'start_time >= \'' . pSQL($filters['date_from']) . '\'';
        }

        if (isset($filters['date_to'])) {
            $where_conditions[] = 'start_time <= \'' . pSQL($filters['date_to']) . '\'';
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'yuju_sync_logs`
                WHERE ' . $where_clause . '
                ORDER BY `start_time` DESC
                LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Cuenta los logs en la base de datos.
     */
    public function countLogsInDatabase($filters = [])
    {
        $where_conditions = ['1=1'];

        if (isset($filters['log_type'])) {
            $where_conditions[] = 'log_type = \'' . pSQL($filters['log_type']) . '\'';
        }

        if (isset($filters['status'])) {
            $where_conditions[] = 'status = \'' . pSQL($filters['status']) . '\'';
        }

        if (isset($filters['entity_type'])) {
            $where_conditions[] = 'entity_type = \'' . pSQL($filters['entity_type']) . '\'';
        }

        if (isset($filters['date_from'])) {
            $where_conditions[] = 'start_time >= \'' . pSQL($filters['date_from']) . '\'';
        }

        if (isset($filters['date_to'])) {
            $where_conditions[] = 'start_time <= \'' . pSQL($filters['date_to']) . '\'';
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE ' . $where_clause;

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Limpia logs antiguos.
     */
    public function cleanOldLogs($days = 30)
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Limpiar base de datos
        $sql = 'DELETE FROM `' . _DB_PREFIX_ . 'yuju_sync_logs` WHERE start_time < "' . pSQL($cutoff_date) . '"';
        $deleted_db = Db::getInstance()->execute($sql);

        // Limpiar archivos
        $deleted_files = 0;
        $files = glob($this->log_directory . '*.log');

        foreach ($files as $file) {
            $file_date = filemtime($file);

            if ($file_date < strtotime("-{$days} days")) {
                unlink($file);
                ++$deleted_files;
            }
        }

        $this->info('Limpieza de logs completada', [
            'days' => $days,
            'cutoff_date' => $cutoff_date,
            'deleted_db_records' => $deleted_db,
            'deleted_files' => $deleted_files,
        ]);

        return [
            'deleted_db_records' => $deleted_db,
            'deleted_files' => $deleted_files,
        ];
    }

    /**
     * Obtiene estadísticas de logs.
     */
    public function getLogStats()
    {
        $stats = [];

        // Estadísticas de base de datos
        $sql = 'SELECT status, COUNT(*) as count FROM `' . _DB_PREFIX_ . 'yuju_sync_logs`
                WHERE start_time >= CURDATE()
                GROUP BY status';
        $db_stats = Db::getInstance()->executeS($sql);

        $stats['database'] = [
            'today' => array_column($db_stats, 'count', 'status'),
        ];

        // Estadísticas de archivos
        $files = glob($this->log_directory . '*.log');
        $stats['files'] = [
            'total_files' => count($files),
            'total_size' => 0,
        ];

        foreach ($files as $file) {
            $stats['files']['total_size'] += filesize($file);
        }

        $stats['files']['total_size_mb'] = round($stats['files']['total_size'] / 1024 / 1024, 2);

        return $stats;
    }

    /**
     * Exporta logs a un archivo.
     */
    public function exportLogs($filters = [], $format = 'json')
    {
        $logs = $this->getLogsFromDatabase($filters, 0); // Sin límite

        $export_filename = 'yuju_logs_export_' . date('Y-m-d_H-i-s');

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($logs, $export_filename);
            case 'json':
            default:
                return $this->exportToJson($logs, $export_filename);
        }
    }

    /**
     * Exporta logs a formato CSV.
     */
    private function exportToCsv($logs, $filename)
    {
        $filepath = $this->log_directory . $filename . '.csv';
        $file = fopen($filepath, 'w');

        // Headers
        $headers = ['ID', 'Tipo', 'Acción', 'Estado', 'Mensaje', 'Fecha'];
        fputcsv($file, $headers);

        // Data
        foreach ($logs as $log) {
            $row = [
                $log['id_log'],
                $log['log_type'],
                $log['action'],
                $log['status'],
                $log['message'],
                $log['created_at'],
            ];
            fputcsv($file, $row);
        }

        fclose($file);

        return $filepath;
    }

    /**
     * Exporta logs a formato JSON.
     */
    private function exportToJson($logs, $filename)
    {
        $filepath = $this->log_directory . $filename . '.json';
        $json_data = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($filepath, $json_data);

        return $filepath;
    }

    /**
     * Configura el logger.
     */
    public function configure($options = [])
    {
        if (isset($options['max_file_size'])) {
            $this->max_file_size = (int) $options['max_file_size'];
        }

        if (isset($options['max_files'])) {
            $this->max_files = (int) $options['max_files'];
        }

        if (isset($options['date_format'])) {
            $this->date_format = $options['date_format'];
        }
    }
}
