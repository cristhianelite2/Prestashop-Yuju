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

class YujuCategoryMapping extends ObjectModel
{
    /** @var int */
    public $id;

    /** @var int */
    public $prestashop_category_id;

    /** @var string */
    public $yuju_category_id;

    /** @var string */
    public $yuju_category_name;

    /** @var bool */
    public $sync_enabled;

    /** @var string */
    public $created_at;

    /** @var string */
    public $updated_at;

    /** @var string */
    public $last_sync_at;

    /** @var string */
    public $sync_status;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'yuju_category_mapping',
        'primary' => 'id',
        'fields' => [
            'prestashop_category_id' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'yuju_category_id' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 255,
            ],
            'yuju_category_name' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 255,
            ],
            'sync_enabled' => [
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
            'last_sync_at' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
            'sync_status' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
            ],
        ],
    ];

    /**
     * Get category mapping by PrestaShop category ID.
     *
     * @param int $prestashop_category_id
     * @return YujuCategoryMapping|false
     */
    public static function getByPrestashopCategoryId($prestashop_category_id)
    {
        $sql = 'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_category_mapping 
                WHERE prestashop_category_id = ' . (int) $prestashop_category_id;
        
        $id = Db::getInstance()->getValue($sql);
        
        if ($id) {
            return new self($id);
        }
        
        return false;
    }

    /**
     * Get category mapping by Yuju category ID.
     *
     * @param string $yuju_category_id
     * @return YujuCategoryMapping|false
     */
    public static function getByYujuCategoryId($yuju_category_id)
    {
        $sql = 'SELECT id FROM ' . _DB_PREFIX_ . 'yuju_category_mapping 
                WHERE yuju_category_id = "' . pSQL($yuju_category_id) . '"';
        
        $id = Db::getInstance()->getValue($sql);
        
        if ($id) {
            return new self($id);
        }
        
        return false;
    }

    /**
     * Get all active category mappings.
     *
     * @return array
     */
    public static function getActiveMappings()
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_category_mapping 
                WHERE sync_enabled = 1 
                ORDER BY prestashop_category_id';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Check if a mapping exists for a PrestaShop category.
     *
     * @param int $prestashop_category_id
     * @return bool
     */
    public static function mappingExists($prestashop_category_id)
    {
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_category_mapping 
                WHERE prestashop_category_id = ' . (int) $prestashop_category_id;
        
        return (bool) Db::getInstance()->getValue($sql);
    }

    /**
     * Update sync status.
     *
     * @param string $status
     * @return bool
     */
    public function updateSyncStatus($status)
    {
        $this->sync_status = $status;
        $this->last_sync_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');
        
        return $this->update();
    }

    /**
     * Enable sync for this mapping.
     *
     * @return bool
     */
    public function enableSync()
    {
        $this->sync_enabled = true;
        $this->updated_at = date('Y-m-d H:i:s');
        
        return $this->update();
    }

    /**
     * Disable sync for this mapping.
     *
     * @return bool
     */
    public function disableSync()
    {
        $this->sync_enabled = false;
        $this->updated_at = date('Y-m-d H:i:s');
        
        return $this->update();
    }
}