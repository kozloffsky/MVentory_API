<?php

/**
 * Catalog product image model
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Model_Product_Image extends Mage_Catalog_Model_Product_Image {

  /**
   * First check this file on FS or DB. If it doesn't then download it from S3
   *
   * @param string $filename
   *
   * @return bool
   */
  protected function _fileExists ($filename) {
    return parent::_fileExists($filename)
           || Mage::helper('mventory_tm/s3')->download($filename);
  }
}
