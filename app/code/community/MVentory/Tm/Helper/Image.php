<?php

class MVentory_Tm_Helper_Image extends Mage_Catalog_Helper_Image {

  /**
   * Initialize Helper to work with Image
   *
   * @param Mage_Catalog_Model_Product $product
   * @param string $attributeName
   * @param mixed $imageFile
   * @return Mage_Catalog_Helper_Image
  */
  public function init (Mage_Catalog_Model_Product $product, $attributeName,
                        $imageFile = null) {

    $this->_reset();

    $this->_setModel(new Varien_Object());

    $this->_getModel()->setDestinationSubdir($attributeName);
    $this->setProduct($product);

    $path = 'design/watermark/' . $attributeName . '_';

    $this->watermark(
      Mage::getStoreConfig($path . 'image'),
      Mage::getStoreConfig($path . 'position'),
      Mage::getStoreConfig($path . 'size'),
      Mage::getStoreConfig($path . 'imageOpacity')
    );

    if ($imageFile)
      $this->setImageFile($imageFile);

    return $this;
  }

  /**
   * Retrieve original image height
   *
   * @return int|null
   */
  public function getOriginalHeight () {
    return null;
  }

  /**
   * Retrieve original image width
   *
   * @return int|null
   */
  public function getOriginalWidth () {
    return null;
  }

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

    if (!$imageFileName = $this->getImageFile())
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

    return $helper->getBaseMediaUrl($website)
           . $prefix
           . '/'
           . $dimensions
           . $imageFileName;
  }
}
