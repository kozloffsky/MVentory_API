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

    $all[] = $rule;

    return $this->setData('rules', $all);
  }
}
