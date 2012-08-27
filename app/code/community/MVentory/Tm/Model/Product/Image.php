<?php

/**
 * Catalog product image model
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Model_Product_Image extends Mage_Catalog_Model_Product_Image {

  protected function _fileExists ($filename) {
    return file_exists($filename)
             ? filemtime($filename) >= filemtime($this->getBaseFile())
               : Mage::helper('core/file_storage_database')
                   ->saveFileToFilesystem($filename);
  }

  public function setNewFile ($newFile) {
    $this->_newFile = $newFile;

    return $this;
  }
}
