<?php
/**
 * Pure matching and comparison helpers for Yuju report rows.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class YujuProductReportReconciler
{
    public static function indexReportRows($products)
    {
        $index = [
            'by_id' => [],
            'duplicate_ids' => [],
            'by_sku' => [],
            'product_count' => 0,
        ];

        foreach ($products as $product) {
            if (!is_array($product) || empty($product['id_product'])) {
                throw new InvalidArgumentException('A Yuju report product is missing id_product.');
            }

            $product_id = (string) $product['id_product'];
            ++$index['product_count'];
            if (isset($index['by_id'][$product_id])) {
                $index['duplicate_ids'][$product_id] = true;
            }
            $index['by_id'][$product_id] = true;

            foreach (['sku_simple', 'sku'] as $field) {
                if (isset($product[$field]) && is_scalar($product[$field])) {
                    self::addSkuMatch($index, $product[$field], $product_id);
                }
            }

            if (!empty($product['variations']) && is_array($product['variations'])) {
                foreach ($product['variations'] as $variation) {
                    if (is_array($variation) && isset($variation['sku']) && is_scalar($variation['sku'])) {
                        self::addSkuMatch($index, $variation['sku'], $product_id);
                    }
                }
            }
        }

        return $index;
    }

    public static function matchProduct($index, $yuju_product_id, $prestashop_skus)
    {
        $yuju_product_id = is_scalar($yuju_product_id) ? trim((string) $yuju_product_id) : '';

        if ($yuju_product_id !== '') {
            if (isset($index['duplicate_ids'][$yuju_product_id])) {
                return ['status' => 'ambiguous', 'reason' => 'duplicate_yuju_id'];
            }

            if (isset($index['by_id'][$yuju_product_id])) {
                return ['status' => 'matched', 'yuju_product_id' => $yuju_product_id, 'matched_by' => 'yuju_id'];
            }

            return ['status' => 'id_not_found', 'yuju_product_id' => $yuju_product_id];
        }

        $matches = [];
        foreach ($prestashop_skus as $sku) {
            $normalized_sku = self::normalizeSku($sku);
            if ($normalized_sku === '' || empty($index['by_sku'][$normalized_sku])) {
                continue;
            }

            foreach ($index['by_sku'][$normalized_sku] as $product_id => $unused) {
                if (isset($index['duplicate_ids'][(string) $product_id])) {
                    return ['status' => 'ambiguous', 'reason' => 'duplicate_yuju_id_in_report'];
                }
                $matches[$product_id] = true;
            }
        }

        if (count($matches) === 1) {
            $product_ids = array_keys($matches);

            return ['status' => 'matched', 'yuju_product_id' => (string) $product_ids[0], 'matched_by' => 'sku'];
        }

        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'reason' => 'sku_matches_multiple_yuju_products'];
        }

        return ['status' => 'not_found'];
    }

    public static function compareMappedFields($prestashop_data, $yuju_data, $mappings)
    {
        $comparison = [
            'differences' => [],
            'uncomparable' => [],
        ];

        foreach ($mappings as $mapping) {
            if (empty($mapping['is_active'])
                || !in_array($mapping['sync_direction'], ['ps_to_yuju', 'prestashop_to_yuju', 'bidirectional'], true)
            ) {
                continue;
            }

            $field = isset($mapping['yuju_field']) ? $mapping['yuju_field'] : '';
            if ($field === '' || !array_key_exists($field, $prestashop_data)) {
                if ($field !== '') {
                    $comparison['uncomparable'][] = $field;
                }
                continue;
            }

            // A report may legitimately omit fields that its endpoint does not expose.
            // Do not block an update of comparable urgent fields (for example name) just
            // because price or stock was not included in this particular report payload.
            if (!array_key_exists($field, $yuju_data)) {
                continue;
            }

            $field_type = isset($mapping['field_type']) ? $mapping['field_type'] : 'string';
            if (!self::valuesEqual($prestashop_data[$field], $yuju_data[$field], $field_type)) {
                $comparison['differences'][] = [
                    'field' => $field,
                    'prestashop' => $prestashop_data[$field],
                    'yuju' => $yuju_data[$field],
                ];
            }
        }

        $comparison['uncomparable'] = array_values(array_unique($comparison['uncomparable']));

        return $comparison;
    }

    public static function compareVariations($prestashop_variations, $yuju_variations)
    {
        $comparison = [
            'matched' => [],
            'differences' => [],
            'uncomparable' => [],
            'ambiguous' => [],
            'cannot_update' => [],
            'to_create' => [],
        ];
        $prestashop_by_sku = [];
        $yuju_by_sku = [];

        foreach ($prestashop_variations as $variation) {
            if (!is_array($variation) || self::normalizeSku(isset($variation['sku']) ? $variation['sku'] : '') === '') {
                $comparison['uncomparable'][] = 'A PrestaShop combination is missing its SKU.';
                continue;
            }

            $sku = self::normalizeSku($variation['sku']);
            $prestashop_by_sku[$sku][] = $variation;
        }

        foreach ($yuju_variations as $variation) {
            if (!is_array($variation) || self::normalizeSku(isset($variation['sku']) ? $variation['sku'] : '') === '') {
                $comparison['uncomparable'][] = 'A Yuju variation is missing its SKU.';
                continue;
            }

            $sku = self::normalizeSku($variation['sku']);
            $yuju_by_sku[$sku][] = $variation;
        }

        foreach ($prestashop_by_sku as $sku => $prestashop_matches) {
            if (count($prestashop_matches) !== 1 || (isset($yuju_by_sku[$sku]) && count($yuju_by_sku[$sku]) > 1)) {
                $comparison['ambiguous'][] = $sku;
                continue;
            }

            $prestashop = $prestashop_matches[0];
            if (empty($yuju_by_sku[$sku])) {
                $comparison['to_create'][] = $prestashop;
                continue;
            }

            $yuju = $yuju_by_sku[$sku][0];
            $variation_id = isset($yuju['id_variation']) ? $yuju['id_variation']
                : (isset($yuju['variation_id']) ? $yuju['variation_id'] : null);
            if (is_scalar($variation_id) && (string) $variation_id !== '') {
                $yuju['id_variation'] = (string) $variation_id;
            }
            $comparison['matched'][$sku] = $yuju;

            foreach (['sku', 'stock', 'price', 'size', 'color', 'color_text', 'secondary_color', 'custom_variation_value', 'ean', 'upc'] as $field) {
                $prestashop_has_field = array_key_exists($field, $prestashop);
                $yuju_has_field = array_key_exists($field, $yuju);
                if (!$prestashop_has_field && !$yuju_has_field) {
                    continue;
                }

                if (!$prestashop_has_field || !$yuju_has_field) {
                    if ($prestashop_has_field && !$yuju_has_field && $field !== 'sku') {
                        $comparison['differences'][$sku][] = [
                            'field' => $field,
                            'prestashop' => $prestashop[$field],
                            'yuju' => null,
                        ];
                    } else {
                        $comparison['uncomparable'][$sku][] = $field;
                    }
                    continue;
                }

                $field_type = in_array($field, ['stock'], true) ? 'integer'
                    : (in_array($field, ['price'], true) ? 'decimal' : 'string');
                if (!self::valuesEqual($prestashop[$field], $yuju[$field], $field_type)) {
                    $comparison['differences'][$sku][] = [
                        'field' => $field,
                        'prestashop' => $prestashop[$field],
                        'yuju' => $yuju[$field],
                    ];
                }
            }

            if (!empty($comparison['differences'][$sku]) && empty($yuju['id_variation'])) {
                $comparison['cannot_update'][$sku] = true;
            }
        }

        return $comparison;
    }

    public static function normalizeSku($sku)
    {
        return is_scalar($sku) ? strtoupper(trim((string) $sku)) : '';
    }

    private static function addSkuMatch(&$index, $sku, $product_id)
    {
        $normalized_sku = self::normalizeSku($sku);
        if ($normalized_sku === '') {
            return;
        }

        if (!isset($index['by_sku'][$normalized_sku])) {
            $index['by_sku'][$normalized_sku] = [];
        }

        $index['by_sku'][$normalized_sku][(string) $product_id] = true;
    }

    private static function valuesEqual($left, $right, $field_type)
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        if (in_array($field_type, ['integer', 'decimal'], true)) {
            if (!is_numeric($left) || !is_numeric($right)) {
                return false;
            }

            if ($field_type === 'integer') {
                return (int) $left === (int) $right;
            }

            return abs((float) $left - (float) $right) < 0.000001;
        }

        if ($field_type === 'boolean') {
            return self::normalizeBoolean($left) === self::normalizeBoolean($right);
        }

        return self::normalizeValue($left) === self::normalizeValue($right);
    }

    private static function normalizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private static function normalizeValue($value)
    {
        if (is_string($value)) {
            // Reportes de Yuju pueden serializar el mismo texto con entidades HTML,
            // espacios repetidos o saltos de línea. Normalizar antes de declarar diferencias.
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            $value = preg_replace('/\\s+/u', ' ', trim($value));
            if (class_exists('Normalizer')) {
                $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
                if ($normalized !== false) {
                    $value = $normalized;
                }
            }

            return $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $is_list = array_keys($value) === range(0, count($value) - 1);
        if (!$is_list) {
            ksort($value);
        }

        foreach ($value as $key => $nested_value) {
            $value[$key] = self::normalizeValue($nested_value);
        }

        return $value;
    }
}
