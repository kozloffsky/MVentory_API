<?php

/**
 * Product information tabs
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Block_Product_View_Tabs
  extends Mage_Catalog_Block_Product_View_Tabs {

  protected $_product = null;

  function getProduct () {
    if (!$this->_product)
      $this->_product = Mage::registry('product');

    return $this->_product;
  }

  /**
   * Add static block as tab to the container
   */
  function addCmsBlockAsTab ($alias, $title, $id) {

    if (!$title || !$id)
      return false;

    $this->_tabs[] = compact('alias', 'title');

    $block = $this
               ->getLayout()
               ->createBlock('cms/block', $alias)
               ->setBlockId($id);

    $this->setChild($alias, $block);
  }

  /**
   * Add static block as tab to the container by attribute's value
   */
  function addCmsBlockAsTabByAttr ($alias, $title, $code) {

    if (!$code)
      return false;

    $product = $this->getProduct();

    $attributes = $product->getAttributes();

    if (!isset($attributes[$code]))
      return false;

    $id = $attributes[$code]
            ->getFrontend()
            ->getValue($product);

    return $this->addCmsBlockAsTab($alias, $title, $id);
  }
}
