<?php

/**
 * Simple product data view
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Block_Product_View_Media
  extends Mage_Catalog_Block_Product_View_Media {

  public function getImageEditorHtml ($image) {
    if (!Mage::helper('mventory_tm')->isAdminLogged())
      return;

    return $this
             ->getLayout()
             ->createBlock('core/template')
             ->setTemplate('catalog/product/view/media/tm_editor.phtml')
             ->setImage($image->getFile())
             ->toHtml();
  }
}
