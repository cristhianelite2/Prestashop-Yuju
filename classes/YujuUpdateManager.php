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

/**
 * Detecta y aplica actualizaciones del módulo desde GitHub (rama main).
 */
class YujuUpdateManager
{
    public const GITHUB_OWNER = 'cristhianelite2';
    public const GITHUB_REPO = 'Prestashop-Yuju';
    public const GITHUB_BRANCH = 'main';
    public const CHECK_INTERVAL = 86400; // 24 horas

    public const CFG_LAST_CHECK = 'YUJU_UPDATE_LAST_CHECK';
    public const CFG_AVAILABLE = 'YUJU_UPDATE_AVAILABLE';
    public const CFG_REMOTE_VERSION = 'YUJU_UPDATE_REMOTE_VERSION';
    public const CFG_REMOTE_SHA = 'YUJU_UPDATE_REMOTE_SHA';
    public const CFG_INSTALLED_SHA = 'YUJU_UPDATE_INSTALLED_SHA';
    public const CFG_REMOTE_DATE = 'YUJU_UPDATE_REMOTE_DATE';
    public const CFG_REMOTE_MESSAGE = 'YUJU_UPDATE_REMOTE_MESSAGE';
    public const CFG_CHECK_ERROR = 'YUJU_UPDATE_CHECK_ERROR';

    /** @var string */
    private $moduleDir;

    /** @var string */
    private $localVersion;

    public function __construct($localVersion = null)
    {
        $this->moduleDir = _PS_MODULE_DIR_ . 'prestashopyuju' . DIRECTORY_SEPARATOR;
        $this->localVersion = $localVersion !== null
            ? (string) $localVersion
            : (string) YUJU_MODULE_VERSION;
    }

    /**
     * Estado de actualización (usa caché de 24h salvo que se fuerce).
     *
     * @param bool $force
     *
     * @return array
     */
    public function getUpdateStatus($force = false)
    {
        $lastCheck = (int) Configuration::get(self::CFG_LAST_CHECK);
        $needsCheck = $force || !$lastCheck || (time() - $lastCheck) >= self::CHECK_INTERVAL;

        if ($needsCheck) {
            try {
                $this->checkForUpdates();
            } catch (Exception $e) {
                Configuration::updateValue(self::CFG_CHECK_ERROR, $e->getMessage());
                Configuration::updateValue(self::CFG_LAST_CHECK, (string) time());
            }
        }

        $lastCheck = (int) Configuration::get(self::CFG_LAST_CHECK);
        $remoteVersion = (string) Configuration::get(self::CFG_REMOTE_VERSION);
        $remoteSha = (string) Configuration::get(self::CFG_REMOTE_SHA);
        $installedSha = (string) Configuration::get(self::CFG_INSTALLED_SHA);
        $updateAvailable = (bool) Configuration::get(self::CFG_AVAILABLE);

        return [
            'update_available' => $updateAvailable,
            'local_version' => $this->localVersion,
            'remote_version' => $remoteVersion ?: $this->localVersion,
            'remote_sha' => $remoteSha,
            'installed_sha' => $installedSha,
            'remote_date' => (string) Configuration::get(self::CFG_REMOTE_DATE),
            'remote_message' => (string) Configuration::get(self::CFG_REMOTE_MESSAGE),
            'last_check' => $lastCheck ? date('Y-m-d H:i:s', $lastCheck) : null,
            'last_check_timestamp' => $lastCheck,
            'next_check' => $lastCheck ? date('Y-m-d H:i:s', $lastCheck + self::CHECK_INTERVAL) : null,
            'check_error' => (string) Configuration::get(self::CFG_CHECK_ERROR),
            'repo_url' => 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
            'branch' => self::GITHUB_BRANCH,
        ];
    }

    /**
     * Consulta GitHub y actualiza la caché de estado.
     *
     * @return array
     *
     * @throws Exception
     */
    public function checkForUpdates()
    {
        $commit = $this->fetchLatestCommit();
        $remoteVersion = $this->fetchRemoteModuleVersion();
        $remoteSha = isset($commit['sha']) ? (string) $commit['sha'] : '';
        $installedSha = (string) Configuration::get(self::CFG_INSTALLED_SHA);

        $versionNewer = version_compare($remoteVersion, $this->localVersion, '>');
        $shaDifferent = $remoteSha !== '' && ($installedSha === '' || $remoteSha !== $installedSha);
        // Si nunca se guardó SHA local, solo marcar por versión más nueva
        $versionNotOlder = version_compare($remoteVersion, $this->localVersion, '>=');
        $updateAvailable = $versionNewer || ($installedSha !== '' && $shaDifferent && $versionNotOlder);

        // Primera instalación del chequeo: registrar SHA remoto si versiones iguales
        if ($installedSha === '' && !$versionNewer && $remoteSha !== '') {
            Configuration::updateValue(self::CFG_INSTALLED_SHA, $remoteSha);
            $updateAvailable = false;
        }

        $commitDate = '';
        if (!empty($commit['commit']['committer']['date'])) {
            $commitDate = (string) $commit['commit']['committer']['date'];
        } elseif (!empty($commit['commit']['author']['date'])) {
            $commitDate = (string) $commit['commit']['author']['date'];
        }

        $commitMessage = '';
        if (!empty($commit['commit']['message'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $commit['commit']['message']);
            $commitMessage = trim($lines[0]);
        }

        Configuration::updateValue(self::CFG_AVAILABLE, $updateAvailable ? '1' : '0');
        Configuration::updateValue(self::CFG_REMOTE_VERSION, $remoteVersion);
        Configuration::updateValue(self::CFG_REMOTE_SHA, $remoteSha);
        Configuration::updateValue(self::CFG_REMOTE_DATE, $commitDate);
        Configuration::updateValue(self::CFG_REMOTE_MESSAGE, Tools::substr($commitMessage, 0, 255));
        Configuration::updateValue(self::CFG_LAST_CHECK, (string) time());
        Configuration::updateValue(self::CFG_CHECK_ERROR, '');

        return $this->getUpdateStatus(false);
    }

    /**
     * Descarga e instala la versión de la rama main.
     *
     * @return array
     *
     * @throws Exception
     */
    public function performUpdate()
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('La extensión PHP ZipArchive es requerida para actualizar el módulo.');
        }

        if (!is_writable($this->moduleDir)) {
            throw new Exception('El directorio del módulo no tiene permisos de escritura.');
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '256M');

        $status = $this->checkForUpdates();
        $remoteSha = $status['remote_sha'];
        $remoteVersion = $status['remote_version'];

        if (empty($remoteSha)) {
            throw new Exception('No se pudo obtener el commit remoto desde GitHub.');
        }

        $tmpDir = $this->moduleDir . 'cache' . DIRECTORY_SEPARATOR . 'update_' . uniqid('', true) . DIRECTORY_SEPARATOR;
        if (!mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new Exception('No se pudo crear el directorio temporal de actualización.');
        }

        $zipPath = $tmpDir . 'module.zip';

        try {
            $this->downloadZipball($zipPath);
            $extractDir = $tmpDir . 'extracted' . DIRECTORY_SEPARATOR;
            if (!mkdir($extractDir, 0755, true) && !is_dir($extractDir)) {
                throw new Exception('No se pudo crear el directorio de extracción.');
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('No se pudo abrir el ZIP descargado desde GitHub.');
            }
            $zip->extractTo($extractDir);
            $zip->close();

            $sourceRoot = $this->findExtractedRoot($extractDir);
            if ($sourceRoot === null) {
                throw new Exception('No se encontró el contenido del módulo en el ZIP.');
            }

            if (!file_exists($sourceRoot . 'prestashopyuju.php')) {
                throw new Exception('El ZIP no parece ser un módulo PrestaShop Yuju válido.');
            }

            $this->copyModuleFiles($sourceRoot, $this->moduleDir);

            $newVersion = $this->parseVersionFromFile($this->moduleDir . 'prestashopyuju.php');
            if ($newVersion === null) {
                $newVersion = $remoteVersion ?: $this->localVersion;
            }

            Module::upgradeModuleVersion('prestashopyuju', $newVersion);
            Configuration::updateValue(self::CFG_INSTALLED_SHA, $remoteSha);
            Configuration::updateValue(self::CFG_AVAILABLE, '0');
            Configuration::updateValue(self::CFG_REMOTE_VERSION, $newVersion);
            Configuration::updateValue(self::CFG_LAST_CHECK, (string) time());
            Configuration::updateValue(self::CFG_CHECK_ERROR, '');

            $this->clearCaches();

            return [
                'success' => true,
                'message' => 'Módulo actualizado correctamente a la versión ' . $newVersion . '.',
                'version' => $newVersion,
                'sha' => $remoteSha,
            ];
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    private function fetchLatestCommit()
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/commits/%s',
            self::GITHUB_OWNER,
            self::GITHUB_REPO,
            self::GITHUB_BRANCH
        );

        $response = $this->httpGet($url);
        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['sha'])) {
            throw new Exception('Respuesta inválida al consultar commits de GitHub.');
        }

        return $data;
    }

    /**
     * @return string
     *
     * @throws Exception
     */
    private function fetchRemoteModuleVersion()
    {
        $url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/prestashopyuju.php',
            self::GITHUB_OWNER,
            self::GITHUB_REPO,
            self::GITHUB_BRANCH
        );

        $content = $this->httpGet($url);
        $version = $this->parseVersionFromContent($content);

        if ($version === null) {
            throw new Exception('No se pudo leer la versión remota del módulo.');
        }

        return $version;
    }

    /**
     * @param string $destination
     *
     * @throws Exception
     */
    private function downloadZipball($destination)
    {
        $url = sprintf(
            'https://codeload.github.com/%s/%s/zip/refs/heads/%s',
            self::GITHUB_OWNER,
            self::GITHUB_REPO,
            self::GITHUB_BRANCH
        );

        $content = $this->httpGet($url, true);
        if ($content === '' || strlen($content) < 100) {
            throw new Exception('La descarga del ZIP desde GitHub falló o está vacía.');
        }

        if (file_put_contents($destination, $content) === false) {
            throw new Exception('No se pudo guardar el ZIP descargado.');
        }
    }

    /**
     * @param string $url
     * @param bool   $binary
     *
     * @return string
     *
     * @throws Exception
     */
    private function httpGet($url, $binary = false)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_USERAGENT => 'PrestaShop-Yuju-Updater/' . $this->localVersion,
                CURLOPT_HTTPHEADER => [
                    'Accept: ' . ($binary ? 'application/octet-stream' : 'application/vnd.github+json'),
                    'X-GitHub-Api-Version: 2022-11-28',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode < 200 || $httpCode >= 300) {
                throw new Exception(
                    'Error HTTP al contactar GitHub' .
                    ($httpCode ? ' (código ' . $httpCode . ')' : '') .
                    ($error ? ': ' . $error : '')
                );
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: PrestaShop-Yuju-Updater/" . $this->localVersion . "\r\n" .
                    'Accept: ' . ($binary ? 'application/octet-stream' : 'application/vnd.github+json') . "\r\n",
                'timeout' => 120,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new Exception('No se pudo contactar GitHub. Verifique la conectividad del servidor.');
        }

        return (string) $body;
    }

    /**
     * @param string $extractDir
     *
     * @return string|null
     */
    private function findExtractedRoot($extractDir)
    {
        $entries = scandir($extractDir);
        if ($entries === false) {
            return null;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $extractDir . $entry . DIRECTORY_SEPARATOR;
            if (is_dir($path) && file_exists($path . 'prestashopyuju.php')) {
                return $path;
            }
        }

        if (file_exists($extractDir . 'prestashopyuju.php')) {
            return $extractDir;
        }

        return null;
    }

    /**
     * @param string $source
     * @param string $destination
     *
     * @throws Exception
     */
    private function copyModuleFiles($source, $destination)
    {
        $excludeNames = [
            '.git',
            '.github',
            'logs',
            'cache',
            'exports',
            'vendor',
            'node_modules',
            '.idea',
            '.vscode',
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($source));
            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            $parts = explode('/', $relative);

            if (in_array($parts[0], $excludeNames, true)) {
                continue;
            }

            $target = $destination . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new Exception('No se pudo crear el directorio: ' . $relative);
                }
            } else {
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
                    throw new Exception('No se pudo crear el directorio padre para: ' . $relative);
                }
                if (!copy($item->getPathname(), $target)) {
                    throw new Exception('No se pudo copiar el archivo: ' . $relative);
                }
            }
        }
    }

    /**
     * @param string $file
     *
     * @return string|null
     */
    private function parseVersionFromFile($file)
    {
        if (!file_exists($file)) {
            return null;
        }

        return $this->parseVersionFromContent((string) file_get_contents($file));
    }

    /**
     * @param string $content
     *
     * @return string|null
     */
    private function parseVersionFromContent($content)
    {
        if (preg_match("/\\\$this->version\\s*=\\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function clearCaches()
    {
        if (method_exists('Tools', 'clearSf2Cache')) {
            try {
                Tools::clearSf2Cache();
            } catch (Exception $e) {
                // ignore
            }
        }

        if (class_exists('Cache')) {
            try {
                Cache::clean('*');
            } catch (Exception $e) {
                // ignore
            }
        }
    }

    /**
     * @param string $dir
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}


