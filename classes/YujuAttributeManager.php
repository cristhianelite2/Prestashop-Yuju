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

require_once dirname(__FILE__) . '/YujuApiClient.php';
require_once dirname(__FILE__) . '/YujuLogger.php';

class YujuAttributeManager
{
    private $api_client;
    private $logger;

    public function __construct()
    {
        $this->api_client = new YujuApiClient();
        $this->logger = new YujuLogger();
    }

    /**
     * Synchronize attributes from Yuju to PrestaShop.
     */
    public function syncAttributesFromYuju($force_update = false)
    {
        $this->logger->log('Starting attribute synchronization from Yuju', 'info');

        $results = [
            'success' => true,
            'synced_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'errors' => [],
        ];

        try {
            // Get attributes from Yuju
            $yuju_attributes = $this->api_client->getAttributes();

            if (!$yuju_attributes || !isset($yuju_attributes['data'])) {
                throw new Exception('No attributes received from Yuju API');
            }

            foreach ($yuju_attributes['data'] as $yuju_attribute) {
                try {
                    $sync_result = $this->syncSingleAttributeFromYuju($yuju_attribute, $force_update);

                    if ($sync_result['action'] === 'created') {
                        ++$results['created_count'];
                    } elseif ($sync_result['action'] === 'updated') {
                        ++$results['updated_count'];
                    }

                    ++$results['synced_count'];
                } catch (Exception $e) {
                    $results['errors'][] = 'Attribute ' . $yuju_attribute['id'] . ': ' . $e->getMessage();
                    $this->logger->log('Failed to sync attribute ' . $yuju_attribute['id'] . ': ' . $e->getMessage(), 'error');
                }
            }
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $this->logger->log('Attribute synchronization from Yuju failed: ' . $e->getMessage(), 'error');
        }

        $this->logger->log('Attribute synchronization from Yuju completed. Synced: ' . $results['synced_count'], 'info');

        return $results;
    }

    /**
     * Synchronize single attribute from Yuju.
     */
    protected function syncSingleAttributeFromYuju($yuju_attribute, $force_update = false)
    {
        // Check if attribute already exists in mapping
        $existing_mapping = $this->getAttributeMappingByYujuId($yuju_attribute['id']);

        if ($existing_mapping) {
            // Update existing attribute if needed
            if ($force_update || $this->shouldUpdateAttribute($existing_mapping, $yuju_attribute)) {
                $this->updateAttributeFromYuju($existing_mapping, $yuju_attribute);

                return ['action' => 'updated', 'attribute_id' => $existing_mapping['prestashop_attribute_id']];
            }

            return ['action' => 'skipped', 'attribute_id' => $existing_mapping['prestashop_attribute_id']];
        } else {
            // Create new attribute
            $attribute_id = $this->createAttributeFromYuju($yuju_attribute);

            return ['action' => 'created', 'attribute_id' => $attribute_id];
        }
    }

    /**
     * Create PrestaShop attribute from Yuju data.
     */
    protected function createAttributeFromYuju($yuju_attribute)
    {
        // Create attribute group first if it doesn't exist
        $attribute_group_id = $this->getOrCreateAttributeGroup($yuju_attribute);

        // Create attribute value using direct database operations for compatibility
        $languages = Language::getLanguages(false);

        // Prepare attribute data
        $attribute_data = [];
        $attribute_data['id_attribute_group'] = (int) $attribute_group_id;

        // Set color if available
        if (isset($yuju_attribute['color'])) {
            $attribute_data['color'] = pSQL($yuju_attribute['color']);
        }

        // Set position if available
        if (isset($yuju_attribute['position'])) {
            $attribute_data['position'] = (int) $yuju_attribute['position'];
        }

        // Insert attribute using direct database query for PrestaShop 8 compatibility
        if (!Db::getInstance()->insert('attribute', $attribute_data)) {
            throw new Exception('Failed to create attribute in PrestaShop database');
        }

        // Get the inserted attribute ID
        $attribute_id = Db::getInstance()->Insert_ID();

        // Insert attribute names for all languages
        foreach ($languages as $language) {
            $attribute_lang_data = [
                'id_attribute' => (int) $attribute_id,
                'id_lang' => (int) $language['id_lang'],
                'name' => pSQL($yuju_attribute['name']),
            ];

            if (!Db::getInstance()->insert('attribute_lang', $attribute_lang_data)) {
                throw new Exception('Failed to create attribute language data');
            }
        }

        // Create mapping
        $this->createAttributeMapping($attribute_id, $yuju_attribute, $attribute_group_id);

        $this->logger->log('Created attribute: ' . $yuju_attribute['name'] . ' (ID: ' . $attribute_id . ')', 'info');

        return $attribute_id;
    }

    /**
     * Update PrestaShop attribute from Yuju data.
     */
    protected function updateAttributeFromYuju($mapping, $yuju_attribute)
    {
        $attribute_id = (int) $mapping['prestashop_attribute_id'];

        // Prepare update data
        $update_data = [];

        // Update color if available
        if (isset($yuju_attribute['color'])) {
            $update_data['color'] = pSQL($yuju_attribute['color']);
        }

        // Update position if available
        if (isset($yuju_attribute['position'])) {
            $update_data['position'] = (int) $yuju_attribute['position'];
        }

        // Update attribute table if there's data to update
        if (!empty($update_data)) {
            if (!Db::getInstance()->update('attribute', $update_data, 'id_attribute = ' . $attribute_id)) {
                throw new Exception('Failed to update attribute in PrestaShop database');
            }
        }

        // Update attribute names for all languages
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $update_lang_data = [
                'name' => pSQL($yuju_attribute['name']),
            ];

            $where_condition = 'id_attribute = ' . $attribute_id . ' AND id_lang = ' . (int) $language['id_lang'];

            if (!Db::getInstance()->update('attribute_lang', $update_lang_data, $where_condition)) {
                throw new Exception('Failed to update attribute language data');
            }
        }

        // Update mapping
        $this->updateAttributeMapping($mapping['id'], $yuju_attribute);

        $this->logger->log('Updated attribute: ' . $yuju_attribute['name'] . ' (ID: ' . $attribute_id . ')', 'info');
    }

    /**
     * Get or create attribute group.
     */
    protected function getOrCreateAttributeGroup($yuju_attribute)
    {
        $group_name = isset($yuju_attribute['group_name']) ? $yuju_attribute['group_name'] : 'Default';

        // Check if group already exists
        $existing_group = Db::getInstance()->getRow('
            SELECT ag.id_attribute_group
            FROM ' . _DB_PREFIX_ . 'attribute_group ag
            JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON ag.id_attribute_group = agl.id_attribute_group
            WHERE agl.name = "' . pSQL($group_name) . '"
            AND agl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
        ');

        if ($existing_group) {
            return $existing_group['id_attribute_group'];
        }

        // Create new attribute group
        $attribute_group = new AttributeGroup();
        $attribute_group->is_color_group = isset($yuju_attribute['is_color_group']) ? (bool) $yuju_attribute['is_color_group'] : false;
        $attribute_group->group_type = 'select';
        $attribute_group->position = 0;

        // Set names for all languages
        $languages = Language::getLanguages(false);

        foreach ($languages as $language) {
            $attribute_group->name[$language['id_lang']] = $group_name;
            $attribute_group->public_name[$language['id_lang']] = $group_name;
        }

        if (!$attribute_group->add()) {
            throw new Exception('Failed to create attribute group: ' . $group_name);
        }

        $this->logger->log('Created attribute group: ' . $group_name . ' (ID: ' . $attribute_group->id . ')', 'info');

        return $attribute_group->id;
    }

    /**
     * Create attribute mapping.
     */
    protected function createAttributeMapping($prestashop_attribute_id, $yuju_attribute, $attribute_group_id)
    {
        $mapping_data = [
            'prestashop_attribute_id' => (int) $prestashop_attribute_id,
            'prestashop_attribute_group_id' => (int) $attribute_group_id,
            'yuju_attribute_id' => pSQL($yuju_attribute['id']),
            'yuju_attribute_name' => pSQL($yuju_attribute['name']),
            'attribute_type' => isset($yuju_attribute['type']) ? pSQL($yuju_attribute['type']) : 'text',
            'sync_direction' => 'yuju_to_ps',
            'auto_create_values' => 1,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return Db::getInstance()->insert('yuju_attribute_mapping', $mapping_data);
    }

    /**
     * Update attribute mapping.
     */
    protected function updateAttributeMapping($mapping_id, $yuju_attribute)
    {
        $mapping_data = [
            'yuju_attribute_name' => pSQL($yuju_attribute['name']),
            'attribute_type' => isset($yuju_attribute['type']) ? pSQL($yuju_attribute['type']) : 'text',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return Db::getInstance()->update('yuju_attribute_mapping', $mapping_data, 'id = ' . (int) $mapping_id);
    }

    /**
     * Get attribute mapping by Yuju ID.
     */
    public function getAttributeMappingByYujuId($yuju_attribute_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            WHERE yuju_attribute_id = "' . pSQL($yuju_attribute_id) . '"
        ');
    }

    /**
     * Get attribute mapping by PrestaShop ID.
     */
    public function getAttributeMappingByPrestashopId($prestashop_attribute_id)
    {
        return Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            WHERE prestashop_attribute_id = ' . (int) $prestashop_attribute_id
        );
    }

    /**
     * Check if attribute should be updated.
     */
    protected function shouldUpdateAttribute($mapping, $yuju_attribute)
    {
        // Check if name has changed
        if ($mapping['yuju_attribute_name'] !== $yuju_attribute['name']) {
            return true;
        }

        // Check if type has changed
        $current_type = isset($yuju_attribute['type']) ? $yuju_attribute['type'] : 'text';

        if ($mapping['attribute_type'] !== $current_type) {
            return true;
        }

        return false;
    }

    /**
     * Synchronize attribute values.
     */
    public function syncAttributeValues($attribute_mapping_id, $yuju_values = null)
    {
        $mapping = Db::getInstance()->getRow('
            SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            WHERE id = ' . (int) $attribute_mapping_id
        );

        if (!$mapping) {
            throw new Exception('Attribute mapping not found');
        }

        $results = [
            'success' => true,
            'synced_count' => 0,
            'created_count' => 0,
            'errors' => [],
        ];

        try {
            // Get values from Yuju if not provided
            if (!$yuju_values) {
                $yuju_values = $this->api_client->getAttributeValues($mapping['yuju_attribute_id']);
            }

            if (!$yuju_values || !isset($yuju_values['data'])) {
                throw new Exception('No attribute values received from Yuju API');
            }

            foreach ($yuju_values['data'] as $yuju_value) {
                try {
                    $this->syncSingleAttributeValue($mapping, $yuju_value);
                    ++$results['synced_count'];
                    ++$results['created_count'];
                } catch (Exception $e) {
                    $results['errors'][] = 'Value ' . $yuju_value['value'] . ': ' . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            $this->logger->log('Attribute values synchronization failed: ' . $e->getMessage(), 'error');
        }

        return $results;
    }

    /**
     * Synchronize single attribute value.
     */
    protected function syncSingleAttributeValue($mapping, $yuju_value)
    {
        // Check if value already exists
        $existing_value = Db::getInstance()->getRow('
            SELECT a.id_attribute
            FROM ' . _DB_PREFIX_ . 'attribute a
            JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON a.id_attribute = al.id_attribute
            WHERE a.id_attribute_group = ' . (int) $mapping['prestashop_attribute_group_id'] . '
            AND al.name = "' . pSQL($yuju_value['value']) . '"
            AND al.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
        ');

        if ($existing_value) {
            return $existing_value['id_attribute'];
        }

        // Create new attribute value using direct database operations
        $languages = Language::getLanguages(false);

        // Prepare attribute data
        $attribute_data = [];
        $attribute_data['id_attribute_group'] = (int) $mapping['prestashop_attribute_group_id'];

        // Set color if available
        if (isset($yuju_value['color'])) {
            $attribute_data['color'] = pSQL($yuju_value['color']);
        }

        // Set position if available
        if (isset($yuju_value['position'])) {
            $attribute_data['position'] = (int) $yuju_value['position'];
        }

        // Insert attribute using direct database query
        if (!Db::getInstance()->insert('attribute', $attribute_data)) {
            throw new Exception('Failed to create attribute value in PrestaShop database');
        }

        // Get the inserted attribute ID
        $attribute_id = Db::getInstance()->Insert_ID();

        // Insert attribute names for all languages
        foreach ($languages as $language) {
            $attribute_lang_data = [
                'id_attribute' => (int) $attribute_id,
                'id_lang' => (int) $language['id_lang'],
                'name' => pSQL($yuju_value['value']),
            ];

            if (!Db::getInstance()->insert('attribute_lang', $attribute_lang_data)) {
                throw new Exception('Failed to create attribute value language data');
            }
        }

        $this->logger->log('Created attribute value: ' . $yuju_value['value'] . ' (ID: ' . $attribute_id . ')', 'info');

        return $attribute_id;
    }

    /**
     * Get all attribute mappings.
     */
    public function getAllAttributeMappings($active_only = false)
    {
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping';

        if ($active_only) {
            $sql .= ' WHERE is_active = 1';
        }

        $sql .= ' ORDER BY `created_at` DESC';

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get attribute mapping statistics.
     */
    public function getAttributeMappingStats()
    {
        $stats = [];

        // Total mappings
        $stats['total_mappings'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
        ');

        // Active mappings
        $stats['active_mappings'] = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping WHERE is_active = 1
        ');

        // Mappings by type
        $stats['by_type'] = Db::getInstance()->executeS('
            SELECT attribute_type, COUNT(*) as count
            FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            GROUP BY attribute_type
        ');

        // Mappings by sync direction
        $stats['by_direction'] = Db::getInstance()->executeS('
            SELECT sync_direction, COUNT(*) as count
            FROM ' . _DB_PREFIX_ . 'yuju_attribute_mapping
            GROUP BY sync_direction
        ');

        return $stats;
    }

    /**
     * Delete attribute mapping.
     */
    public function deleteAttributeMapping($mapping_id)
    {
        return Db::getInstance()->delete('yuju_attribute_mapping', 'id = ' . (int) $mapping_id);
    }

    /**
     * Enable/disable attribute mapping.
     */
    public function toggleAttributeMapping($mapping_id, $active = true)
    {
        return Db::getInstance()->update(
            'yuju_attribute_mapping',
            ['is_active' => (int) $active, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ' . (int) $mapping_id
        );
    }

    /**
     * Get PrestaShop attributes for mapping.
     */
    public function getPrestashopAttributes()
    {
        return Db::getInstance()->executeS('
            SELECT a.id_attribute, a.id_attribute_group, al.name as attribute_name,
            agl.name as group_name, a.color, a.position
            FROM ' . _DB_PREFIX_ . 'attribute a
            JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON a.id_attribute = al.id_attribute
            JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON a.id_attribute_group = agl.id_attribute_group
            WHERE al.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
            AND agl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
            ORDER BY `agl`.`name`, `al`.`name`
        ');
    }

    /**
     * Get PrestaShop attribute groups.
     */
    public function getPrestashopAttributeGroups()
    {
        return Db::getInstance()->executeS('
            SELECT ag.id_attribute_group, agl.name, ag.is_color_group, ag.group_type, ag.position
            FROM ' . _DB_PREFIX_ . 'attribute_group ag
            JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON ag.id_attribute_group = agl.id_attribute_group
            WHERE agl.id_lang = ' . (int) Configuration::get('PS_LANG_DEFAULT') . '
            ORDER BY `ag`.`position`, `agl`.`name`
        ');
    }

    /**
     * Apply attribute transformations.
     */
    public function applyAttributeTransformations($value, $transformation_rules)
    {
        if (empty($transformation_rules)) {
            return $value;
        }

        $rules = json_decode($transformation_rules, true);

        if (!$rules) {
            return $value;
        }

        foreach ($rules as $rule) {
            switch ($rule['type']) {
                case 'replace':
                    $value = str_replace($rule['search'], $rule['replace'], $value);
                    break;
                case 'uppercase':
                    $value = strtoupper($value);
                    break;
                case 'lowercase':
                    $value = strtolower($value);
                    break;
                case 'trim':
                    $value = trim($value);
                    break;
                case 'prefix':
                    $value = $rule['prefix'] . $value;
                    break;
                case 'suffix':
                    $value = $value . $rule['suffix'];
                    break;
            }
        }

        return $value;
    }
}
