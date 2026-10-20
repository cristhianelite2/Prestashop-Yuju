<?php
/**
 * Reglas configurables para normalizar y mapear los campos de una orden Yuju.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class YujuOrderFieldMapping
{
    private static $cachedMappings = null;

    public static function clearCache()
    {
        self::$cachedMappings = null;
    }

    public static function getMappings()
    {
        if (self::$cachedMappings !== null) {
            return self::$cachedMappings;
        }
        try {
            $rows = Db::getInstance()->executeS(
                'SELECT id, target_path, template, field_type, default_value, is_active
                 FROM ' . _DB_PREFIX_ . 'yuju_order_field_mapping
                 WHERE is_active = 1 ORDER BY id ASC'
            );
            self::$cachedMappings = is_array($rows) ? $rows : [];
            return self::$cachedMappings;
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getDefaultValue($targetPath, $fallback = '')
    {
        $canonical = self::canonicalTarget($targetPath);
        $mappings = self::getMappings();
        foreach ($mappings as $mapping) {
            $mappedTarget = self::canonicalTarget(trim((string) ($mapping['target_path'] ?? '')));
            if ($mappedTarget === $canonical) {
                $val = trim((string) ($mapping['default_value'] ?? ''));
                if ($val !== '') {
                    return $val;
                }
            }
        }
        return $fallback;
    }

    /**
     * Aplica reglas al array adaptado. Los comodines se resuelven contra el
     * payload original y también contra el payload ya adaptado.
     */
    public static function apply(array $adapted, array $source)
    {
        foreach (self::getMappings() as $mapping) {
            $target = self::canonicalTarget(trim((string) ($mapping['target_path'] ?? '')));
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
                    return 'not found';
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
        foreach (['customer', 'shipping_address'] as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                $data[$section] = [];
            }
        }

        $defaultCustomerFirst = self::getDefaultValue('customer.first_name', 'Cliente');
        $defaultCustomerLast = self::getDefaultValue('customer.last_name', 'Yuju');
        $defaultShippingFirst = self::getDefaultValue('shipping_address.first_name', 'Cliente');
        $defaultShippingLast = self::getDefaultValue('shipping_address.last_name', 'Yuju');

        $rawCustomerFirst = $data['customer']['first_name'] ?? '';
        $rawCustomerLast = $data['customer']['last_name'] ?? '';
        $rawShippingFirst = $data['shipping_address']['first_name'] ?? '';
        $rawShippingLast = $data['shipping_address']['last_name'] ?? '';

        // Si el cliente no tiene nombre usable pero el shipping sí, preferir shipping
        if (!self::isUsableName($rawCustomerFirst) && self::isUsableName($rawShippingFirst)) {
            $rawCustomerFirst = $rawShippingFirst;
        }
        if (!self::isUsableName($rawCustomerLast) && self::isUsableName($rawShippingLast)) {
            $rawCustomerLast = $rawShippingLast;
        }

        // Si el shipping no tiene nombre usable pero el cliente sí, preferir cliente
        if (!self::isUsableName($rawShippingFirst) && self::isUsableName($rawCustomerFirst)) {
            $rawShippingFirst = $rawCustomerFirst;
        }
        if (!self::isUsableName($rawShippingLast) && self::isUsableName($rawCustomerLast)) {
            $rawShippingLast = $rawCustomerLast;
        }

        $data['customer']['first_name'] = $rawCustomerFirst;
        $data['customer']['last_name'] = $rawCustomerLast;
        self::sanitizeAndComplementNames($data['customer']['first_name'], $data['customer']['last_name'], $defaultCustomerFirst, $defaultCustomerLast);

        $data['customer']['email'] = self::validEmail($data['customer']['email'] ?? '');
        if (array_key_exists('phone', $data['customer'])) {
            $data['customer']['phone'] = self::validPhone($data['customer']['phone']);
        }
        if (array_key_exists('doc_number', $data['customer'])) {
            $data['customer']['doc_number'] = self::validIdentifier($data['customer']['doc_number'], 16, '00000000');
        }

        $data['shipping_address']['first_name'] = $rawShippingFirst;
        $data['shipping_address']['last_name'] = $rawShippingLast;
        self::sanitizeAndComplementNames($data['shipping_address']['first_name'], $data['shipping_address']['last_name'], $defaultShippingFirst, $defaultShippingLast);

        $addressSections = ['shipping_address'];
        if (isset($data['billing_address']) && is_array($data['billing_address']) && !empty($data['billing_address'])) {
            $addressSections[] = 'billing_address';
            $defaultBillingFirst = self::getDefaultValue('billing_address.first_name', $data['shipping_address']['first_name']);
            $defaultBillingLast = self::getDefaultValue('billing_address.last_name', $data['shipping_address']['last_name']);
            $rawBillingFirst = $data['billing_address']['first_name'] ?? $data['shipping_address']['first_name'];
            $rawBillingLast = $data['billing_address']['last_name'] ?? $data['shipping_address']['last_name'];
            $data['billing_address']['first_name'] = $rawBillingFirst;
            $data['billing_address']['last_name'] = $rawBillingLast;
            self::sanitizeAndComplementNames($data['billing_address']['first_name'], $data['billing_address']['last_name'], $defaultBillingFirst, $defaultBillingLast);
        }

        foreach ($addressSections as $section) {
            // Sanitizar siempre los valores, incluso cuando el origen sí contiene datos.
            // Límites alineados con las propiedades Address de PrestaShop.
            $data[$section]['company'] = self::sanitizeFieldText($data[$section]['company'] ?? '', 64, '');
            $data[$section]['address_line_1'] = self::sanitizeFieldText($data[$section]['address_line_1'] ?? '', 128, 'Dirección no especificada');
            $data[$section]['address_line_2'] = self::sanitizeFieldText($data[$section]['address_line_2'] ?? '', 128, '');
            $data[$section]['city'] = self::sanitizeFieldText($data[$section]['city'] ?? '', 64, 'Ciudad no especificada');
            $data[$section]['postal_code'] = self::sanitizePostalCode($data[$section]['postal_code'] ?? '');
            $data[$section]['phone'] = self::validPhone($data[$section]['phone'] ?? '');
            $data[$section]['country_code'] = self::sanitizeCode($data[$section]['country_code'] ?? '', 2, 'MX');
            $data[$section]['state_code'] = self::sanitizeCode($data[$section]['state_code'] ?? '', 32, 'MEX');
            $data[$section]['dni'] = self::validIdentifier($data[$section]['dni'] ?? '', 16, '00000000');
        }
        if (!isset($data['items']) || !is_array($data['items'])) {
            $data['items'] = [];
        }
        foreach ($data['items'] as $index => $item) {
            if (!is_array($item)) {
                $data['items'][$index] = [];
                $item = [];
            }
            foreach (['quantity', 'qty', 'product_quantity'] as $quantityKey) {
                if (array_key_exists($quantityKey, $item)) {
                    $raw = $item[$quantityKey];
                    // PrestaShop no puede crear una línea de pedido con cantidad 0.
                    $data['items'][$index][$quantityKey] = is_numeric($raw) && is_finite((float) $raw) && (int) $raw > 0
                        ? (int) $raw : 1;
                }
            }
            foreach (['price', 'unit_price', 'unit_price_tax_excl', 'total', 'total_price', 'total_price_tax_excl', 'total_price_tax_incl', 'amount'] as $priceKey) {
                if (array_key_exists($priceKey, $item)) {
                    $raw = $item[$priceKey];
                    $data['items'][$index][$priceKey] = is_numeric($raw) && is_finite((float) $raw)
                        ? (float) $raw : 0.0;
                }
            }
            foreach (['name', 'product_name', 'sku', 'reference'] as $textKey) {
                if (array_key_exists($textKey, $item)) {
                    $data['items'][$index][$textKey] = self::validText($item[$textKey], 'not found');
                }
            }
            if (array_key_exists('product_id', $item)) {
                $rawId = $item['product_id'];
                $data['items'][$index]['product_id'] = (is_array($rawId) || is_object($rawId) || is_bool($rawId) || $rawId === null || trim((string) $rawId) === '')
                    ? null : trim((string) $rawId);
            }
            if (array_key_exists('product_attribute_id', $item)) {
                $rawAttribute = $item['product_attribute_id'];
                $data['items'][$index]['product_attribute_id'] = is_numeric($rawAttribute) && is_finite((float) $rawAttribute)
                    ? max(0, (int) $rawAttribute) : 0;
            }
        }
        $data['currency'] = strtoupper(self::validText($data['currency'] ?? '', 'MXN'));
        $data['id'] = self::validText($data['id'] ?? '', 'yuju_' . substr(hash('sha256', uniqid('', true)), 0, 16));
        $data['reference'] = self::validText($data['reference'] ?? '', (string) $data['id']);
        foreach (['total_amount', 'total_amount_tax_excl', 'products_total', 'shipping_cost', 'total'] as $numericKey) {
            if (array_key_exists($numericKey, $data)) {
                $raw = $data[$numericKey];
                $data[$numericKey] = is_numeric($raw) && is_finite((float) $raw) ? (float) $raw : 0.0;
            }
        }
        foreach (['status', 'payment_method', 'shipping_method'] as $textKey) {
            if (array_key_exists($textKey, $data)) {
                $data[$textKey] = self::validText($data[$textKey], 'not found');
            }
        }
        if (isset($data['created_at']) && strtotime((string) $data['created_at']) === false) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    private static function canonicalTarget($target)
    {
        // Los prefijos identifican el bloque funcional en la UI; se traducen a
        // las claves consumidas realmente por el pipeline de creación de pedidos.
        if (strpos($target, 'cart.items.') === 0) {
            return 'items.' . substr($target, strlen('cart.items.'));
        }
        if (strpos($target, 'order.customer.') === 0) {
            return substr($target, strlen('order.'));
        }
        if (strpos($target, 'order.shipping_address.') === 0 || strpos($target, 'order.billing_address.') === 0) {
            return substr($target, strlen('order.'));
        }
        if (strpos($target, 'cart.') === 0) {
            $candidate = substr($target, strlen('cart.'));
            if (in_array($candidate, ['currency', 'items', 'shipping_cost', 'products_total'], true)) {
                return $candidate;
            }
        }
        if (strpos($target, 'order.') === 0) {
            $candidate = substr($target, strlen('order.'));
            if (in_array($candidate, ['id', 'reference', 'status', 'currency', 'total', 'total_amount',
                'total_amount_tax_excl', 'products_total', 'shipping_cost', 'payment_method',
                'shipping_method', 'created_at'], true)) {
                $target = $candidate === 'total' ? 'total_amount' : $candidate;
            }
        }
        $aliases = [
            'cart.currency' => 'currency',
            'cart.items' => 'items',
            'cart.shipping_cost' => 'shipping_cost',
            'cart.products_total' => 'products_total',
            'order.id' => 'id',
            'order.reference' => 'reference',
            'order.status' => 'status',
            'order.currency' => 'currency',
            'order.total' => 'total_amount',
            'order.total_amount' => 'total_amount',
            'order.total_amount_tax_excl' => 'total_amount_tax_excl',
            'order.products_total' => 'products_total',
            'order.shipping_cost' => 'shipping_cost',
            'order.payment_method' => 'payment_method',
            'order.shipping_method' => 'shipping_method',
            'order.created_at' => 'created_at',
            'address.shipping.first_name' => 'shipping_address.first_name',
            'address.shipping.last_name' => 'shipping_address.last_name',
            'address.shipping.address_line_1' => 'shipping_address.address_line_1',
            'address.shipping.address_line_2' => 'shipping_address.address_line_2',
            'address.shipping.city' => 'shipping_address.city',
            'address.shipping.postal_code' => 'shipping_address.postal_code',
            'address.shipping.phone' => 'shipping_address.phone',
            'address.shipping.company' => 'shipping_address.company',
            'address.shipping.dni' => 'shipping_address.dni',
            'address.shipping.country_code' => 'shipping_address.country_code',
            'address.shipping.state_code' => 'shipping_address.state_code',
            'address.billing.first_name' => 'billing_address.first_name',
            'address.billing.last_name' => 'billing_address.last_name',
            'address.billing.address_line_1' => 'billing_address.address_line_1',
            'address.billing.address_line_2' => 'billing_address.address_line_2',
            'address.billing.city' => 'billing_address.city',
            'address.billing.postal_code' => 'billing_address.postal_code',
            'address.billing.phone' => 'billing_address.phone',
            'address.billing.company' => 'billing_address.company',
            'address.billing.dni' => 'billing_address.dni',
            'address.billing.country_code' => 'billing_address.country_code',
            'address.billing.state_code' => 'billing_address.state_code',
            'customer.phone' => 'shipping_address.phone',
            'customer.doc_number' => 'shipping_address.dni',
        ];
        return isset($aliases[$target]) ? $aliases[$target] : $target;
    }

    public static function isUsableName($value)
    {
        if (is_array($value) || is_object($value) || is_bool($value) || $value === null) {
            return false;
        }
        $value = trim((string) $value);
        if ($value === '' || (bool) preg_match('/^\d+$/', $value)) {
            return false;
        }
        if (!preg_match('/[\p{L}]/u', $value)) {
            return false;
        }
        $sanitized = preg_replace('/[*0-9!<>,;?=+()@#"°{}_$%:\/\\\\^]/u', '', $value);
        $sanitized = preg_replace('/[^\p{L}\s\-\']/u', '', $sanitized);
        $sanitized = trim(preg_replace('/\s+/u', ' ', (string) $sanitized));
        if ($sanitized === '') {
            return false;
        }
        if (class_exists('Validate') && method_exists('Validate', 'isName')) {
            return Validate::isName($sanitized);
        }
        return !preg_match('/[0-9!<>,;?=+()@#"°{}_$%:\/\\\\*^]/u', $sanitized);
    }

    public static function isInvalidName($value)
    {
        return !self::isUsableName($value);
    }

    public static function validName($value, $fallback)
    {
        return self::sanitizeName($value, $fallback);
    }

    public static function sanitizeName($value, $fallback = '')
    {
        if (is_array($value) || is_object($value) || is_bool($value) || $value === null) {
            $value = '';
        }
        $value = trim((string) $value);
        // Remover dígitos, asteriscos de enmascaramiento y caracteres prohibidos por PrestaShop
        $value = preg_replace('/[*0-9!<>,;?=+()@#"°{}_$%:\/\\\\^]/u', '', $value);
        $value = preg_replace('/[^\p{L}\s\-\']/u', '', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        $value = trim((string) $value, " \t\n\r\0\x0B-'.");

        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, 32, 'UTF-8');
        } else {
            $value = substr($value, 0, 32);
        }

        if ($value === '' || !preg_match('/[\p{L}]/u', $value)) {
            return $fallback;
        }

        if (class_exists('Validate') && method_exists('Validate', 'isName') && !Validate::isName($value)) {
            return $fallback;
        }

        return $value;
    }

    public static function sanitizeAndComplementNames(&$firstName, &$lastName, $fallbackFirst = 'Cliente', $fallbackLast = 'Yuju')
    {
        $cleanFirst = self::sanitizeName($firstName, '');
        $cleanLast = self::sanitizeName($lastName, '');

        // Complementación inteligente: si first_name contiene nombre compuesto y last_name quedó vacío
        if ($cleanLast === '' && $cleanFirst !== '') {
            $words = preg_split('/\s+/u', $cleanFirst, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($words) && count($words) >= 2) {
                $lastWord = array_pop($words);
                $firstPart = implode(' ', $words);
                $firstCandidate = self::sanitizeName($firstPart, '');
                $lastCandidate = self::sanitizeName($lastWord, '');
                if ($firstCandidate !== '' && $lastCandidate !== '') {
                    $cleanFirst = $firstCandidate;
                    $cleanLast = $lastCandidate;
                }
            }
        } elseif ($cleanFirst === '' && $cleanLast !== '') {
            $words = preg_split('/\s+/u', $cleanLast, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($words) && count($words) >= 2) {
                $firstWord = array_shift($words);
                $lastPart = implode(' ', $words);
                $firstCandidate = self::sanitizeName($firstWord, '');
                $lastCandidate = self::sanitizeName($lastPart, '');
                if ($firstCandidate !== '' && $lastCandidate !== '') {
                    $cleanFirst = $firstCandidate;
                    $cleanLast = $lastCandidate;
                }
            }
        }

        if ($cleanFirst === '') {
            $cleanFirst = self::sanitizeName($fallbackFirst, 'Cliente');
            if ($cleanFirst === '') {
                $cleanFirst = 'Cliente';
            }
        }
        if ($cleanLast === '') {
            $cleanLast = self::sanitizeName($fallbackLast, 'Yuju');
            if ($cleanLast === '') {
                $cleanLast = 'Yuju';
            }
        }

        if (function_exists('mb_substr')) {
            $cleanFirst = mb_substr($cleanFirst, 0, 32, 'UTF-8');
            $cleanLast = mb_substr($cleanLast, 0, 32, 'UTF-8');
        } else {
            $cleanFirst = substr($cleanFirst, 0, 32);
            $cleanLast = substr($cleanLast, 0, 32);
        }

        if (class_exists('Validate') && method_exists('Validate', 'isName')) {
            if (!Validate::isName($cleanFirst)) {
                $cleanFirst = 'Cliente';
            }
            if (!Validate::isName($cleanLast)) {
                $cleanLast = 'Yuju';
            }
        }

        $firstName = $cleanFirst;
        $lastName = $cleanLast;
    }

    private static function validText($value, $fallback)
    {
        if (is_array($value) || is_object($value) || is_bool($value)) {
            $value = '';
        }
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }
        return $value;
    }

    /**
     * Devuelve un teléfono compatible con PrestaShop; evita enviar placeholders
     * como "not found" o cadenas con letras a Address->phone.
     */
    private static function isPlaceholder($value)
    {
        return in_array(strtolower(trim((string) $value)), [
            '', 'not found', 'not found.', 'null', 'undefined', 'n/a', 'na', 'none'
        ], true);
    }

    private static function sanitizeFieldText($value, $maxLength, $fallback)
    {
        if (is_array($value) || is_object($value) || is_bool($value) || $value === null) {
            $value = '';
        }
        $value = trim((string) $value);
        // Quitar controles invisibles, conservando acentos y puntuación válida en direcciones.
        $value = preg_replace('/[\\x00-\\x1F\\x7F]/u', ' ', $value);
        $value = preg_replace('/\\s+/u', ' ', (string) $value);
        $value = trim((string) $value);
        if (self::isPlaceholder($value)) {
            return $fallback;
        }
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength, 'UTF-8')
            : substr($value, 0, $maxLength);
    }

    private static function sanitizePostalCode($value)
    {
        $value = self::sanitizeFieldText($value, 12, '00000');
        $value = preg_replace('/[^a-zA-Z0-9\\- ]/', '', $value);
        $value = trim(substr($value, 0, 12));
        return $value !== '' ? $value : '00000';
    }

    private static function sanitizeCode($value, $maxLength, $fallback)
    {
        $value = self::sanitizeFieldText($value, $maxLength, $fallback);
        $value = strtoupper(preg_replace('/[^a-zA-Z0-9_-]/', '', $value));
        return $value !== '' ? substr($value, 0, $maxLength) : $fallback;
    }

    private static function validIdentifier($value, $maxLength, $fallback)
    {
        $value = self::sanitizeFieldText($value, $maxLength, $fallback);
        // Identificadores fiscales/documentales pueden ser alfanuméricos.
        $value = strtoupper(preg_replace('/[^a-zA-Z0-9-]/', '', $value));
        return $value !== '' ? substr($value, 0, $maxLength) : $fallback;
    }

    private static function validPhone($value)
    {
        if (is_array($value) || is_object($value) || is_bool($value) || $value === null) {
            return '0000000000';
        }

        $value = trim((string) $value);
        if (self::isPlaceholder($value)) {
            return '0000000000';
        }

        // Normalizar teléfonos con prefijos, espacios, guiones o paréntesis a dígitos.
        $digits = preg_replace('/[^0-9]/', '', $value);
        if (!is_string($digits) || strlen($digits) < 3) {
            return '0000000000';
        }
        $digits = substr($digits, 0, 32);

        // Si PrestaShop está disponible, comprobar su validador antes de devolverlo.
        if (class_exists('Validate') && method_exists('Validate', 'isPhoneNumber')
            && !Validate::isPhoneNumber($digits)) {
            return '0000000000';
        }

        return $digits;
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
            return $value !== '' && is_numeric($value) && is_finite((float) $value) ? (int) $value : (int) $fallback;
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
