<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Entity Setup Model
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Resource_Setup extends Mage_Eav_Model_Entity_Setup {

  protected $_mappings = array(
    Mage_Catalog_Model_Product::ENTITY => array(
      'frontend_input_renderer' => array('input_renderer'),
      'is_global' => array(
        'global',
        Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL
      ),
      'is_visible' => array('visible', 1),
      'is_searchable' => array('searchable', 0),
      'is_filterable' => array('filterable', 0),
      'is_comparable' => array('comparable', 0),
      'is_visible_on_front' => array('visible_on_front', 0),
      'is_wysiwyg_enabled' => array('wysiwyg_enabled', 0),
      'is_html_allowed_on_front' => array(null, 0),
      'is_visible_in_advanced_search' => array('visible_in_advanced_search', 0),
      'is_filterable_in_search' => array('filterable_in_search', 0),
      'used_in_product_listing' => array(null, 0),
      'used_for_sort_by' => array(null, 0),
      'apply_to' => null,
      'position' => array(null, 0),
      'is_configurable' => array(null, 1),
      'is_used_for_promo_rules' => array(null, 0)
    ),
    'customer' => array(
      'is_visible' => array('visible', 1),
      'is_system' => array('system', 1),
      'input_filter '=> null,
      'multiline_count' => array(null, 0),
      'validate_rules' => null,
      'data_model' => array('data'),
      'sort_order' => array('position', 0)
    )
  );

  public function addAttribute ($entityTypeId, $code, array $attr) {
    $attr['entity_type_id'] = $this->getEntityTypeId($entityTypeId);

    return parent::addAttribute($entityTypeId, $code, $attr);
  }

  protected function _prepareValues ($attr) {
    $data = parent::_prepareValues($attr);

    if (!(isset($attr['entity_type_id']) && $type = $attr['entity_type_id']))
      return $data;

    $types = array_keys($this->_mappings);
    $types = array_combine(
      array_map(array($this, 'getEntityTypeId'), $types),
      $types
    );

    if (!isset($types[$type]))
      return $data;

    $map = $this->_mappings[$types[$type]];

    foreach ($map as $name => $params)
      $entityData[$name] = $this->_getValue(
        $attr,
        (isset($params[0]) && $params[0]) ? $params[0] : $name,
        isset($params[1]) ? $params[1] : null
      );

    return array_merge($data, $entityData);
  }
}

?>
