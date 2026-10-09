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

        // Log solo en archivo (no en base de datos)
        $this->writeToFile($level, $log_entry);
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
        $subdirs = ['sync_logs', 'error_logs', 'audit_reports', 'oauth_attempts'];

        foreach ($subdirs as $subdir) {
            $subdir_path = $this->log_directory . $subdir;

            if (!is_dir($subdir_path)) {
                mkdir($subdir_path, 0755, true);
            }
        }

        // Crear archivo .htaccess para proteger los logs (solo si el directorio existe y es escribible)
        $htaccess_path = $this->log_directory . '.htaccess';
        $htaccess_content = "# Apache 2.4+\nRequire all denied\n\n# Apache 2.2 fallback\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>";
        
        if (is_dir($this->log_directory) && is_writable($this->log_directory)) {
            @file_put_contents($htaccess_path, $htaccess_content);
        }
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
     * Limpia logs antiguos (solo archivos).
     */
    public function cleanOldLogs($days = 30)
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

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

        // Limpiar subdirectorios
        $subdirs = ['sync_logs', 'error_logs', 'audit_reports', 'oauth_attempts'];
        foreach ($subdirs as $subdir) {
            $subdir_files = glob($this->log_directory . $subdir . '/*.log');
            foreach ($subdir_files as $file) {
                $file_date = filemtime($file);
                if ($file_date < strtotime("-{$days} days")) {
                    unlink($file);
                    ++$deleted_files;
                }
            }
        }

        $this->info('Limpieza de logs completada', [
            'days' => $days,
            'cutoff_date' => $cutoff_date,
            'deleted_files' => $deleted_files,
        ]);

        return [
            'deleted_files' => $deleted_files,
        ];
    }

    /**
     * Obtiene estadísticas de logs (solo archivos).
     */
    public function getLogStats()
    {
        $stats = [];

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

        // Estadísticas por subdirectorio
        $subdirs = ['sync_logs', 'error_logs', 'audit_reports', 'oauth_attempts'];
        foreach ($subdirs as $subdir) {
            $subdir_files = glob($this->log_directory . $subdir . '/*.log');
            $subdir_size = 0;
            foreach ($subdir_files as $file) {
                $subdir_size += filesize($file);
            }
            $stats['subdirs'][$subdir] = [
                'files' => count($subdir_files),
                'size_mb' => round($subdir_size / 1024 / 1024, 2),
            ];
        }

        return $stats;
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
