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

class YujuProductMapping extends ObjectModel
{
    /** @var int */
    public $id_mapping;

    /** @var string */
    public $prestashop_field;

    /** @var string */
    public $yuju_field;

    /** @var string */
    public $field_type;

    /** @var bool */
    public $is_required;

    /** @var string */
    public $sync_direction;

    /** @var string */
    public $transformation_rule;

    /** @var bool */
    public $is_active;

    /** @var string */
    public $created_at;

    /** @var string */
    public $updated_at;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'yuju_product_mapping',
        'primary' => 'id_mapping',
        'fields' => [
            'prestashop_field' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 255,
            ],
            'yuju_field' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 255,
            ],
            'field_type' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
            ],
            'is_required' => [
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool',
            ],
            'sync_direction' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
            ],
            'transformation_rule' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isCleanHtml',
                'size' => 1000,
            ],
            'is_active' => [
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool',
            ],
            'created_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
            'updated_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
        ],
    ];

    /**
     * Get mapping by PrestaShop field.
     *
     * @param string $prestashop_field
     * @return YujuProductMapping|false
     */
    public static function getByPrestashopField($prestashop_field)
    {
        $sql = 'SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_product_mapping 
                WHERE prestashop_field = "' . pSQL($prestashop_field) . '"';
        
        $id = Db::getInstance()->getValue($sql);
        
        if ($id) {
            return new self($id);
        }
        
        return false;
    }

    /**
     * Get mapping by Yuju field.
     *
     * @param string $yuju_field
     * @return YujuProductMapping|false
     */
    public static function getByYujuField($yuju_field)
    {
        $sql = 'SELECT id_mapping FROM ' . _DB_PREFIX_ . 'yuju_product_mapping 
                WHERE yuju_field = "' . pSQL($yuju_field) . '"';
        
        $id = Db::getInstance()->getValue($sql);
        
        if ($id) {
            return new self($id);
        }
        
        return false;
    }

    /**
     * Get all active mappings.
     *
     * @return array
     */
    public static function getActiveMappings()
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping 
                WHERE is_active = 1 
                ORDER BY prestashop_field';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get mappings by sync direction.
     *
     * @param string $direction
     * @return array
     */
    public static function getMappingsByDirection($direction)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_product_mapping 
                WHERE sync_direction = "' . pSQL($direction) . '" AND is_active = 1 
                ORDER BY prestashop_field';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Check if a mapping exists for a field combination.
     *
     * @param string $prestashop_field
     * @param string $yuju_field
     * @return bool
     */
    public static function mappingExists($prestashop_field, $yuju_field)
    {
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_product_mapping 
                WHERE prestashop_field = "' . pSQL($prestashop_field) . '" 
                OR yuju_field = "' . pSQL($yuju_field) . '"';
        
        return (bool) Db::getInstance()->getValue($sql);
    }

    /**
     * Enable mapping.
     *
     * @return bool
     */
    public function enable()
    {
        $this->is_active = true;
        $this->updated_at = date('Y-m-d H:i:s');
        
        return $this->update();
    }

    /**
     * Disable mapping.
     *
     * @return bool
     */
    public function disable()
    {
        $this->is_active = false;
        $this->updated_at = date('Y-m-d H:i:s');
        
        return $this->update();
    }
}