<?php

/**
 * Product description block
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Block_Product_View_Description
  extends Mage_Catalog_Block_Product_View_Description {

  public function setTemplate ($template) {
    return strlen($this->getProduct()->getDescription()) > 4
             ? parent::setTemplate($template)
               : null;
  }
}
