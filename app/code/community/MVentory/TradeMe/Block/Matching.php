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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Block for list of category matching rules
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Block_Matching
  extends Mage_Adminhtml_Block_Template {

  protected $_attrs = null;

  protected $_categories = null;
  protected $_usedCategories = array();

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

    $this->_categories = (new MVentory_TradeMe_Model_Api())->getCategories();
  }

  /**
   * Prepare layout
   *
   * @return MVentory_TradeMe_Block_Catalog_Product_Attribute_Set_Matching
   */
  protected function _prepareLayout () {
    parent::_prepareLayout();

    $data = array(
      'id' => 'trademe-rule-reset',
      'label' => Mage::helper('trademe')->__('Reset rule')
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data);

    $this->setChild('button_rule_reset', $button);

    $data = array(
      'id' => 'trademe-rule-save',
      'class' => 'disabled',
      'label' => Mage::helper('trademe')->__('Save rule')
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data);

    $this->setChild('button_rule_save', $button);

    $data = array(
      'id' => 'trademe-rule-categories',
      'label' => Mage::helper('trademe')->__('Select category')
    );

    $button = $this
                ->getLayout()
                ->createBlock('adminhtml/widget_button')
                ->setData($data);

    $this->setChild('button_rule_categories', $button);

    $data = array(
      'id' => 'trademe-rule-ignore',
      'label' => Mage::helper('trademe')->__('Don\'t list on TradeMe')
    );

    $button = $this
      ->getLayout()
      ->createBlock('adminhtml/widget_button')
      ->setData($data);

    $this->setChild('button_rule_ignore', $button);
  }

  protected function _getAttributesJson () {
    return Mage::helper('core')->jsonEncode($this->_attrs);
  }

  protected function _getRules () {
    return Mage::getModel('trademe/matching')->loadBySetId($this->_getSetId());
  }

  protected function _getAddRuleButtonHtml () {
    return $this->getChildHtml('add_rule_button');
  }

  protected function _getUrlsJson () {
    $params = array(
      'type' => MVentory_TradeMe_Block_Categories::TYPE_RADIO
    );

    $categories = $this->getUrl('trademe/categories', $params);

    $params = array(
      'set_id' => $this->_getSetId(),
      'ajax' => true
    );

    $addrule = $this->getUrl('trademe/matching/append/', $params);
    $remove = $this->getUrl('trademe/matching/remove/', $params);
    $reorder = $this->getUrl('trademe/matching/reorder/', $params);

    return Mage::helper('core')->jsonEncode(compact('categories',
                                                    'addrule',
                                                    'remove',
                                                    'reorder'));
  }

  protected function _getUsedCategories () {
    return Mage::helper('core')
      ->jsonEncode(array_unique($this->_usedCategories, SORT_NUMERIC));
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
    $default = ($id == MVentory_TradeMe_Model_Matching::DEFAULT_RULE_ID);

    $category = $data['category'];

    $hasCategory = isset($this->_categories[$category]) || $category == -1;

    switch (true) {
      case ($category == -1):
        $category = $this->__('Don\'t list on TradeMe');

        break;

      case $hasCategory:
        $this->_usedCategories[] = (int) $category;
        $category = implode(' - ', $this->_categories[$category]['name']);

        break;

      default:
        $category = $this->__('Category doesn\'t exist anymore');
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
      'attrs' => $attrs
    );
  }
}
