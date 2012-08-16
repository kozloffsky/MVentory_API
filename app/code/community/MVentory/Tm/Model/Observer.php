<?php

class MVentory_Tm_Model_Observer {

  private $supportedImageTypes = array(
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_JPEG => 'jpeg',
    IMAGETYPE_PNG => 'png'
  );

  public function productSaveAfter ($observer) {
    $event = $observer->getEvent();

    $product = Mage::getModel('catalog/product')
                 ->load($event->getProduct()->getId());

    $stock = Mage::getModel('cataloginventory/stock_item')
               ->loadByProduct($product);

    $mventoryCategoryId = Mage::app()
                            ->getRequest()
                            ->getParam('mventory_category');

    $category = Mage::getModel('catalog/category')
                  ->getCollection()
                  ->addAttributeToSelect('mventory_tm_category')
                  ->addFieldToFilter('entity_id',
                                     array('in' => $product->getCategoryIds()))
                  ->getFirstItem();

    $category->setMventoryTmCategory($mventoryCategoryId);
    $category->save();

    if ($stock->getManageStock() && $stock->getQty() == 0
        && $product->getMventoryTmId()) {

      $connector = Mage::getModel('mventory_tm/connector');
      $result = $connector->remove($product);

      if ($result === true) {
        Mage::getSingleton('adminhtml/session')
          ->addSuccess(Mage::helper('catalog')->__('Removed!'));

        $product->setMventoryTmId(0);
        $product->save();
      } else
        Mage::getSingleton('adminhtml/session')
          ->addError($result);
    }
  }

  public function scheduledCheck ($schedule) {
    $collection = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addFieldToFilter('mventory_tm_id', array('neq' => ''));

    foreach ($collection as $product) {
      $connector = Mage::getModel('mventory_tm/connector');
      $result = $connector->check($product);

      if ($result == 1 || $result == 2) {
        if ($result == 2) {
          $stock = Mage::getModel('cataloginventory/stock_item')
                     ->loadByProduct($product);

          if ($stock->getManageStock() && $stock->getQty()) {
            $stockData = $stock->getData();
            $stockData['qty'] -= 1;
            $product->setStockData($stockData);
          }
        }

        $product->setMventoryTmId(0);
        $product->save();
      }
    }
  }

  public function mediaSaveBefore($observer) {
    if (!function_exists('exif_read_data'))
      return;

    $value = $observer->getImages();

    $config = Mage::getSingleton('catalog/product_media_config');

    foreach ($value['images'] as $image) {
      if (isset($image['value_id']))
        continue;

      $imagePath = $config->getMediaPath($image['file']);

      $exif = exif_read_data($imagePath);

      if ($exif === false)
        continue;

      if (isset($exif['FileType']))
        $imageType = $exif['FileType'];
      else
        continue;

      if (!array_key_exists($imageType, $this->supportedImageTypes))
        continue;

      if (isset($exif['Orientation']))
        $orientation = $exif['Orientation'];
      elseif (isset($exif['IFD0']['Orientation']))
        $orientation = $exif['IFD0']['Orientation'];
      else
        continue;

      if (!in_array($orientation, array(3, 6, 8)))
        continue;

      $imageTypeName = $this->supportedImageTypes[$imageType];

      $imageCreate = 'imagecreatefrom' . $imageTypeName;

      $imageData = $imageCreate($imagePath);

      switch($orientation) {
        case 3:
          $imageData = imagerotate($imageData, 180, 0);

          break;
        case 6:
          $imageData = imagerotate($imageData, -90, 0);

          break;
        case 8:
          $imageData = imagerotate($imageData, 90, 0);

          break;
      }

      $imageSave = 'image' . $imageTypeName;

      $imageSave($imageData, $imagePath, 100);
    }
  }

  public function productInit ($observer) {
    $product = $observer->getProduct();

    $categories = $product->getCategoryIds();

    if (!count($categories))
      return;

    $categoryId = $categories[0];

    $lastId = Mage::getSingleton('catalog/session')->getLastVisitedCategoryId();

    $category = $product->getCategory();

    // Return if last visited vategory was not used
    if ($category && $category->getId() != $lastId)
      return;

    // Return if categories are same, nothing to change
    if ($lastId == $categoryId)
      return;

    if (!$product->canBeShowInCategory($categoryId))
      return;

    $category = Mage::getModel('catalog/category')->load($categoryId);

    $product->setCategory($category);

    Mage::unregister('current_category');
    Mage::register('current_category', $category);
  }

  public function addProductNameRebuildMassaction ($observer) {
    $block = $observer->getBlock();

    $route = 'mventory_tm/catalog_product/massNameRebuild';

    $label = Mage::helper('mventory_tm')->__('Rebuild product name');
    $url = $block->getUrl($route, array('_current' => true));

    $block
      ->getMassactionBlock()
      ->addItem('namerebuild', compact('label', 'url'));
  }
}
