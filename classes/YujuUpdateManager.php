<?php
/**
 * Actualizador del módulo Yuju desde la rama main de GitHub.
 * Conserva la configuración de PrestaShop, logs y datos locales del módulo.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class YujuUpdateManager
{
    const GITHUB_OWNER = 'cristhianelite2';
    const GITHUB_REPO = 'Prestashop-Yuju';
    const GITHUB_BRANCH = 'main';
    const CHECK_INTERVAL = 86400;

    const CFG_LAST_CHECK = 'YUJU_UPDATE_LAST_CHECK';
    const CFG_AVAILABLE = 'YUJU_UPDATE_AVAILABLE';
    const CFG_REMOTE_VERSION = 'YUJU_UPDATE_REMOTE_VERSION';
    const CFG_REMOTE_SHA = 'YUJU_UPDATE_REMOTE_SHA';
    const CFG_INSTALLED_SHA = 'YUJU_UPDATE_INSTALLED_SHA';
    const CFG_REMOTE_DATE = 'YUJU_UPDATE_REMOTE_DATE';
    const CFG_REMOTE_MESSAGE = 'YUJU_UPDATE_REMOTE_MESSAGE';
    const CFG_CHECK_ERROR = 'YUJU_UPDATE_CHECK_ERROR';

    private $moduleDir;
    private $moduleName;
    private $localVersion;

    public function __construct($localVersion = null, $moduleName = 'yuju')
    {
        $moduleName = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $moduleName);
        if ($moduleName === '') {
            $moduleName = 'yuju';
        }

        $this->moduleName = $moduleName;
        $this->moduleDir = _PS_MODULE_DIR_ . $this->moduleName . DIRECTORY_SEPARATOR;
        $this->localVersion = $localVersion !== null
            ? (string) $localVersion
            : (string) Configuration::get('PS_VERSION_DB');
    }

    public function getUpdateStatus($force = false)
    {
        $lastCheck = (int) Configuration::get(self::CFG_LAST_CHECK);
        $needsCheck = $force || !$lastCheck || (time() - $lastCheck) >= self::CHECK_INTERVAL;

        if ($needsCheck) {
            try {
                $this->checkForUpdates();
            } catch (Throwable $e) {
                Configuration::updateValue(self::CFG_CHECK_ERROR, Tools::substr($e->getMessage(), 0, 1000));
                Configuration::updateValue(self::CFG_LAST_CHECK, (string) time());
            }
        }

        return $this->buildStatus();
    }

    public function checkForUpdates()
    {
        $commit = $this->fetchLatestCommit();
        $remoteSha = isset($commit['sha']) ? strtolower((string) $commit['sha']) : '';
        if (!preg_match('/^[a-f0-9]{40}$/', $remoteSha)) {
            throw new Exception('GitHub no devolvió un identificador de commit válido.');
        }

        // Leer la versión desde el mismo commit que se comparará/descargará.
        $remoteVersion = $this->fetchRemoteModuleVersion($remoteSha);
        $installedSha = strtolower((string) Configuration::get(self::CFG_INSTALLED_SHA));
        $versionNewer = version_compare($remoteVersion, $this->localVersion, '>');
        $versionNotOlder = version_compare($remoteVersion, $this->localVersion, '>=');
        $shaDifferent = $installedSha === '' || !hash_equals($installedSha, $remoteSha);

        // Si no hay SHA local, la instalación no está identificada: ofrecer una
        // actualización inicial en vez de marcar silenciosamente el commit remoto como instalado.
        $updateAvailable = $versionNewer || ($versionNotOlder && $shaDifferent);

        $commitDate = '';
        if (!empty($commit['commit']['committer']['date'])) {
            $commitDate = (string) $commit['commit']['committer']['date'];
        } elseif (!empty($commit['commit']['author']['date'])) {
            $commitDate = (string) $commit['commit']['author']['date'];
        }

        $message = '';
        if (!empty($commit['commit']['message'])) {
            $parts = preg_split('/\r\n|\r|\n/', (string) $commit['commit']['message']);
            $message = trim($parts[0]);
        }

        Configuration::updateValue(self::CFG_AVAILABLE, $updateAvailable ? '1' : '0');
        Configuration::updateValue(self::CFG_REMOTE_VERSION, $remoteVersion);
        Configuration::updateValue(self::CFG_REMOTE_SHA, $remoteSha);
        Configuration::updateValue(self::CFG_REMOTE_DATE, $commitDate);
        Configuration::updateValue(self::CFG_REMOTE_MESSAGE, Tools::substr($message, 0, 255));
        Configuration::updateValue(self::CFG_LAST_CHECK, (string) time());
        Configuration::updateValue(self::CFG_CHECK_ERROR, '');

        return $this->buildStatus();
    }

    public function performUpdate()
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('La extensión PHP ZipArchive es necesaria para obtener actualizaciones.');
        }
        if (!is_dir($this->moduleDir) || !is_writable($this->moduleDir)) {
            throw new Exception('El directorio del módulo no existe o no tiene permisos de escritura.');
        }

        @set_time_limit(300);
        @ini_set('memory_limit', '256M');

        // Revalidar justo antes de descargar e instalar, y fijar la descarga a ese commit.
        $status = $this->checkForUpdates();
        if (empty($status['remote_sha'])) {
            throw new Exception('No se pudo identificar el commit de GitHub.');
        }
        if (empty($status['update_available'])) {
            return [
                'success' => true,
                'message' => 'El módulo ya está actualizado con la rama main.',
                'version' => $status['remote_version'],
                'sha' => $status['remote_sha'],
                'files_copied' => 0,
            ];
        }

        $cacheDir = $this->moduleDir . 'cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
            throw new Exception('No se pudo preparar el directorio temporal del actualizador.');
        }
        if (!is_writable($cacheDir)) {
            throw new Exception('El directorio cache del módulo no permite crear archivos temporales.');
        }

        $tmpDir = $cacheDir . 'update_' . str_replace('.', '', uniqid('', true)) . DIRECTORY_SEPARATOR;
        if (!@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new Exception('No se pudo crear el directorio temporal de actualización.');
        }

        try {
            $zipPath = $tmpDir . 'module.zip';
            $this->downloadZipball($status['remote_sha'], $zipPath);

            $extractDir = $tmpDir . 'extracted' . DIRECTORY_SEPARATOR;
            if (!@mkdir($extractDir, 0755, true) && !is_dir($extractDir)) {
                throw new Exception('No se pudo crear el directorio de extracción.');
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new Exception('No se pudo abrir el ZIP descargado desde GitHub.');
            }

            try {
                $this->validateZipEntries($zip);
                if (!$zip->extractTo($extractDir)) {
                    throw new Exception('No se pudo extraer el ZIP descargado desde GitHub.');
                }
            } finally {
                $zip->close();
            }

            $sourceRoot = $this->findExtractedRoot($extractDir);
            $entryFile = $this->moduleName . '.php';
            if ($sourceRoot === null || !is_file($sourceRoot . $entryFile)) {
                throw new Exception('El ZIP no contiene el archivo principal "' . $entryFile . '". No se copió ningún archivo.');
            }

            $remoteVersionFromZip = $this->parseVersionFromFile($sourceRoot . $entryFile);
            if ($remoteVersionFromZip === null || version_compare($remoteVersionFromZip, $status['remote_version'], '!=')) {
                throw new Exception('La versión del ZIP no coincide con la versión consultada a GitHub. Vuelva a comprobar las actualizaciones.');
            }

            $filesCopied = $this->copyModuleFiles($sourceRoot, $this->moduleDir);

            // Asegurar que los hooks de vistas de órdenes y assets estén registrados tras actualizar
            try {
                if (file_exists($this->moduleDir . 'yuju.php')) {
                    require_once $this->moduleDir . 'yuju.php';
                    if (class_exists('Yuju')) {
                        $moduleInstance = new Yuju();
                        $moduleInstance->ensureOrderDetailHooks();
                    }
                }
            } catch (Throwable $e) {
                // Continuar sin interrumpir el proceso de actualización
            }

            if (class_exists('Module') && method_exists('Module', 'upgradeModuleVersion')) {
                Module::upgradeModuleVersion($this->moduleName, $remoteVersionFromZip);
            }

            Configuration::updateValue(self::CFG_INSTALLED_SHA, $status['remote_sha']);
            Configuration::updateValue(self::CFG_AVAILABLE, '0');
            Configuration::updateValue(self::CFG_REMOTE_VERSION, $remoteVersionFromZip);
            Configuration::updateValue(self::CFG_LAST_CHECK, (string) time());
            Configuration::updateValue(self::CFG_CHECK_ERROR, '');

            $this->clearCaches();

            return [
                'success' => true,
                'message' => 'Actualización obtenida e instalada correctamente desde main.',
                'version' => $remoteVersionFromZip,
                'sha' => $status['remote_sha'],
                'files_copied' => $filesCopied,
            ];
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    private function buildStatus()
    {
        $lastCheck = (int) Configuration::get(self::CFG_LAST_CHECK);

        return [
            'update_available' => (bool) Configuration::get(self::CFG_AVAILABLE),
            'local_version' => $this->localVersion,
            'remote_version' => (string) Configuration::get(self::CFG_REMOTE_VERSION) ?: $this->localVersion,
            'remote_sha' => (string) Configuration::get(self::CFG_REMOTE_SHA),
            'installed_sha' => (string) Configuration::get(self::CFG_INSTALLED_SHA),
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

    private function fetchLatestCommit()
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/commits/%s',
            self::GITHUB_OWNER,
            self::GITHUB_REPO,
            self::GITHUB_BRANCH
        );
        $data = json_decode($this->httpGet($url, 'application/vnd.github+json'), true);

        if (!is_array($data) || empty($data['sha'])) {
            throw new Exception('Respuesta inválida al consultar la rama main en GitHub.');
        }
        return $data;
    }

    private function fetchRemoteModuleVersion($sha)
    {
        $url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s.php',
            self::GITHUB_OWNER,
            self::GITHUB_REPO,
            rawurlencode($sha),
            rawurlencode($this->moduleName)
        );
        $version = $this->parseVersionFromContent($this->httpGet($url, 'text/plain'));

        if ($version === null) {
            throw new Exception('No se pudo leer la versión de ' . $this->moduleName . '.php desde GitHub.');
        }
        return $version;
    }

    private function downloadZipball($sha, $destination)
    {
        $url = sprintf(
            'https://codeload.github.com/%s/%s/zip/%s',
            self::GITHUB_OWNER,
            self::GITHUB_REPO,
            rawurlencode($sha)
        );
        $content = $this->httpGet($url, 'application/zip');

        if ($content === '' || strlen($content) < 100 || strlen($content) > 52428800) {
            throw new Exception('El ZIP de GitHub está vacío o excede el tamaño máximo de 50 MB.');
        }
        if (substr($content, 0, 2) !== 'PK') {
            throw new Exception('La respuesta de GitHub no parece ser un archivo ZIP válido.');
        }
        if (file_put_contents($destination, $content, LOCK_EX) === false) {
            throw new Exception('No se pudo guardar el ZIP descargado.');
        }
    }

    private function httpGet($url, $accept)
    {
        $parts = parse_url($url);
        $allowedHosts = ['api.github.com', 'raw.githubusercontent.com', 'codeload.github.com'];
        if (empty($parts['scheme']) || $parts['scheme'] !== 'https' || empty($parts['host']) || !in_array(strtolower($parts['host']), $allowedHosts, true)) {
            throw new Exception('Se bloqueó una URL no permitida en el actualizador.');
        }

        $userAgent = 'PrestaShop-Yuju-Updater/' . $this->localVersion;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_HTTPHEADER => [
                    'Accept: ' . $accept,
                    'X-GitHub-Api-Version: 2022-11-28',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode < 200 || $httpCode >= 300) {
                throw new Exception(
                    'Error al contactar GitHub' . ($httpCode ? ' (HTTP ' . $httpCode . ')' : '') .
                    ($error ? ': ' . $error : '') .
                    ($httpCode === 403 ? '. GitHub puede haber limitado temporalmente las consultas.' : '')
                );
            }
            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: " . $userAgent . "\r\nAccept: " . $accept . "\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
                'timeout' => 120,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        $httpStatus = 0;
        foreach ($responseHeaders as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
                $httpStatus = (int) $matches[1];
            }
        }

        if ($body === false || $httpStatus < 200 || $httpStatus >= 300) {
            throw new Exception('No se pudo descargar información desde GitHub' . ($httpStatus ? ' (HTTP ' . $httpStatus . ')' : '') . '. Verifique la conectividad saliente del servidor.');
        }
        return (string) $body;
    }

    private function validateZipEntries(ZipArchive $zip)
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                throw new Exception('El ZIP de GitHub contiene una entrada ilegible.');
            }

            $normalized = str_replace('\\', '/', $name);
            if ($normalized === '' || strpos($normalized, '/') === 0 || preg_match('/^[a-zA-Z]:/', $normalized)) {
                throw new Exception('El ZIP contiene una ruta absoluta no permitida.');
            }
            foreach (explode('/', $normalized) as $part) {
                if ($part === '..') {
                    throw new Exception('El ZIP contiene una ruta fuera del directorio de extracción.');
                }
            }

            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $mode = ($attributes >> 16) & 0170000;
                    if ($mode === 0120000) {
                        throw new Exception('El ZIP contiene un enlace simbólico no permitido.');
                    }
                }
            }
        }
    }

    private function findExtractedRoot($extractDir)
    {
        $entries = @scandir($extractDir);
        if (!is_array($entries)) {
            return null;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $extractDir . $entry . DIRECTORY_SEPARATOR;
            if (is_dir($path) && is_file($path . $this->moduleName . '.php')) {
                return $path;
            }
        }
        if (is_file($extractDir . $this->moduleName . '.php')) {
            return $extractDir;
        }
        return null;
    }

    private function copyModuleFiles($source, $destination)
    {
        $excludeTopLevel = [
            '.git', '.github', 'logs', 'cache', 'exports', 'vendor',
            'node_modules', '.idea', '.vscode', '.trae', 'deployment',
        ];
        $copied = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source));
            $relative = ltrim(str_replace('\\', '/', $relative), '/');
            if ($relative === '') {
                continue;
            }

            $parts = explode('/', $relative);
            if (in_array($parts[0], $excludeTopLevel, true)) {
                continue;
            }
            $baseName = basename($relative);
            if ($baseName === '.DS_Store' || $baseName === 'debug.log' || $baseName === '.env' || preg_match('/\.log(?:\.\d+)?$/i', $baseName)) {
                continue;
            }

            $target = $destination . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new Exception('No se pudo crear el directorio "' . $relative . '".');
                }
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }

            $parent = dirname($target);
            if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
                throw new Exception('No se pudo crear el directorio padre para "' . $relative . '".');
            }

            // Escribir primero a un archivo temporal y renombrarlo para evitar archivos parciales.
            $tempTarget = $target . '.yuju-tmp-' . substr(sha1(uniqid('', true)), 0, 10);
            if (!@copy($item->getPathname(), $tempTarget)) {
                throw new Exception('No se pudo preparar el archivo "' . $relative . '".');
            }
            if (!@rename($tempTarget, $target)) {
                @unlink($tempTarget);
                throw new Exception('No se pudo reemplazar el archivo "' . $relative . '". Verifique los permisos del directorio.');
            }
            $copied++;
        }

        return $copied;
    }

    private function parseVersionFromFile($file)
    {
        if (!is_file($file)) {
            return null;
        }
        $content = @file_get_contents($file);
        return $content === false ? null : $this->parseVersionFromContent($content);
    }

    private function parseVersionFromContent($content)
    {
        if (preg_match('/\$this->version\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function clearCaches()
    {
        if (method_exists('Tools', 'clearSf2Cache')) {
            try {
                Tools::clearSf2Cache();
            } catch (Throwable $e) {
                // La copia ya está aplicada; ignorar fallos secundarios de limpieza.
            }
        }
        if (class_exists('Cache')) {
            try {
                Cache::clean('*');
            } catch (Throwable $e) {
                // No revertir archivos instalados por un fallo de caché.
            }
        }
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
