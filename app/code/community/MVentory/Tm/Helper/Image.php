<?php

class MVentory_Tm_Helper_Image extends Mage_Catalog_Helper_Image {
  public function __toString() {
    $destSubdir = $this->_getModel()->getDestinationSubdir();

    //!!!Should be fixed. It loads full product data on every image
    //request.
    $bkThumbnail = Mage::getModel('catalog/product')
                     ->load($this->getProduct()->getId())
                     ->getData('bk_thumbnail_');

    if (in_array($destSubdir, array('image', 'small_image'))
        && $this->getProduct()->getData($destSubdir) == 'no_selection'
        && $bkThumbnail)
      return $bkThumbnail . '&zoom=' . ($destSubdir == 'image' ? 1 : 5);

    return parent::__toString();
  }
}
