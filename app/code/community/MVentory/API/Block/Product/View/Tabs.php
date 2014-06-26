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
 * Product information tabs
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Block_Product_View_Tabs
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
  function addCmsBlockAsTabByAttr ($alias, $code) {

    if (!$code)
      return false;

    $product = $this->getProduct();

    $attributes = $product->getAttributes();

    if (!isset($attributes[$code]))
      return false;

    $id = $attributes[$code]
            ->getFrontend()
            ->getValue($product);

    $title = $this->__($attributes[$code]->getStoreLabel());

    return $this->addCmsBlockAsTab($alias, $title, $id);
  }
}
