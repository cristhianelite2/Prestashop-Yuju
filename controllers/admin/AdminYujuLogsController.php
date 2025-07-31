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
        $this->meta_title = $this->l('Yuju Logs');
        $this->logger = new YujuLogger();
    }

    public function initContent()
    {
        parent::initContent();

        $logs = $this->getLogs();

        $this->context->smarty->assign([
            'logs' => $logs,
            'module_dir' => $this->module->getPathUri(),
        ]);

        $this->setTemplate('logs.tpl');
    }

    protected function getLogs()
    {
        $log_dir = _PS_MODULE_DIR_ . 'prestashopyuju/logs/';
        $logs = [];

        if (is_dir($log_dir)) {
            $files = scandir($log_dir);

            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'log') {
                    $logs[] = [
                        'filename' => $file,
                        'size' => filesize($log_dir . $file),
                        'modified' => filemtime($log_dir . $file),
                    ];
                }
            }
        }

        return $logs;
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');
    }
}
