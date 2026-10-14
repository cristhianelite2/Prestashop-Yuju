<?php
/**
 * Reglas configurables para normalizar y mapear los campos de una orden Yuju.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class YujuOrderFieldMapping
{
    public static function getMappings()
    {
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT id, target_path, template, field_type, default_value, is_active
                 FROM ' . _DB_PREFIX_ . 'yuju_order_field_mapping
                 WHERE is_active = 1 ORDER BY id ASC'
            );
            return is_array($rows) ? $rows : [];
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Aplica reglas al array adaptado. Los comodines se resuelven contra el
     * payload original y también contra el payload ya adaptado.
     */
    public static function apply(array $adapted, array $source)
    {
        foreach (self::getMappings() as $mapping) {
            $target = trim((string) ($mapping['target_path'] ?? ''));
            if (!self::isValidPath($target)) {
                continue;
            }

            $template = (string) ($mapping['template'] ?? '');
            $value = preg_replace_callback('/%([a-zA-Z0-9_.\[\]-]+)%/', function ($matches) use ($source, $adapted) {
                $path = $matches[1];
                $found = false;
                $value = self::pathValue($source, $path, $found);
                if (!$found) {
                    $value = self::pathValue($adapted, $path, $found);
                }
                if (!$found || is_array($value) || is_object($value) || $value === null) {
                    return '';
                }
                return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }, $template);

            $type = strtolower(trim((string) ($mapping['field_type'] ?? 'string')));
            $fallback = (string) ($mapping['default_value'] ?? '');
            if ($fallback === '') {
                $fallback = in_array($type, ['integer', 'decimal', 'boolean'], true) ? '0' : 'not found';
            }
            $value = self::normalizeValue($value, $type, $fallback);
            self::setPath($adapted, $target, $value);
        }

        return self::normalizeKnownData($adapted);
    }

    public static function normalizeKnownData(array $data)
    {
        // Asegura contenedores previsibles sin descartar campos adicionales del marketplace.
        foreach (['customer', 'shipping_address', 'billing_address'] as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                $data[$section] = [];
            }
        }
        $data['customer']['first_name'] = self::validText($data['customer']['first_name'] ?? '', 'Cliente');
        $data['customer']['last_name'] = self::validText($data['customer']['last_name'] ?? '', 'Yuju');
        $data['customer']['email'] = self::validEmail($data['customer']['email'] ?? '');
        foreach (['shipping_address', 'billing_address'] as $section) {
            $data[$section]['first_name'] = self::validText($data[$section]['first_name'] ?? ($data['customer']['first_name'] ?? ''), 'Cliente');
            $data[$section]['last_name'] = self::validText($data[$section]['last_name'] ?? ($data['customer']['last_name'] ?? ''), 'Yuju');
            $data[$section]['address_line_1'] = self::validText($data[$section]['address_line_1'] ?? '', 'Dirección no especificada');
            $data[$section]['city'] = self::validText($data[$section]['city'] ?? '', 'Ciudad no especificada');
            $data[$section]['postal_code'] = self::validText($data[$section]['postal_code'] ?? '', '00000');
            $data[$section]['phone'] = self::validText($data[$section]['phone'] ?? '', '0000000000');
            $data[$section]['country_code'] = self::validText($data[$section]['country_code'] ?? '', 'MX');
            $data[$section]['state_code'] = self::validText($data[$section]['state_code'] ?? '', 'MEX');
        }
        if (!isset($data['items']) || !is_array($data['items'])) {
            $data['items'] = [];
        }
        $data['currency'] = self::validText($data['currency'] ?? '', 'MXN');
        if (isset($data['total']) && !is_numeric($data['total'])) {
            $data['total'] = 0;
        }
        return $data;
    }

    private static function validText($value, $fallback)
    {
        if (is_array($value) || is_object($value) || is_bool($value)) {
            $value = '';
        }
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'not found' || preg_match('/^\d+$/', $value) && $fallback === 'Cliente') {
            return $fallback;
        }
        return $value;
    }

    private static function validEmail($value)
    {
        $value = trim((string) $value);
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return strtolower($value);
        }
        // Correo sintético estable para marketplaces que no proporcionan uno válido.
        $seed = (string) ($value !== '' ? $value : uniqid('yuju_', true));
        return 'yuju-' . substr(hash('sha256', strtolower($seed)), 0, 24) . '@not-found.invalid';
    }

    private static function normalizeValue($value, $type, $fallback)
    {
        if (is_array($value) || is_object($value) || $value === null) {
            $value = '';
        }
        $value = trim((string) $value);
        if (in_array($type, ['integer', 'int'], true)) {
            return $value !== '' && is_numeric($value) ? (int) $value : (int) $fallback;
        }
        if (in_array($type, ['decimal', 'float', 'number'], true)) {
            return $value !== '' && is_numeric($value) && is_finite((float) $value) ? (float) $value : (float) $fallback;
        }
        if (in_array($type, ['boolean', 'bool'], true)) {
            if (in_array(strtolower($value), ['1', 'true', 'yes', 'sí', 'si', 'on'], true)) return 1;
            if (in_array(strtolower($value), ['0', 'false', 'no', 'off'], true)) return 0;
            return (int) $fallback;
        }
        return $value !== '' ? $value : $fallback;
    }

    private static function isValidPath($path)
    {
        return $path !== '' && strlen($path) <= 190
            && preg_match('/^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*$/', $path);
    }

    private static function pathValue(array $data, $path, &$found)
    {
        $current = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                $found = false;
                return null;
            }
            $current = $current[$segment];
        }
        $found = true;
        return $current;
    }

    private static function setPath(array &$data, $path, $value)
    {
        $segments = explode('.', $path);
        $current =& $data;
        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $current[$segment] = $value;
                return;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current =& $current[$segment];
        }
    }
}
