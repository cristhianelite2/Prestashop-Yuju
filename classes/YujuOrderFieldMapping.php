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
        $rawCustomerFirst = $data['customer']['first_name'] ?? '';
        $rawCustomerLast = $data['customer']['last_name'] ?? '';
        $data['customer']['first_name'] = self::validName($rawCustomerFirst, 'Cliente');
        $data['customer']['last_name'] = self::validName($rawCustomerLast, 'Yuju');
        $data['customer']['email'] = self::validEmail($data['customer']['email'] ?? '');
        $addressSections = ['shipping_address'];
        if (isset($data['billing_address']) && is_array($data['billing_address']) && !empty($data['billing_address'])) {
            $addressSections[] = 'billing_address';
        }
        foreach ($addressSections as $section) {
            $data[$section]['first_name'] = self::validName($data[$section]['first_name'] ?? ($data['customer']['first_name'] ?? ''), 'Cliente');
            $data[$section]['last_name'] = self::validName($data[$section]['last_name'] ?? ($data['customer']['last_name'] ?? ''), 'Yuju');
            $data[$section]['company'] = self::validText($data[$section]['company'] ?? '', 'not found');
            $data[$section]['address_line_1'] = self::validText($data[$section]['address_line_1'] ?? '', 'Dirección no especificada');
            $data[$section]['address_line_2'] = self::validText($data[$section]['address_line_2'] ?? '', '');
            $data[$section]['city'] = self::validText($data[$section]['city'] ?? '', 'Ciudad no especificada');
            $data[$section]['postal_code'] = self::validText($data[$section]['postal_code'] ?? '', '00000');
            $data[$section]['phone'] = self::validText($data[$section]['phone'] ?? '', '0000000000');
            $data[$section]['country_code'] = self::validText($data[$section]['country_code'] ?? '', 'MX');
            $data[$section]['state_code'] = self::validText($data[$section]['state_code'] ?? '', 'MEX');
            $data[$section]['dni'] = self::validText($data[$section]['dni'] ?? '', '00000000');
        }
        // Cuando el marketplace pone un ID numérico en first_name, preferir el nombre de envío.
        if (self::isInvalidName($rawCustomerFirst)) {
            $data['customer']['first_name'] = self::validName($data['shipping_address']['first_name'] ?? '', 'Cliente');
        }
        if (self::isInvalidName($rawCustomerLast)) {
            $data['customer']['last_name'] = self::validName($data['shipping_address']['last_name'] ?? '', 'Yuju');
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

    private static function isInvalidName($value)
    {
        if (is_array($value) || is_object($value) || is_bool($value)) {
            return true;
        }
        $value = trim((string) $value);
        return $value === '' || strtolower($value) === 'not found' || (bool) preg_match('/^\\d+$/', $value);
    }

    private static function validName($value, $fallback)
    {
        if (self::isInvalidName($value)) {
            return $fallback;
        }
        return self::validText($value, $fallback);
    }

    private static function validText($value, $fallback)
    {
        if (is_array($value) || is_object($value) || is_bool($value)) {
            $value = '';
        }
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'not found') {
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
