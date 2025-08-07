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

class YujuAttributeMapping extends ObjectModel
{
    /** @var int */
    public $id;

    /** @var string */
    public $prestashop_attribute_name;

    /** @var string */
    public $yuju_attribute_id;

    /** @var string */
    public $yuju_attribute_name;

    /** @var string */
    public $attribute_type;

    /** @var string */
    public $sync_direction;

    /** @var int */
    public $value_mapping_count;

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
        'table' => 'yuju_attribute_mapping',
        'primary' => 'id',
        'fields' => [
            'prestashop_attribute_name' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 255,
            ],
            'yuju_attribute_id' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 255,
            ],
            'yuju_attribute_name' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 255,
            ],
            'attribute_type' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
            ],
            'sync_direction' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
            ],
            'value_mapping_count' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
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
     * Get mapping by PrestaShop attribute name.
     *
     * @param string $prestashop_attribute_name
     * @return YujuAttributeMapping|false
     */
    public static function getByPrestashopAttributeName($prestashop_attribute_name)
    {
        $sql = 'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping 
                WHERE prestashop_attribute_name = "' . pSQL($prestashop_attribute_name) . '"';
        
        $id = Db::getInstance()->getValue($sql);
        
        if ($id) {
            return new self($id);
        }
        
        return false;
    }

    /**
     * Get mapping by Yuju attribute ID.
     *
     * @param string $yuju_attribute_id
     * @return YujuAttributeMapping|false
     */
    public static function getByYujuAttributeId($yuju_attribute_id)
    {
        $sql = 'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping 
                WHERE yuju_attribute_id = "' . pSQL($yuju_attribute_id) . '"';
        
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
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping 
                WHERE is_active = 1 
                ORDER BY prestashop_attribute_name';
        
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
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping 
                WHERE sync_direction = "' . pSQL($direction) . '" AND is_active = 1 
                ORDER BY prestashop_attribute_name';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get mappings by attribute type.
     *
     * @param string $type
     * @return array
     */
    public static function getMappingsByType($type)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping 
                WHERE attribute_type = "' . pSQL($type) . '" AND is_active = 1 
                ORDER BY prestashop_attribute_name';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Check if a mapping exists for an attribute combination.
     *
     * @param string $prestashop_attribute_name
     * @param string $yuju_attribute_id
     * @return bool
     */
    public static function mappingExists($prestashop_attribute_name, $yuju_attribute_id)
    {
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping 
                WHERE prestashop_attribute_name = "' . pSQL($prestashop_attribute_name) . '" 
                OR yuju_attribute_id = "' . pSQL($yuju_attribute_id) . '"';
        
        return (bool) Db::getInstance()->getValue($sql);
    }

    /**
     * Update value mapping count.
     *
     * @return bool
     */
    public function updateValueMappingCount()
    {
        $count = Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_attribute_value_mapping 
            WHERE attribute_mapping_id = ' . (int) $this->id
        );
        
        $this->value_mapping_count = (int) $count;
        $this->updated_at = date('Y-m-d H:i:s');
        
        return $this->update();
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