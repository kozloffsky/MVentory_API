<?php

/**
 * Image editing helper
 *
 * @author MVentory <???@mventory.com>
 */

class MVentory_Tm_Helper_Imageediting extends Mage_Core_Helper_Abstract {

  public function rotate ($file, $angle) {
    $media = Mage::getModel('catalog/product_media_config');

    if (!file_exists($media->getMediaPath($file)))
      return;

    $image = Mage::getModel('catalog/product_image');

    $image
      ->setBaseFile($file)
      ->setNewFile($image->getBaseFile())
      ->setQuality(100)
      ->setKeepFrame(false)
      ->rotate($angle)
      ->saveFile();

    return true;
  }
}
