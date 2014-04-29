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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Block for list of category matching rules
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Block_Matching extends Mage_Adminhtml_Block_Template {

  protected $_attrs = null;

  protected $_categories = null;
  protected $_tmCategories = null;

  protected $_usedTmCategories = array();

  protected function _construct () {
    $this->_attrs['-1'] = array(
      'label' => $this->__('Select an attribute...'),
      'used' => false,
      'used_values' => array()
    );

    $labels[] = '';

    $attrs = Mage::getResourceModel('catalog/product_attribute_collection')
               ->setAttributeSetFilter($this->_getSetId());

    foreach ($attrs as $attr) {
      if (substr($attr->getAttributeCode(), -1) != '_')
        continue;

      $type = $attr->getFrontendInput();

      if (!($type == 'select' || $type == 'multiselect'))
        continue;

      $id = $attr->getId();

      $this->_attrs[$id] = array(
        'label' => $attr->getFrontendLabel(),
        'used' => false,
        'used_values' => array()
      );

      $labels[] = $attr->getFrontendLabel();
    }

    unset($attrs);

    $keys = array_keys($this->_attrs);

    array_multisort($labels, SORT_ASC, SORT_STRING, $keys, $this->_attrs);

    $this->_attrs = array_combine($keys, $this->_attrs);

    unset($keys, $labels);

    $options = Mage::getResourceModel('eav/entity_attribute_option_collection')
                 ->setAttributeFilter(array('in' => array_keys($this->_attrs)))
                 ->setStoreFilter($attr->getStoreId())
                 ->setPositionOrder('asc', true);

    foreach ($options as $option)
      $this->_attrs[$option->getAttributeId()]['values'][$option->getId()]
        = $option->getValue();

    $this->_tmCategories = Mage::getModel('mventory_tm/connector')
                             ->getTmCategories();

    $this->_categories = Mage::getResourceModel('catalog/category_collection')
                           ->addNameToResult()
                           ->load()
                           ->toArray();
  }

  /**
   * Prepare layout
   *
   * @return MVentory_Tm_Block_Matching
   */
  protected function _prepareLayout () {
    $data = array(
      'id' => 'tm-reset-rule-button',
      'label' => Mage::helper('mventory_tm')->__('Reset rule')
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data);

    $this->setChild('reset_rule_button', $button);

    $data = array(
      'id' => 'tm-save-rule-button',
      'class' => 'disabled',
      'label' => Mage::helper('mventory_tm')->__('Save rule')
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data);

    $this->setChild('save_rule_button', $button);

    $data = array(
      'id' => 'tm-categories-button',
      'label' => Mage::helper('mventory_tm')->__('Select TM category')
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data);

    $this->setChild('categories_button', $button);

    $data = array(
      'id' => 'tm-ignore-button',
      'label' => Mage::helper('mventory_tm')->__('Don\'t list on TM')
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data);

    $this->setChild('ignore_button', $button);
  }

  protected function _getAttributesJson () {
    return Mage::helper('core')->jsonEncode($this->_attrs);
  }

  protected function _getRules () {
    return Mage::getModel('mventory_tm/matching')
      ->loadBySetId($this->_getSetId());
  }

  protected function _getAddRuleButtonHtml () {
    return $this->getChildHtml('add_rule_button');
  }

  protected function _getUrlsJson () {
    $params = array(
      'type' => MVentory_Tm_Block_Categories::TYPE_RADIO
    );

    $categories = $this
                    ->getUrl('mventory_tm/adminhtml_tm/categories/', $params);

    $params = array(
      'set_id' => $this->_getSetId(),
      'ajax' => true
    );

    $addrule = $this->getUrl('mventory_tm/matching/append/', $params);
    $remove = $this->getUrl('mventory_tm/matching/remove/', $params);
    $reorder = $this->getUrl('mventory_tm/matching/reorder/', $params);

    return Mage::helper('core')->jsonEncode(compact('categories',
                                                    'addrule',
                                                    'remove',
                                                    'reorder'));
  }

  protected function _getUsedTmCategories () {
    return Mage::helper('core')
             ->jsonEncode(array_unique($this->_usedTmCategories, SORT_NUMERIC));
  }

  /**
   * Retrieve current attribute set
   *
   * @return Mage_Eav_Model_Entity_Attribute_Set
   */
  protected function _getAttributeSet () {
    return Mage::registry('current_attribute_set');
  }

  /**
   * Retrieve current attribute set ID
   *
   * @return int
   */
  protected function _getSetId () {
    return $this->_getAttributeSet()->getId();
  }

  protected function _prepareRule ($data) {
    $id = $data['id'];
    $default = ($id == MVentory_Tm_Model_Matching::DEFAULT_RULE_ID);

    $category = $data['category'];
    $tmCategory = $data['tm_category'];

    $hasCategory = false;
    $hasTmCategory = false;

    if ($category == null)
      $category = $this->__('Category not selected');
    else if (!isset($this->_categories[$category]))
      $category = $this->__('Category doesn\'t exist anymore');
    else {
      $hasCategory = true;
      $category = $this->_categories[$category]['name'];
    }

    if ($tmCategory == -1) {
      $hasTmCategory = true;

      $tmCategory = $this->__('Don\'t list on TM');
    } else if ($tmCategory == null)
      $tmCategory = $this->__('TM category not selected');
    else if (!isset($this->_tmCategories[$tmCategory]))
      $tmCategory = $this->__('TM category doesn\'t exist anymore');
    else {
      $hasTmCategory = true;

      $this->_usedTmCategories[] = (int) $tmCategory;

      $tmCategory = implode(' - ', $this->_tmCategories[$tmCategory]['name']);
    }

    $attrs = array();

    foreach ($data['attrs'] as $attr) {
      $_attr = &$this->_attrs[$attr['id']];

      $_attr['used'] = true;

      if (is_array($attr['value'])) {
        $values = array();

        foreach ($attr['value'] as $valueId)
          if (isset($_attr['values'][$valueId])) {
            $values[] = $_attr['values'][$valueId];

            $_attr['used_values'][$valueId] = true;
          }

        $attrs[$_attr['label']] = implode(', ', $values);

        continue;
      }

      $attrs[$_attr['label']] = isset($_attr['values'][$attr['value']])
                                  ? $_attr['values'][$attr['value']]
                                    : '';
    }

    return array(
      'id' => $id,
      'default' => $default,
      'category' => $category,
      'has_category' => $hasCategory,
      'tm_category' => $tmCategory,
      'has_tm_category' => $hasTmCategory,
      'attrs' => $attrs
    );
  }
}
