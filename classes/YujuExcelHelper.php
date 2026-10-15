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

class YujuExcelHelper
{
    /** Prefijo para los archivos de caché del filtro masivo */
    const CACHE_PREFIX = 'yuju_bulk_filter_';
    /** TTL de la lista de filtros temporales (48 horas) */
    const CACHE_TTL = 172800;

    /**
     * Parsea un archivo subido (XLSX, XLS, CSV o TXT) y extrae SKUs o nombres de productos.
     *
     * @param string $filePath Ruta local del archivo
     * @param string $originalName Nombre original del archivo para determinar la extensión
     * @return array ['success' => bool, 'items' => array, 'count' => int, 'preview' => array, 'message' => string]
     */
    public static function parseFile($filePath, $originalName = '')
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [
                'success' => false,
                'message' => 'El archivo no existe o no se puede leer.',
                'items' => [],
                'count' => 0,
                'preview' => [],
            ];
        }

        $ext = strtolower(pathinfo($originalName ?: $filePath, PATHINFO_EXTENSION));

        if ($ext === 'xlsx') {
            return self::parseXlsx($filePath);
        }

        return self::parseCsv($filePath);
    }

    /**
     * Parsea un archivo de texto/textarea y extrae los ítems (uno por línea).
     *
     * @param string $text
     * @return array Lista de ítems únicos y limpios
     */
    public static function parseTextareaLines($text)
    {
        if (!is_string($text) || trim($text) === '') {
            return [];
        }

        // Normalizar saltos de línea y limpiar BOM / caracteres invisibles
        $text = str_replace(["\xEF\xBB\xBF", "\r\n", "\r"], ["", "\n", "\n"], $text);
        $lines = explode("\n", $text);

        $items = [];
        foreach ($lines as $line) {
            $cleaned = trim($line);
            // Quitar comillas iniciales y finales si fueron pegadas desde Excel/CSV
            $cleaned = trim($cleaned, "\"' \t\n\r\0\x0B");
            if ($cleaned !== '') {
                $items[] = $cleaned;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * Parsea un archivo OpenXML Excel (.xlsx) nativamente usando ZipArchive y XML.
     *
     * @param string $filePath
     * @return array
     */
    public static function parseXlsx($filePath)
    {
        if (!class_exists('ZipArchive')) {
            // Si ZipArchive no está disponible, intentar parseo como texto/CSV por si es CSV renombrado
            return self::parseCsv($filePath);
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return [
                'success' => false,
                'message' => 'No se pudo abrir el archivo .xlsx como un archivo comprimido válido.',
                'items' => [],
                'count' => 0,
                'preview' => [],
            ];
        }

        // 1. Cargar shared strings (tabla de cadenas compartidas de Excel)
        $sharedStrings = [];
        $ssXmlContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXmlContent !== false) {
            $ssXml = @simplexml_load_string($ssXmlContent);
            if ($ssXml && isset($ssXml->si)) {
                foreach ($ssXml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $parts = [];
                        foreach ($si->r as $r) {
                            $parts[] = (string) $r->t;
                        }
                        $sharedStrings[] = implode('', $parts);
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Buscar y cargar la primera hoja de cálculo disponible
        $sheetXmlContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXmlContent === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (strpos($name, 'xl/worksheets/') === 0 && substr($name, -4) === '.xml') {
                    $sheetXmlContent = $zip->getFromIndex($i);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheetXmlContent === false) {
            return [
                'success' => false,
                'message' => 'No se encontró ninguna hoja de cálculo en el archivo .xlsx.',
                'items' => [],
                'count' => 0,
                'preview' => [],
            ];
        }

        $sheetXml = @simplexml_load_string($sheetXmlContent);
        if (!$sheetXml || !isset($sheetXml->sheetData) || !isset($sheetXml->sheetData->row)) {
            return [
                'success' => false,
                'message' => 'El archivo .xlsx no contiene datos legibles en la hoja.',
                'items' => [],
                'count' => 0,
                'preview' => [],
            ];
        }

        // 3. Extraer celdas por fila y columna
        $rawRows = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $rNum = (int) $row['r'];
            $rowValues = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
                $colLetters = isset($m[1]) ? $m[1] : 'A';
                $colIdx = self::columnLetterToNumber($colLetters);

                $val = '';
                $type = isset($cell['t']) ? (string) $cell['t'] : '';

                if ($type === 's') {
                    $idx = (int) $cell->v;
                    $val = isset($sharedStrings[$idx]) ? $sharedStrings[$idx] : '';
                } elseif ($type === 'inlineStr' && isset($cell->is) && isset($cell->is->t)) {
                    $val = (string) $cell->is->t;
                } elseif (isset($cell->v)) {
                    $val = (string) $cell->v;
                }

                $rowValues[$colIdx] = trim($val);
            }

            if (!empty(array_filter($rowValues, 'strlen'))) {
                $rawRows[$rNum] = $rowValues;
            }
        }

        ksort($rawRows);
        return self::extractItemsFromMatrix(array_values($rawRows));
    }

    /**
     * Parsea un archivo CSV o TXT delimitado.
     *
     * @param string $filePath
     * @return array
     */
    public static function parseCsv($filePath)
    {
        $handle = @fopen($filePath, 'r');
        if (!$handle) {
            return [
                'success' => false,
                'message' => 'No se pudo abrir el archivo CSV.',
                'items' => [],
                'count' => 0,
                'preview' => [],
            ];
        }

        // Leer primeros bytes para detectar delimitador (, ; o \t)
        $sample = '';
        for ($i = 0; $i < 5 && !feof($handle); $i++) {
            $sample .= fgets($handle);
        }
        rewind($handle);

        $delimiter = ',';
        $commas = substr_count($sample, ',');
        $semicolons = substr_count($sample, ';');
        $tabs = substr_count($sample, "\t");

        if ($semicolons > $commas && $semicolons >= $tabs) {
            $delimiter = ';';
        } elseif ($tabs > $commas && $tabs > $semicolons) {
            $delimiter = "\t";
        }

        $rawRows = [];
        while (($row = fgetcsv($handle, 4096, $delimiter)) !== false) {
            $cleaned = array_map(function ($col) {
                return trim(str_replace("\xEF\xBB\xBF", '', (string) $col));
            }, $row);

            if (!empty(array_filter($cleaned, 'strlen'))) {
                $rawRows[] = $cleaned;
            }
        }
        fclose($handle);

        return self::extractItemsFromMatrix($rawRows);
    }

    /**
     * Analiza una matriz de filas/columnas y extrae la lista consolidada de SKUs / nombres.
     *
     * @param array $rows
     * @return array
     */
    protected static function extractItemsFromMatrix(array $rows)
    {
        if (empty($rows)) {
            return [
                'success' => false,
                'message' => 'El archivo no contiene filas o datos.',
                'items' => [],
                'count' => 0,
                'preview' => [],
            ];
        }

        $firstRow = $rows[0];
        $isHeader = false;
        $targetCols = [];

        // Buscar encabezados comunes: sku, referencia, nombre, codigo
        foreach ($firstRow as $colIdx => $colVal) {
            $lower = strtolower(trim((string) $colVal));
            if (preg_match('/^(sku|ref|referencia|codigo|cod)$/i', $lower)) {
                $targetCols['sku'] = $colIdx;
                $isHeader = true;
            } elseif (preg_match('/^(nombre|name|producto|product|titulo|title|descripcion)$/i', $lower)) {
                $targetCols['name'] = $colIdx;
                $isHeader = true;
            }
        }

        $startRow = $isHeader ? 1 : 0;
        $items = [];

        for ($i = $startRow; $i < count($rows); $i++) {
            $row = $rows[$i];

            if (!empty($targetCols)) {
                if (isset($targetCols['sku']) && isset($row[$targetCols['sku']])) {
                    $sku = trim((string) $row[$targetCols['sku']]);
                    if ($sku !== '' && !self::isGenericHeader($sku)) {
                        $items[] = $sku;
                    }
                }
                if (isset($targetCols['name']) && isset($row[$targetCols['name']])) {
                    $name = trim((string) $row[$targetCols['name']]);
                    if ($name !== '' && !self::isGenericHeader($name)) {
                        $items[] = $name;
                    }
                }
            } else {
                // Sin cabecera detectada: tomar columna 0 y 1 si tienen valores
                $col0 = isset($row[0]) ? trim((string) $row[0]) : '';
                $col1 = isset($row[1]) ? trim((string) $row[1]) : '';

                if ($col0 !== '' && !self::isGenericHeader($col0)) {
                    $items[] = $col0;
                }
                if ($col1 !== '' && !self::isGenericHeader($col1)) {
                    $items[] = $col1;
                }
            }
        }

        $unique = array_values(array_unique(array_filter($items, 'strlen')));

        return [
            'success' => true,
            'items' => $unique,
            'count' => count($unique),
            'preview' => array_slice($unique, 0, 5),
            'message' => count($unique) . ' elementos extraídos correctamente.',
        ];
    }

    /**
     * Comprueba si una cadena es un encabezado genérico que deba omitirse.
     */
    protected static function isGenericHeader($val)
    {
        $v = strtolower(trim((string) $val));
        return in_array($v, ['sku', 'sku / referencia', 'referencia', 'reference', 'nombre', 'nombre del producto', 'name', 'product', 'código', 'codigo', 'item']);
    }

    /**
     * Convierte letras de columna (A, B, AA...) a índice entero base 0.
     */
    protected static function columnLetterToNumber($letters)
    {
        $num = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($letters[$i]) - 0x40);
        }
        return max(0, $num - 1);
    }

    /**
     * Guarda la lista de ítems en caché y retorna su identificador único.
     *
     * @param array $items
     * @return string Identificador de filtro temporal (e.g. bf_a1b2c3d4)
     */
    public static function saveFilterList(array $items)
    {
        $clean = array_values(array_unique(array_filter(array_map('trim', $items), 'strlen')));
        $id = 'bf_' . substr(md5(implode('|', $clean) . time() . uniqid()), 0, 16);

        $dir = self::getCacheDir();
        if ($dir) {
            $filePath = $dir . self::CACHE_PREFIX . $id . '.json';
            $data = [
                'id' => $id,
                'created_at' => time(),
                'count' => count($clean),
                'items' => $clean,
            ];
            @file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE));
            self::cleanOldCacheFiles($dir);
        }

        return $id;
    }

    /**
     * Recupera los ítems de un filtro masivo dado su ID.
     *
     * @param string $filterId
     * @return array
     */
    public static function getFilterList($filterId)
    {
        $id = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $filterId);
        if ($id === '') {
            return [];
        }

        $dir = self::getCacheDir();
        if (!$dir) {
            return [];
        }

        $filePath = $dir . self::CACHE_PREFIX . $id . '.json';
        if (!file_exists($filePath)) {
            return [];
        }

        $json = @file_get_contents($filePath);
        if (!$json) {
            return [];
        }

        $data = json_decode($json, true);
        if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        return [];
    }

    /**
     * Retorna la ruta al directorio de caché con permisos de escritura.
     */
    protected static function getCacheDir()
    {
        $dirs = [
            defined('_PS_CACHE_DIR_') ? _PS_CACHE_DIR_ : '',
            _PS_MODULE_DIR_ . 'yuju/cache/',
            sys_get_temp_dir() . '/yuju_cache/',
        ];

        foreach ($dirs as $dir) {
            if (!$dir) {
                continue;
            }
            $dir = rtrim($dir, '/\\') . '/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        return '';
    }

    /**
     * Elimina archivos de caché anteriores a 48 horas.
     */
    protected static function cleanOldCacheFiles($dir)
    {
        $now = time();
        $files = @glob($dir . self::CACHE_PREFIX . '*.json');
        if (is_array($files)) {
            foreach ($files as $f) {
                if ($now - @filemtime($f) > self::CACHE_TTL) {
                    @unlink($f);
                }
            }
        }
    }

    /**
     * Genera el archivo binario de la plantilla demo en formato Excel (.xlsx).
     *
     * @return string|false Contenido binario del archivo .xlsx o false si falla
     */
    public static function generateDemoXlsx()
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'yuju_demo_');
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Filtro Yuju" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><name val="Calibri"/><sz val="11"/></font>'
            . '<font><b/><name val="Calibri"/><sz val="11"/><color rgb="FFFFFFFF"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F7244"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/></border></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // xl/worksheets/sheet1.xml
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>'
            . '<col min="1" max="1" width="22" customWidth="1"/>'
            . '<col min="2" max="2" width="60" customWidth="1"/>'
            . '</cols>'
            . '<sheetData>'
            . '<row r="1">'
            . '<c r="A1" t="inlineStr" s="1"><is><t>SKU / Referencia</t></is></c>'
            . '<c r="B1" t="inlineStr" s="1"><is><t>Nombre del Producto</t></is></c>'
            . '</row>'
            . '<row r="2">'
            . '<c r="A2" t="inlineStr"><is><t>CH080-860</t></is></c>'
            . '<c r="B2" t="inlineStr"><is><t>Cable Para Audio Plug 6.3 Mono A Plug 3.5 Stereo 3:60 Mt</t></is></c>'
            . '</row>'
            . '<row r="3">'
            . '<c r="A3" t="inlineStr"><is><t>CH080-861</t></is></c>'
            . '<c r="B3" t="inlineStr"><is><t>Cable Para Audio Plug 3.5 Stereo A 2 RCA 1.8 Mt</t></is></c>'
            . '</row>'
            . '<row r="4">'
            . '<c r="A4" t="inlineStr"><is><t>CH080-862</t></is></c>'
            . '<c r="B4" t="inlineStr"><is><t>Adaptador Plug 6.3 Macho A Jack 3.5 Hembra</t></is></c>'
            . '</row>'
            . '<row r="5">'
            . '<c r="A5" t="inlineStr"><is><t>CH080-863</t></is></c>'
            . '<c r="B5" t="inlineStr"><is><t>Cable Auxiliar 3.5mm Reforzado 1 Metro</t></is></c>'
            . '</row>'
            . '</sheetData>'
            . '</worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

        $zip->close();

        $content = @file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $content;
    }

    /**
     * Genera el archivo CSV de la plantilla demo.
     *
     * @return string Contenido CSV con BOM UTF-8
     */
    public static function generateDemoCsv()
    {
        $bom = "\xEF\xBB\xBF";
        $csv = $bom . "\"SKU / Referencia\",\"Nombre del Producto\"\n"
            . "\"CH080-860\",\"Cable Para Audio Plug 6.3 Mono A Plug 3.5 Stereo 3:60 Mt\"\n"
            . "\"CH080-861\",\"Cable Para Audio Plug 3.5 Stereo A 2 RCA 1.8 Mt\"\n"
            . "\"CH080-862\",\"Adaptador Plug 6.3 Macho A Jack 3.5 Hembra\"\n"
            . "\"CH080-863\",\"Cable Auxiliar 3.5mm Reforzado 1 Metro\"\n";

        return $csv;
    }
}
