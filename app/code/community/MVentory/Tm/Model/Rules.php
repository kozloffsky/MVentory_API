<?php

class MVentory_Tm_Model_Rules
  extends Mage_Core_Model_Abstract
  implements IteratorAggregate {

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

    $all[$rule['id']] = $rule;

    return $this->setData('rules', $all);
  }

  public function remove ($ruleId) {
    $all = $this->getData('rules');

    unset($all[$ruleId]);

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

    $categoryId = null;

    $rules = $this->getData('rules');

    foreach ($rules as $rule) {
      foreach ($rule['attrs'] as $attribute) {
        $code = $_attributes[$attribute['id']];

        $productValue = (array) $product->getData($code);
        $ruleValue = (array) $attribute['value'];

        if (!count(array_intersect($productValue, $ruleValue)))
          continue 2;
      }

      $categoryId = (int) $rule['category'];

      break;
    }

    if ($categoryId == null && ($defaultRule = end($rules)))
      $categoryId = (int) $defaultRule['category'];

    $categories = Mage::getModel('mventory_tm/connector')
                    ->getTmCategories();

    $category = isset($categories[$categoryId])
                  ? implode(' / ', $categories[$categoryId]['name'])
                    : Mage::helper('mventory_tm')
                        ->__('TM category doesn\'t exist');

    return array(
      'id' => $categoryId,
      'name' => $category
    );
  }
}
