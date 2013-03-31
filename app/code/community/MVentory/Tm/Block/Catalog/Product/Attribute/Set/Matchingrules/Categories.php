<?php

/**
 * Product categories tab
 *
 * @category   MVentor
 * @package    MVentory_Tm
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Block_Catalog_Product_Attribute_Set_Matchingrules_Categories
  extends Mage_Adminhtml_Block_Catalog_Category_Abstract {

  /**
   * Get JSON of a tree node or an associative array
   *
   * @param Varien_Data_Tree_Node|array $node
   * @param int $level
   * @return string
   */
  protected function _getNode ($node) {
    $item['text'] = $this->htmlEscape($node->getName());
    $item['id']  = $node->getId();

    if ($node->hasChildren()) {
      $item['children'] = array();

      foreach ($node->getChildren() as $child)
        $item['children'][] = $this->_getNode($child);
    }

    return $item;
  }

  public function getTreeJson () {
    $root = $this->_getNode($this->getRoot());
    $root = isset($root['children']) ? $root['children'] : array();

    return Mage::helper('core')->jsonEncode($root);
  }
}
