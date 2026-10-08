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
        ]);

        $action = Tools::getValue('action', 'list');
        $filename = Tools::getValue('file');
        $lines = (int) Tools::getValue('lines', 500);

        if ($action === 'view' && $filename) {
            $this->viewLogFile($filename, $lines);
        } else {
            $this->listLogFiles();
        }

        $this->setTemplate('logs.tpl');
    }

    protected function listLogFiles()
    {
        $logs = $this->getLogs();
        $db_logs = $this->logger->getLogsFromDatabase([], 50);

        $this->context->smarty->assign([
            'logs' => $logs,
            'db_logs' => $db_logs,
            'module_dir' => $this->module->getPathUri(),
            'current_action' => 'list',
            'current_index' => $this->context->link->getAdminLink('AdminYujuLogs'),
        ]);
    }

    protected function viewLogFile($filename, $lines = 500)
    {
        // Validar nombre de archivo para path traversal
        $filename = basename($filename);
        if (!preg_match('/^[\w\-\.]+$/', $filename)) {
            $this->errors[] = $this->trans('Nombre de archivo inválido', [], 'Modules.Prestashopyuju.Admin');
            $this->listLogFiles();
            return;
        }

        $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/';
        $subdirs = ['', 'sync_logs/', 'error_logs/', 'audit_reports/'];
        $filepath = null;

        foreach ($subdirs as $subdir) {
            $test_path = $log_dir . $subdir . $filename;
            if (file_exists($test_path) && is_readable($test_path)) {
                $filepath = $test_path;
                break;
            }
        }

        if (!$filepath) {
            $this->errors[] = $this->trans('Archivo no encontrado o no legible', [], 'Modules.Prestashopyuju.Admin');
            $this->listLogFiles();
            return;
        }

        $content = $this->readLogFile($filepath, $lines);
        $stats = $this->getLogStats($filepath);

        $this->context->smarty->assign([
            'current_action' => 'view',
            'view_filename' => $filename,
            'view_lines' => $lines,
            'log_content' => $content,
            'log_stats' => $stats,
            'module_dir' => $this->module->getPathUri(),
            'current_index' => $this->context->link->getAdminLink('AdminYujuLogs'),
        ]);
    }

    protected function readLogFile($filepath, $lines = 500)
    {
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

    protected function getLogStats($filepath)
    {
        $stats = [
            'size' => filesize($filepath),
            'size_human' => $this->formatBytes(filesize($filepath)),
            'modified' => date('Y-m-d H:i:s', filemtime($filepath)),
            'lines' => 0,
            'errors' => 0,
            'warnings' => 0,
            'info' => 0,
        ];

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
                if (stripos($line, '] error') !== false || stripos($line, '] ERROR') !== false) {
                    $error_count++;
                } elseif (stripos($line, '] warning') !== false || stripos($line, '] WARNING') !== false) {
                    $warning_count++;
                } elseif (stripos($line, '] info') !== false || stripos($line, '] INFO') !== false) {
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
            $subdirs = ['', 'sync_logs/', 'error_logs/', 'audit_reports/'];
            
            foreach ($subdirs as $subdir) {
                $full_dir = $log_dir . $subdir;
                if (is_dir($full_dir)) {
                    $files = scandir($full_dir);
                    
                    foreach ($files as $file) {
                        if (pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                            $filepath = $full_dir . $file;
                            $logs[] = [
                                'filename' => $file,
                                'subdir' => $subdir,
                                'full_path' => $subdir . $file,
                                'size' => filesize($filepath),
                                'size_human' => $this->formatBytes(filesize($filepath)),
                                'modified' => date('Y-m-d H:i:s', filemtime($filepath)),
                            ];
                        }
                    }
                }
            }
        }

        // Ordenar por fecha descendente
        usort($logs, function($a, $b) {
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