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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Catalog product media api
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Model_Product_Attribute_Media_Api
  extends Mage_Catalog_Model_Product_Attribute_Media_Api {

  public function createAndReturnInfo ($productId, $data, $storeId = null,
                                       $identifierType = null) {

    if (!isset($data['file']))
      $this->_fault('data_invalid',
                    Mage::helper('catalog')->__('The image is not specified.'));

    $file = &$data['file'];

    if (!isset($file['name'], $file['mime'], $file['content']))
      $this->_fault('data_invalid',
                    Mage::helper('catalog')->__('The image is not specified.'));


    if (!isset($this->_mimeTypes[$file['mime']]))
      $this->_fault('data_invalid',
                    Mage::helper('catalog')->__('Invalid image type.'));

    if (!$file['content'] = @base64_decode($file['content'], true))
      $this->_fault(
        'data_invalid',
        Mage::helper('catalog')
          ->__('The image contents is not valid base64 data.')
      );

    $file['name'] = strtolower(trim($file['name']));

    //$storeId = Mage::helper('mventory_tm')->getCurrentStoreId($storeId);

    //Temp solution, apply image settings globally
    $storeId = null;

    $images = $this->items($productId, $storeId, $identifierType);

    $name = $file['name'] . '.' . $this->_mimeTypes[$file['mime']];

    foreach ($images as $image)
      //Throw of first 5 symbols becau se 'file'
      //has following format '/i/m/image.ext' (dispretion path)
      if (strtolower(substr($image['file'], 5)) == $name)
        return Mage::getModel('mventory_tm/product_api')
                 ->fullInfo($productId, $identifierType);

    $hasMainImage = false;
    $hasSmallImage = false;
    $hasThumbnail = false;

    if (isset($image['types']))
      foreach ($images as $image) {
        if (in_array('image', $image['types']))
          $hasMainImage = true;

        if (in_array('small_image', $image['types']))
          $hasSmallImage = true;

        if (in_array('thumbnail', $image['types']))
          $hasThumbnail = true;
      }

    if (!$hasMainImage)
      $data['types'][] = 'image';

    if (!$hasSmallImage)
      $data['types'][] = 'small_image';

    if (!$hasThumbnail)
      $data['types'][] = 'thumbnail';

    //We don't use exclude feature
    $data['exclude'] = 0;

    $file['content'] = base64_encode(
      $this->_fixOrientation($name, $file['content'])
    );

    $this->create($productId, $data, $storeId, $identifierType);

    $productApi = Mage::getModel('mventory_tm/product_api');

    return $productApi->fullInfo($productId, $identifierType);
  }

  public function remove_ ($productId, $file, $identifierType = null) {
    $image = $this->info($productId, $file, null, $identifierType);

    $this->remove($productId, $file, $identifierType);

    $productApi = Mage::getModel('mventory_tm/product_api');

    if (!in_array('image', $image['types']))
      return $productApi->fullInfo($productId, $identifierType);

    $images = $this->items($productId, null, $identifierType);

    if (!$images)
      return $productApi->fullInfo($productId, $identifierType);

    $this->update(
      $productId,
      $images[0]['file'],
      array(
        'types' => array('image', 'small_image', 'thumbnail'),
        'exclude' => 0
      ),
      null,
      $identifierType
    );

    return $productApi->fullInfo($productId, $identifierType);
  }

  /**
   * Retrieve product
   *
   * The function is redefined to check if api user has access to the product
   *
   * @param int|string $productId
   * @param string|int $store
   * @param  string $identifierType
   * @return Mage_Catalog_Model_Product
   */
  protected function _initProduct($productId, $store = null,
                                  $identifierType = null) {

    $helper = Mage::helper('mventory_tm/product');

    $productId = $helper->getProductId($productId, $identifierType);

    if (!($productId && $helper->hasApiUserAccess($productId, 'id')))
      $this->_fault('product_not_exists');

    $product = Mage::getModel('catalog/product')
                 ->setStoreId(Mage::app()->getStore($store)->getId())
                 ->load($productId);

    if (!$product->getId())
      $this->_fault('product_not_exists');

    return $product;
  }

  private function _fixOrientation ($name, &$data) {
    $io = new Varien_Io_File();

    $tmp  = Mage::getBaseDir('var')
            . DS
            . 'api'
            . DS
            . $this->_getSession()->getSessionId();

    try {
      $io->checkAndCreateFolder($tmp);
      $io->open(array('path' => $tmp));
      $io->write($name, $data, 0666);

      $path = $tmp . DS . $name;

      if (Mage::helper('mventory_tm/image')->fixOrientation($path))
        $data = $io->read($path);
    } catch (Exception $e) {}

    $io->rmdir($tmp, true);

    return $data;
  }
}
