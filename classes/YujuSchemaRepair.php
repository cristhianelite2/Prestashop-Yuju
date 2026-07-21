<?php
/**
 * Revisa y repara el esquema de tablas del módulo Yuju
 * (crear tablas faltantes y agregar columnas ausentes según sql/install.sql).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class YujuSchemaRepair
{
    /** @var string */
    protected $sqlContent = '';

    /** @var array<table, string> CREATE statements by short table name */
    protected $createStatements = [];

    /** @var array<table, array{name:string, definition:string}[]> */
    protected $expectedColumns = [];

    public function __construct()
    {
        $this->loadSql();
    }

    /**
     * Catálogo de tablas del módulo (nombres cortos sin prefijo).
     *
     * @return string[]
     */
    public function getExpectedTableNames()
    {
        return array_keys($this->createStatements);
    }

    /**
     * Revisa y repara una tabla concreta.
     *
     * @param string $shortName ej. yuju_product_status
     * @return array
     */
    public function repairTable($shortName)
    {
        $shortName = preg_replace('/[^a-z0-9_]/i', '', (string) $shortName);
        $fullName = _DB_PREFIX_ . $shortName;

        $report = [
            'success' => true,
            'table' => $shortName,
            'full_name' => $fullName,
            'status' => 'ok', // ok|created|repaired|error
            'message' => '',
            'actions' => [],
            'missing_before' => [],
            'exists_before' => false,
            'exists_after' => false,
        ];

        if (!isset($this->createStatements[$shortName])) {
            $report['success'] = false;
            $report['status'] = 'error';
            $report['message'] = 'No hay definición CREATE TABLE para esta tabla en install.sql.';
            return $report;
        }

        $exists = $this->tableExists($fullName);
        $report['exists_before'] = $exists;

        if (!$exists) {
            try {
                $ok = (bool) Db::getInstance()->execute($this->createStatements[$shortName]);
                $err = $ok ? '' : Db::getInstance()->getMsgError();
            } catch (Exception $e) {
                $ok = false;
                $err = $e->getMessage();
            }

            $existsNow = $this->tableExists($fullName);
            $report['exists_after'] = $existsNow;

            if ($existsNow) {
                $report['status'] = 'created';
                $report['message'] = 'Tabla creada correctamente.';
                $report['actions'][] = [
                    'type' => 'create_table',
                    'detail' => 'CREATE TABLE `' . $fullName . '`',
                ];
                // Tras crear, no hace falta agregar columnas
                return $report;
            }

            $report['success'] = false;
            $report['status'] = 'error';
            $report['message'] = 'No se pudo crear la tabla' . ($err ? ': ' . $err : '.');
            return $report;
        }

        $report['exists_after'] = true;
        $expected = isset($this->expectedColumns[$shortName]) ? $this->expectedColumns[$shortName] : [];
        $actual = $this->getActualColumns($fullName);
        $actualLower = array_change_key_case($actual, CASE_LOWER);

        $missing = [];
        foreach ($expected as $col) {
            $colName = $col['name'];
            if (!isset($actualLower[strtolower($colName)])) {
                $missing[] = $col;
            }
        }

        $report['missing_before'] = array_map(function ($c) {
            return $c['name'];
        }, $missing);

        if (empty($missing)) {
            $patchActions = $this->applyKnownPatches($shortName);
            foreach ($patchActions as $pa) {
                $report['actions'][] = $pa;
            }
            $report['status'] = empty($patchActions) ? 'ok' : 'repaired';
            $report['message'] = empty($patchActions)
                ? 'Tabla OK: columnas coinciden con el esquema esperado.'
                : 'Tabla OK en columnas; parches de esquema aplicados.';
            if (empty($patchActions)) {
                $report['actions'][] = [
                    'type' => 'check',
                    'detail' => 'Sin diferencias. Columnas presentes: ' . count($actual),
                ];
            }
            return $report;
        }

        $added = [];
        $failed = [];
        foreach ($missing as $col) {
            $sql = 'ALTER TABLE `' . bqSQL($fullName) . '` ADD COLUMN ' . $col['definition'];
            try {
                $ok = (bool) Db::getInstance()->execute($sql);
                $err = $ok ? '' : Db::getInstance()->getMsgError();
            } catch (Exception $e) {
                $ok = false;
                $err = $e->getMessage();
            }

            if ($ok) {
                $added[] = $col['name'];
                $report['actions'][] = [
                    'type' => 'add_column',
                    'detail' => 'Columna agregada: `' . $col['name'] . '`',
                    'definition' => $col['definition'],
                ];
            } else {
                $failed[] = $col['name'] . ($err ? ' (' . $err . ')' : '');
                $report['actions'][] = [
                    'type' => 'add_column_error',
                    'detail' => 'No se pudo agregar `' . $col['name'] . '`' . ($err ? ': ' . $err : ''),
                ];
            }
        }

        // Aplicar parches ENUM conocidos en tablas específicas
        $patchActions = $this->applyKnownPatches($shortName);
        foreach ($patchActions as $pa) {
            $report['actions'][] = $pa;
        }

        if (!empty($failed) && empty($added)) {
            $report['success'] = false;
            $report['status'] = 'error';
            $report['message'] = 'Falló la reparación de columnas: ' . implode(', ', $failed);
        } elseif (!empty($failed)) {
            $report['success'] = false;
            $report['status'] = 'repaired';
            $report['message'] = 'Reparación parcial. Agregadas: ' . implode(', ', $added) . '. Fallaron: ' . implode(', ', $failed);
        } else {
            $report['status'] = 'repaired';
            $report['message'] = 'Columnas agregadas: ' . implode(', ', $added);
        }

        return $report;
    }

    /**
     * @param string $fullName
     * @return bool
     */
    protected function tableExists($fullName)
    {
        try {
            $check = Db::getInstance()->executeS('SHOW TABLES LIKE "' . pSQL($fullName) . '"');
            return !empty($check);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param string $fullName
     * @return array<string, array>
     */
    protected function getActualColumns($fullName)
    {
        $cols = [];
        try {
            $rows = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . bqSQL($fullName) . '`');
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $field = $row['Field'];
                    $cols[$field] = $row;
                }
            }
        } catch (Exception $e) {
            // ignore
        }
        return $cols;
    }

    protected function loadSql()
    {
        $path = _PS_MODULE_DIR_ . 'prestashopyuju/sql/install.sql';
        if (!is_readable($path)) {
            return;
        }

        $sql = (string) file_get_contents($path);
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        $this->sqlContent = $sql;

        if (!preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`\s*\((.*?)\)\s*ENGINE\s*=/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            return;
        }

        $prefix = _DB_PREFIX_;
        foreach ($matches as $m) {
            $full = $m[1];
            if (strpos($full, $prefix) !== 0) {
                continue;
            }
            $short = substr($full, strlen($prefix));
            if ($short === '' || strpos($short, 'yuju_') !== 0) {
                continue;
            }

            // Statement completo hasta el ; del install
            $stmt = $this->extractCreateStatement($sql, $full);
            if ($stmt) {
                $this->createStatements[$short] = $stmt;
            }

            $this->expectedColumns[$short] = $this->parseColumnDefinitions($m[2]);
        }
    }

    /**
     * @param string $sql
     * @param string $fullTableName
     * @return string|null
     */
    protected function extractCreateStatement($sql, $fullTableName)
    {
        $needles = [
            'CREATE TABLE IF NOT EXISTS `' . $fullTableName . '`',
            'CREATE TABLE `' . $fullTableName . '`',
        ];
        $pos = false;
        foreach ($needles as $needle) {
            $pos = stripos($sql, $needle);
            if ($pos !== false) {
                break;
            }
        }
        if ($pos === false) {
            return null;
        }
        $end = strpos($sql, ';', $pos);
        if ($end === false) {
            return null;
        }
        return trim(substr($sql, $pos, $end - $pos));
    }

    /**
     * Extrae definiciones de columnas (ignora KEY/PRIMARY/FOREIGN/INDEX/UNIQUE).
     *
     * @param string $inner
     * @return array{name:string,definition:string}[]
     */
    protected function parseColumnDefinitions($inner)
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $len = strlen($inner);
        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth = max(0, $depth - 1);
            }

            if ($ch === ',' && $depth === 0) {
                $trim = trim($current);
                if ($trim !== '') {
                    $parts[] = $trim;
                }
                $current = '';
                continue;
            }
            $current .= $ch;
        }
        $trim = trim($current);
        if ($trim !== '') {
            $parts[] = $trim;
        }

        $columns = [];
        foreach ($parts as $def) {
            if (!preg_match('/^`([^`]+)`\s+/', $def, $cm)) {
                continue;
            }
            if (preg_match('/^(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|FOREIGN\s+KEY|CONSTRAINT)\b/i', $def)) {
                continue;
            }
            $columns[] = [
                'name' => $cm[1],
                'definition' => $def,
            ];
        }

        return $columns;
    }

    /**
     * Parches conocidos de ENUM / upgrades (no solo columnas nuevas).
     *
     * @param string $shortName
     * @return array
     */
    protected function applyKnownPatches($shortName)
    {
        $actions = [];

        if ($shortName === 'yuju_sync_queue') {
            try {
                Db::getInstance()->execute(
                    'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_sync_queue` MODIFY COLUMN `action` ENUM(\'create\',\'update\',\'delete\') NOT NULL'
                );
                $actions[] = [
                    'type' => 'patch',
                    'detail' => 'ENUM action actualizado (incluye delete)',
                ];
            } catch (Exception $e) {
                $actions[] = [
                    'type' => 'patch_skip',
                    'detail' => 'Patch action ENUM: ' . $e->getMessage(),
                ];
            }
        }

        if ($shortName === 'yuju_product_status') {
            try {
                Db::getInstance()->execute(
                    'ALTER TABLE `' . _DB_PREFIX_ . 'yuju_product_status` MODIFY COLUMN `sync_status` ENUM(
                        \'pending\',\'syncing\',\'synced\',\'synced_with_warnings\',\'synced_with_errors\',\'error\',\'disabled\',\'queued\',
                        \'creating_in_yuju\',\'updating_in_yuju\',\'deleting_in_yuju\'
                    ) DEFAULT \'pending\''
                );
                $actions[] = [
                    'type' => 'patch',
                    'detail' => 'ENUM sync_status actualizado (estados webhook intermedios)',
                ];
            } catch (Exception $e) {
                $actions[] = [
                    'type' => 'patch_skip',
                    'detail' => 'Patch sync_status ENUM: ' . $e->getMessage(),
                ];
            }
        }

        if ($shortName === 'yuju_product_mapping') {
            try {
                $n = Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'yuju_product_mapping`
                     SET `is_active` = 0, `updated_at` = NOW()
                     WHERE `is_active` = 1
                       AND `yuju_field` IN (
                           \'cost_price\',\'active\',\'stock_quantity\',\'short_description\',
                           \'width\',\'height\',\'depth\'
                       )'
                );
                $actions[] = [
                    'type' => 'patch',
                    'detail' => 'Mapeos inválidos para API Yuju desactivados (cost_price/active/stock_quantity/…)',
                ];
            } catch (Exception $e) {
                $actions[] = [
                    'type' => 'patch_skip',
                    'detail' => 'Patch product_mapping: ' . $e->getMessage(),
                ];
            }
        }

        if ($shortName === 'yuju_category_mapping') {
            require_once _PS_MODULE_DIR_ . 'prestashopyuju/classes/YujuCategoryMapping.php';
            $ok = YujuCategoryMapping::ensureSharedYujuCategoryAllowed();
            $actions[] = [
                'type' => $ok ? 'patch' : 'patch_skip',
                'detail' => $ok
                    ? 'Índice UNIQUE de yuju_category_id eliminado (varias PS → misma Yuju permitido)'
                    : 'No se pudo relajar unicidad de yuju_category_id',
            ];
        }

        return $actions;
    }
}
