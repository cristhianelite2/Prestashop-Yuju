<?php
/**
 * Secure temporary storage and streaming parser for Yuju JSONL reports.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class YujuProductReportFileStore
{
    const MAX_REPORT_BYTES = 268435456;
    const MAX_LINE_BYTES = 10485760;
    const MAX_REDIRECTS = 3;

    private $directory;
    private $custom_directory;
    private $resolver;
    private $transport;
    private $max_report_bytes;

    public function __construct($directory = null, $resolver = null, $transport = null, $max_report_bytes = self::MAX_REPORT_BYTES)
    {
        $this->custom_directory = $directory !== null;

        if ($directory === null) {
            // Keep the report with the module so it survives system-temp cleanup/reboots.
            // The directory is private and protected against direct HTTP access.
            $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports';
        }

        $this->directory = $directory;
        $this->resolver = $resolver;
        $this->transport = $transport;
        $this->max_report_bytes = max(1, (int) $max_report_bytes);
    }

    /**
     * Download, validate and cache a report file returned by Yuju.
     *
     * @param string $url Signed URL returned by the authenticated report API
     *
     * @return array Cache ID and validated report summary; never returns the URL
     */
    public function downloadAndValidate($url)
    {
        $this->ensurePrivateDirectory();
        $cache_id = bin2hex(random_bytes(16));
        $path = $this->getPath($cache_id);
        $current_url = $url;

        try {
            for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; ++$redirect) {
                $target = $this->validateReportUrl($current_url);
                $result = $this->downloadOnce($current_url, $path, $target['host'], $target['ip']);

                if ($result['status'] >= 300 && $result['status'] < 400) {
                    if ($redirect === self::MAX_REDIRECTS || empty($result['location'])) {
                        throw new RuntimeException('The report download exceeded the allowed redirects.');
                    }

                    $current_url = $this->resolveRedirect($current_url, $result['location']);
                    continue;
                }

                if ($result['status'] < 200 || $result['status'] >= 300) {
                    throw new RuntimeException('Yuju report download failed with HTTP ' . (int) $result['status'] . '.');
                }

                if (!is_file($path) || filesize($path) < 0 || filesize($path) > $this->max_report_bytes) {
                    throw new RuntimeException('The downloaded Yuju report is missing or exceeds the size limit.');
                }

                @chmod($path, 0600);
                $product_count = 0;
                foreach ($this->readProducts($cache_id) as $product) {
                    ++$product_count;
                }

                // A successful HTTP response with an empty file is not a usable product report.
                // Otherwise the UI marks it cached, but reconciliation sees zero rows and reports
                // a misleading "no cached report" error.
                if ($product_count === 0) {
                    throw new RuntimeException('The downloaded Yuju report contains no product rows.');
                }

                return [
                    'cache_id' => $cache_id,
                    'product_count' => $product_count,
                    'size_bytes' => (int) filesize($path),
                    'downloaded_at' => time(),
                ];
            }
        } catch (Exception $e) {
            $this->deleteReport($cache_id);
            throw $e;
        }

        $this->deleteReport($cache_id);
        throw new RuntimeException('Yuju report download did not complete.');
    }

    /**
     * Download the raw report file from a temporary URL returned by Yuju.
     * The URL is validated on every redirect to prevent SSRF.
     *
     * @param string $url
     * @return array
     */
    public function downloadRawReportFile($url)
    {
        $this->ensurePrivateDirectory();
        $cache_id = bin2hex(random_bytes(16));
        $path = $this->directory . DIRECTORY_SEPARATOR . $cache_id . '.json';
        $current_url = $url;

        try {
            for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; ++$redirect) {
                $target = $this->validateReportUrl($current_url);
                $result = $this->downloadOnce($current_url, $path, $target['host'], $target['ip']);

                if ($result['status'] >= 300 && $result['status'] < 400) {
                    if ($redirect === self::MAX_REDIRECTS || empty($result['location'])) {
                        throw new RuntimeException('La descarga del reporte excedió el número permitido de redirecciones.');
                    }
                    $current_url = $this->resolveRedirect($current_url, $result['location']);
                    continue;
                }

                if ($result['status'] < 200 || $result['status'] >= 300) {
                    throw new RuntimeException('La descarga del archivo de ofertas falló con HTTP ' . (int) $result['status'] . '.');
                }

                clearstatcache(true, $path);
                if (!is_file($path) || is_link($path) || filesize($path) <= 0 || filesize($path) > $this->max_report_bytes) {
                    throw new RuntimeException('El archivo de ofertas descargado está vacío o excede el tamaño permitido.');
                }

                @chmod($path, 0600);
                return [
                    'cache_id' => $cache_id,
                    'relative_path' => 'var/reports/' . $cache_id . '.json',
                    'size_bytes' => (int) filesize($path),
                    'saved_at' => time(),
                ];
            }
        } catch (Exception $e) {
            @unlink($path);
            throw $e;
        }

        @unlink($path);
        throw new RuntimeException('La descarga del archivo de ofertas no pudo completarse.');
    }

    /**
     * Save a JSON response privately without assuming its schema.
     */
    public function saveJsonPayload($payload)
    {
        $this->ensurePrivateDirectory();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('The Yuju offer report response could not be encoded as JSON.');
        }
        if (strlen($json) > $this->max_report_bytes) {
            throw new RuntimeException('The Yuju offer report response exceeds the configured size limit.');
        }
        $cache_id = bin2hex(random_bytes(16));
        $path = $this->directory . DIRECTORY_SEPARATOR . $cache_id . '.json';
        $written = file_put_contents($path, $json, LOCK_EX);
        if ($written === false || $written !== strlen($json)) {
            @unlink($path);
            throw new RuntimeException('The Yuju offer report response could not be saved to disk.');
        }
        @chmod($path, 0600);
        return ['cache_id' => $cache_id, 'relative_path' => 'var/reports/' . $cache_id . '.json', 'size_bytes' => (int) $written, 'saved_at' => time()];
    }

    public function deleteJsonPayload($cache_id)
    {
        if (!is_string($cache_id) || !preg_match('/^[a-f0-9]{32}$/', $cache_id)) {
            throw new InvalidArgumentException('Invalid Yuju report cache ID.');
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . $cache_id . '.json';
        return !file_exists($path) || (!is_link($path) && unlink($path));
    }

    /**
     * Stream validated parent product rows from a cached JSONL file.
     *
     * @param string $cache_id
     *
     * @return Generator
     */
    public function readProducts($cache_id)
    {
        $path = $this->getPath($cache_id);

        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('The cached Yuju report is no longer available.');
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException('Could not open the cached Yuju report.');
        }

        $line_number = 0;

        try {
            while (($line = fgets($handle, self::MAX_LINE_BYTES + 2)) !== false) {
                ++$line_number;

                if (strlen($line) > self::MAX_LINE_BYTES && substr($line, -1) !== "\n") {
                    throw new RuntimeException('Yuju report line ' . $line_number . ' exceeds the size limit.');
                }

                if ($line_number === 1) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                }

                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $product = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($product)) {
                    throw new RuntimeException('Yuju report line ' . $line_number . ' is not valid JSON.');
                }

                $product_id = $this->getScalar($product, ['id_product', 'id', 'product_id']);
                if ($product_id === null || $product_id === '') {
                    throw new RuntimeException('Yuju report line ' . $line_number . ' has no product ID.');
                }
                $product['id_product'] = (string) $product_id;

                $product['_yuju_report_has_variations'] = array_key_exists('variations', $product);
                if (!$product['_yuju_report_has_variations']) {
                    $product['variations'] = [];
                } elseif (!is_array($product['variations'])) {
                    throw new RuntimeException('Yuju report line ' . $line_number . ' has invalid variations data.');
                }

                foreach ($product['variations'] as $variation) {
                    if (!is_array($variation)) {
                        throw new RuntimeException('Yuju report line ' . $line_number . ' contains an invalid variation.');
                    }

                    $sku = $this->getScalar($variation, ['sku']);
                    if ($sku === null || trim((string) $sku) === '') {
                        throw new RuntimeException('Yuju report line ' . $line_number . ' contains a variation without SKU.');
                    }
                }

                yield $product;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Find the newest valid report already stored inside this module.
     *
     * This is a read-only recovery path for deployments where the JSONL file
     * exists but the old Configuration state no longer points to it.
     * Files are only accepted after the same streaming validation used for
     * freshly downloaded reports.
     *
     * @return array|null
     */
    public function discoverLatestValidReport()
    {
        $this->ensurePrivateDirectory();
        $paths = glob($this->directory . DIRECTORY_SEPARATOR . '*.jsonl');

        if (!is_array($paths) || !$paths) {
            return null;
        }

        usort($paths, function ($left, $right) {
            return (int) filemtime($right) <=> (int) filemtime($left);
        });

        foreach ($paths as $path) {
            $cache_id = basename($path, '.jsonl');
            if (is_link($path) || !preg_match('/^[a-f0-9]{32}$/', $cache_id)) {
                continue;
            }

            $size = filesize($path);
            if ($size === false || $size <= 0 || $size > $this->max_report_bytes) {
                continue;
            }

            try {
                $product_count = 0;
                foreach ($this->readProducts($cache_id) as $unused_product) {
                    ++$product_count;
                }
            } catch (Exception $e) {
                // Ignore invalid or partially written files and try the next candidate.
                continue;
            }

            if ($product_count < 1) {
                continue;
            }

            return [
                'cache_id' => $cache_id,
                'product_count' => $product_count,
                'size_bytes' => (int) $size,
                'downloaded_at' => (int) filemtime($path),
            ];
        }

        return null;
    }


    /**
     * Find the newest valid offer report already stored locally.
     *
     * This lets the module recover after an upgrade or a missing Configuration
     * pointer without requesting another report URL from Yuju.
     *
     * @return array|null
     */
    public function discoverLatestValidOfferReport()
    {
        $this->ensurePrivateDirectory();
        $paths = glob($this->directory . DIRECTORY_SEPARATOR . '*.json');

        if (!is_array($paths) || !$paths) {
            return null;
        }

        usort($paths, function ($left, $right) {
            return (int) filemtime($right) <=> (int) filemtime($left);
        });

        foreach ($paths as $path) {
            $cache_id = basename($path, '.json');
            if (is_link($path) || !preg_match('/^[a-f0-9]{32}$/', $cache_id)) {
                continue;
            }

            $size = filesize($path);
            if ($size === false || $size <= 0 || $size > $this->max_report_bytes) {
                continue;
            }

            try {
                $products = $this->readOfferProducts($cache_id);
            } catch (Exception $e) {
                // Ignore old URL-response payloads, invalid JSON and partial files.
                continue;
            }

            if (!$products) {
                continue;
            }

            return [
                'cache_id' => $cache_id,
                'product_count' => count($products),
                'report_size_bytes' => (int) $size,
                'report_downloaded_at' => (int) filemtime($path),
                'saved_at' => (int) filemtime($path),
            ];
        }

        return null;
    }

    /** Parse the downloaded offer JSON. Supports Yuju's JSON array and JSONL. */
    public function readOfferProducts($cache_id)
    {
        if (!is_string($cache_id) || !preg_match('/^[a-f0-9]{32}$/', $cache_id)) {
            throw new InvalidArgumentException('Invalid Yuju offer report cache ID.');
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . $cache_id . '.json';
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('El archivo del reporte de ofertas ya no está disponible.');
        }
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > $this->max_report_bytes) {
            throw new RuntimeException('El archivo del reporte de ofertas está vacío o excede el tamaño permitido.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el archivo del reporte de ofertas.');
        }
        $decoded = json_decode($contents, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded['products']) && is_array($decoded['products'])) {
                $decoded = $decoded['products'];
            } elseif (isset($decoded['data']) && is_array($decoded['data']) && !isset($decoded['id_product'])) {
                $decoded = $decoded['data'];
            }
            if (isset($decoded['id_product'])) {
                $decoded = [$decoded];
            }
            if (!is_array($decoded)) {
                throw new RuntimeException('El JSON del reporte de ofertas no contiene una lista de productos.');
            }
            $rows = $decoded;
        } else {
            $rows = [];
            foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $row = json_decode($line, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($row)) {
                    throw new RuntimeException('El reporte de ofertas contiene una fila JSON inválida.');
                }
                $rows[] = $row;
            }
        }
        $products = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('El reporte de ofertas contiene un registro inválido.');
            }
            $id = isset($row['id_product']) ? $row['id_product']
                : (isset($row['id']) ? $row['id'] : (isset($row['product_id']) ? $row['product_id'] : null));
            if (!is_scalar($id) || trim((string) $id) === '') {
                throw new RuntimeException('Un producto del reporte de ofertas no tiene id_product.');
            }
            $product = ['id_product' => (string) $id];
            foreach (['stock', 'price'] as $field) {
                if (array_key_exists($field, $row) && is_scalar($row[$field]) && $row[$field] !== '') {
                    if (!is_numeric($row[$field])) {
                        throw new RuntimeException('El campo ' . $field . ' no es numérico en el reporte de ofertas.');
                    }
                    $product[$field] = $row[$field];
                }
            }
            $products[] = $product;
        }
        if (!$products) throw new RuntimeException('El reporte de ofertas no contiene productos válidos.');
        return $products;
    }

    public function reportExists($cache_id)
    {
        $path = $this->getPath($cache_id);

        return is_file($path) && !is_link($path);
    }

    public function deleteReport($cache_id)
    {
        $path = $this->getPath($cache_id);

        return !file_exists($path) || (!is_link($path) && unlink($path));
    }

    /**
     * Remove only expired files created by this class.
     */
    public function cleanupExpired($max_age = 86400)
    {
        $this->ensurePrivateDirectory();
        $deleted = 0;
        $files = array_merge(
            glob($this->directory . DIRECTORY_SEPARATOR . '*.jsonl') ?: [],
            glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: []
        );

        foreach ($files as $path) {
            if (is_link($path) || !preg_match('/^[a-f0-9]{32}\.(jsonl|json)$/', basename($path))) {
                continue;
            }

            if (filemtime($path) < time() - max(0, (int) $max_age) && unlink($path)) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    protected function validateReportUrl($url)
    {
        if (!is_string($url) || $url === '') {
            throw new InvalidArgumentException('Yuju returned an empty report URL.');
        }

        $parts = parse_url($url);
        if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https'
            || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
        ) {
            throw new InvalidArgumentException('Yuju returned a report URL that is not a safe HTTPS URL.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $addresses = $this->resolvePublicAddresses($host);

        if (empty($addresses)) {
            throw new InvalidArgumentException('Could not resolve the report host to a public address.');
        }

        foreach ($addresses as $address) {
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('The report host resolves to a non-public IP address.');
            }
        }

        return ['host' => $host, 'ip' => reset($addresses)];
    }

    protected function resolvePublicAddresses($host)
    {
        if ($this->resolver) {
            $addresses = call_user_func($this->resolver, $host);
        } elseif (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            $addresses = gethostbynamel($host);
        }

        return is_array($addresses) ? array_values(array_unique($addresses)) : [];
    }

    protected function resolveRedirect($current_url, $location)
    {
        $location = trim((string) $location);
        $parts = parse_url($location);

        if (!empty($parts['scheme'])) {
            return $location;
        }

        if (strpos($location, '/') !== 0 || strpos($location, '//') === 0) {
            throw new InvalidArgumentException('Yuju returned an unsupported relative report redirect.');
        }

        $current = parse_url($current_url);

        return 'https://' . $current['host'] . $location;
    }

    protected function downloadOnce($url, $path, $host, $ip)
    {
        if ($this->transport) {
            return call_user_func($this->transport, $url, $path, $ip, $this->max_report_bytes);
        }

        $handle = fopen($path, 'wb');
        if (!$handle) {
            throw new RuntimeException('Could not create the temporary Yuju report file.');
        }
        @chmod($path, 0600);

        $downloaded = 0;
        $location = null;
        $content_length = null;
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$host . ':443:' . $ip],
            CURLOPT_HTTPHEADER => ['Accept: application/jsonl, application/json, text/plain'],
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$location, &$content_length) {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, strlen('Location:')));
                } elseif (stripos($header, 'Content-Length:') === 0) {
                    $content_length = (int) trim(substr($header, strlen('Content-Length:')));
                }

                return strlen($header);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use ($handle, &$downloaded, &$content_length) {
                $chunk_length = strlen($chunk);
                if ($downloaded + $chunk_length > $this->max_report_bytes
                    || ($content_length !== null && $content_length > $this->max_report_bytes)
                ) {
                    return 0;
                }

                $written = fwrite($handle, $chunk);
                if ($written === false) {
                    return 0;
                }
                $downloaded += $written;

                return $written;
            },
        ]);

        $result = curl_exec($curl);
        $curl_error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        fclose($handle);

        if ($result === false && $status < 300) {
            throw new RuntimeException($downloaded >= $this->max_report_bytes
                ? 'The Yuju report exceeds the configured download size limit.'
                : 'Could not download the Yuju report: ' . $curl_error
            );
        }

        return ['status' => $status, 'location' => $location];
    }

    protected function ensurePrivateDirectory()
    {
        if (is_link($this->directory)) {
            throw new RuntimeException('The report cache directory cannot be a symbolic link.');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Could not create the private Yuju report cache directory.');
        }

        $real_directory = realpath($this->directory);
        $module_root = realpath(dirname(__DIR__));
        $expected_root = $module_root
            ? $module_root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports'
            : false;

        if (!$real_directory || is_link($real_directory)
            || (!$this->custom_directory && (!$module_root || !$expected_root || $real_directory !== $expected_root))
        ) {
            throw new RuntimeException('The Yuju report cache directory is invalid.');
        }

        @chmod($real_directory, 0700);
        $this->directory = $real_directory;
    }

    protected function getPath($cache_id)
    {
        if (!is_string($cache_id) || !preg_match('/^[a-f0-9]{32}$/', $cache_id)) {
            throw new InvalidArgumentException('Invalid Yuju report cache ID.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $cache_id . '.jsonl';
    }

    protected function getScalar($row, $keys)
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && (string) $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }
}
