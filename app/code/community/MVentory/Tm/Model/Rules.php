<?php

class MVentory_Tm_Model_Rules
  extends Mage_Core_Model_Abstract
  implements IteratorAggregate {

  const DEFAULT_RULE_ID = 'default_rule';

  /**
   * Initialize resource mode
   *
   */
  protected function _construct () {
    $this->_init('mventory_tm/rules');
  }

  public function loadBySetId ($setId) {
    return $this
             ->setData('attribute_set_id', $setId)
             ->load($setId, 'attribute_set_id');
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

  public function match ($product) {
    if (($setId = $product->getAttributeSetId()) === false)
      return false;

    $this->loadBySetId($setId);

    if (!$this->getId())
      return false;

    $_attributes = array();

    foreach ($product->getAttributes() as $code => $attribute)
      $_attributes[$attribute->getId()] = $code;

    unset($attribute, $code);

    $tmCategoryId = null;

    $rules = $this->getData('rules');

    foreach ($rules as $rule) {
      foreach ($rule['attrs'] as $attribute) {
        $code = $_attributes[$attribute['id']];

        $productValue = (array) $product->getData($code);
        $ruleValue = (array) $attribute['value'];

        if (!count(array_intersect($productValue, $ruleValue)))
          continue 2;
      }

      $tmCategoryId = (int) $rule['tm_category'];

      break;
    }

    if ($tmCategoryId == null) {
      if (isset($rules[self::DEFAULT_RULE_ID]))
        $tmCategoryId = (int) $rules[self::DEFAULT_RULE_ID]['category'];
      else
        return false;
    }

    $categories = Mage::getModel('mventory_tm/connector')
                    ->getTmCategories();

    $tmCategory = isset($categories[$tmCategoryId])
                    ? implode(' / ', $categories[$tmCategoryId]['name'])
                      : Mage::helper('mventory_tm')
                          ->__('TM category doesn\'t exist');

    return array(
      'tm_id' => $tmCategoryId,
      'tm_category' => $tmCategory
    );
  }
}
