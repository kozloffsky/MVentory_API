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

    if ($this->getImageFile())
      $imageFileName = $this->getImageFile();
    else
      $imageFileName = $this->getProduct()->getData($destSubdir);

    if ($imageFileName == 'no_selection') {
      $placeholder = Mage::getModel('catalog/product_image')
                       ->setDestinationSubdir($destSubdir)
                       ->setBaseFile(null)
                       ->getBaseFile();

      $imageFileName = '/' . basename($placeholder);
    }

    $dimensions = $this->_getModel()->getWidth()
                  . 'x'
                  . $this->_getModel()->getHeight();

    if ($dimensions == 'x')
      $dimensions = 'full';

    $helper = Mage::helper('mventory_tm');
    $website = $helper->getWebsite($this->getProduct());

    $prefix = $helper
                ->getConfig(MVentory_Tm_Model_Observer::XML_PATH_CDN_PREFIX,
                            $website);

    return Mage::getBaseUrl('media')
           . $prefix
           . '/'
           . $dimensions
           . $imageFileName;
  }
}
