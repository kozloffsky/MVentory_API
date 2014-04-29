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
 * Model for category matching rules
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Model_Matching
  extends Mage_Core_Model_Abstract
  implements IteratorAggregate {

  const DEFAULT_RULE_ID = 'mventory_default_rule';
  const LOST_CATEGORY_PATH = 'mventory_tm/api/lost_category';

  /**
   * Initialize resource mode
   *
   */
  protected function _construct () {
    $this->_init('mventory_tm/matching');
  }

  public function loadBySetId ($setId, $cleanRules = true) {
    $this
      ->setData('attribute_set_id', $setId)
      ->load($setId, 'attribute_set_id');

    return $cleanRules ? $this->_clean() : $this;
  }

  public function getIterator () {
    return new ArrayIterator($this->getData('rules'));
  }

  public function append ($rule) {
    $all = $this->getData('rules');

    if ($rule['id'] != self::DEFAULT_RULE_ID
        && isset($all[self::DEFAULT_RULE_ID])) {
      $defaultRule = array_pop($all);

      $all[$rule['id']] = $rule;
      $all[self::DEFAULT_RULE_ID] = $defaultRule;
    } else
      $all[$rule['id']] = $rule;

    return $this->setData('rules', $all);
  }

  public function remove ($ruleId) {
    $all = $this->getData('rules');

    unset($all[$ruleId]);

    return $this->setData('rules', $all);
  }

  public function reorder ($ids) {
    $_all = $this->getData('rules');

    $all = array();

    foreach ($ids as $id)
      $all[$id] = $_all[$id];

    if (isset($_all[self::DEFAULT_RULE_ID]))
      $all[self::DEFAULT_RULE_ID] = $_all[self::DEFAULT_RULE_ID];

    return $this->setData('rules', $all);
  }

  public function matchCategory ($product) {
    if (($setId = $product->getAttributeSetId()) === false)
      return (int) $this->_getLostCategoryId($product);

    $this->loadBySetId($setId);

    if (!$this->getId())
      return (int) $this->_getLostCategoryId($product);

    $_attributes = array();

    $attributes = $product
      ->getTypeInstance()
      ->getEditableAttributes($product);

    foreach ($attributes as $code => $attribute)
      if ($id = $attribute->getId())
        $_attributes[$id] = $code;

    unset($attributes, $attribute, $code, $id);

    $categoryId = null;

    $rules = $this->getData('rules');

    foreach ($rules as $rule) {
      foreach ($rule['attrs'] as $attribute) {
        if (!isset($_attributes[$attribute['id']]))
          continue;

        $code = $_attributes[$attribute['id']];

        $productValue = (array) $product->getData($code);
        $ruleValue = (array) $attribute['value'];

        if (!count(array_intersect($productValue, $ruleValue)))
          continue 2;
      }

      if (isset($rule['category'])) {
        $categoryId = (int) $rule['category'];

        break;
      }
    }

    if ($categoryId == null && isset($rules[self::DEFAULT_RULE_ID]))
      $categoryId = (int) $rules[self::DEFAULT_RULE_ID]['category'];

    $categories = Mage::getResourceModel('catalog/category_collection')
                    ->load()
                    ->toArray();

    if ($categoryId == null || !isset($categories[$categoryId]))
      $categoryId = (int) $this->_getLostCategoryId($product);

    if (!($categoryId && isset($categories[$categoryId])))
      return false;

    return $categoryId;
  }

  protected function _getLostCategoryId ($product) {
    $helper = Mage::helper('mventory_tm/product');

    return $helper->getConfig(self::LOST_CATEGORY_PATH,
                              $helper->getWebsite($product));
  }

  protected function _clean () {
    $attrs = array();

    $_attrs = Mage::getResourceModel('catalog/product_attribute_collection')
               ->setAttributeSetFilter($this->getData('attribute_set_id'));

    foreach ($_attrs as $attr) {
      if (substr($attr->getAttributeCode(), -1) != '_')
        continue;

      $type = $attr->getFrontendInput();

      if (!($type == 'select' || $type == 'multiselect'))
        continue;

      $allOptions = $attr
                      ->getSource()
                      ->getAllOptions();

      $optionIds = array();

      foreach ($allOptions as $options)
        if ($options['value'])
          $optionIds[] = $options['value'];

      $attrs[$attr->getId()] = $optionIds;
    }

    unset($_attrs);

    $rules = $this->getData('rules');

    $isChanged = false;

    foreach ($rules as $ruleId => &$rule) {
      if ($ruleId == self::DEFAULT_RULE_ID)
        continue;

      foreach ($rule['attrs'] as $n => $attr) {
        $id = $attr['id'];

        //Keep attribute in the rule if it exists in the system, has values
        //and containes one value at least which exists in the attribute
        $keepAttr = isset($attrs[$id])
                    && count($attrs[$id])
                    && count(array_intersect($attr['value'], $attrs[$id]));

        if (!$keepAttr) {
          unset($rule['attrs'][$n]);

          $isChanged = true;
        }
      }

      if (!count($rule['attrs'])) {
        unset($rules[$ruleId]);

        $isChanged = true;
      }
    }

    if ($isChanged)
      $this
        ->setData('rules', $rules)
        ->save();

    return $this;
  }
}