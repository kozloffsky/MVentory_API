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

    //$mventoryCategoryId = Mage::app()
    //                        ->getRequest()
    //                        ->getParam('mventory_category');

    //$category = Mage::getModel('catalog/category')
    //              ->getCollection()
    //              ->addAttributeToSelect('mventory_tm_category')
    //              ->addFieldToFilter('entity_id',
    //                                 array('in' => $product->getCategoryIds()))
    //              ->getFirstItem();

    //$category->setMventoryTmCategory($mventoryCategoryId);
    //$category->save();

    if ($stock->getManageStock() && $stock->getQty() == 0
        && $product->getTmListingId()) {

      $connector = Mage::getModel('mventory_tm/connector');
      $result = $connector->remove($product);

      if ($result === true) {
        Mage::getSingleton('adminhtml/session')
          ->addSuccess(Mage::helper('catalog')->__('Removed!'));

        $product->setTmListingId(0);
        $product->save();
      } else
        Mage::getSingleton('adminhtml/session')
          ->addError($result);
    }
  }

  public function productSaveBefore ($observer) {
    $event = $observer->getEvent();

    // populate product attributes
    Mage::getSingleton('mventory_tm/product_action')
      ->populateAttributes(array($event->getProduct()), null, false);
  }

  public function sync ($schedule) {
    //Get cron job config
    $jobsRoot = Mage::getConfig()->getNode('default/crontab/jobs');
    $jobConfig = $jobsRoot->{$schedule->getJobCode()};

    //Get website from the job config
    $website = Mage::app()->getWebsite((string) $jobConfig->website);

    //Get default customer ID for TM sells (TM buyer)
    $path = MVentory_Tm_Model_Connector::BUYER_PATH;
    $customerId = Mage::helper('mventory_tm')->getConfig($path, $website);

    $customer = Mage::getModel('customer/customer')->load($customerId);

    $products = Mage::getModel('catalog/product')
                  ->getCollection()
                  ->addAttributeToSelect('tm_relist')
                  ->addAttributeToSelect('price')
                  ->addFieldToFilter('tm_listing_id', array('neq' => ''))
                  ->addWebsiteFilter($website)
                  ->addWebsiteNamesToResult();

    //If customer exists and loaded add price data to the product collection
    //filtered by customer's group ID
    if ($customer->getId())
      $products->addPriceData($customer->getGroupId());

    $connector = Mage::getModel('mventory_tm/connector');

    $connector->setWebsiteId($website->getId());

    $result = $connector->massCheck($products);

    if (!$result)
      return;

    foreach ($products as $product) {
      if ($product->getIsSelling())
        continue;

      $result = $connector->check($product);

      if (!$result)
        continue;

      if ($result == 1 && $product->getTmRelist())
        $product->setTmListingId($connector->relist($product));

      if ($result == 2) {
        $sku = $product->getSku();
        $price = $product->getPrice();
        $qty = 1;

        //API function for creating order requires curren store to be set
        Mage::app()->setCurrentStore($website->getDefaultStore());

        //Set global flag to enable our dummy shipping method
        Mage::register('tm_allow_dummyshipping', true);

        //Make order for the product
        Mage::getModel('mventory_tm/cart_api')
          ->createOrderForProduct($sku, $price, $qty, $customerId);

        $product->setTmListingId(0);
      }

      $product->save();
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

  /**
   * Add action "Populate product attributes" to admin product manage grid
   */
  public function addProductAttributesPopulateMassaction ($observer) {
    $block = $observer->getBlock();

    $route = 'mventory_tm/catalog_product/massAttributesPopulate';

    $label = Mage::helper('mventory_tm')->__('Populate product attributes');
    $url = $block->getUrl($route, array('_current' => true));

    $block
      ->getMassactionBlock()
      ->addItem('attributespopulate', compact('label', 'url'));
  }

  public function addCategoryTab ($observer) {
    $tabs = $observer->getTabs();

    $label = Mage::helper('mventory_tm')->__('TM Categories');

    $content = $tabs
                 ->getLayout()
                 ->createBlock('mventory_tm/catalog_category_tab_tm',
                               'category.tm')
                 ->toHtml();

    $tabs
      ->addTab('tm', compact('label', 'content'));
  }

  public function addTabToProduct ($observer) {
    $block =  $observer->getEvent()->getBlock();

    if (!$block instanceof Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs)
      return;

    if (!($block->getProduct()->getAttributeSetId()
          || $block->getRequest()->getParam('set', null)))
      return;

    $label = Mage::helper('mventory_tm')->__('mVentory');
    $content = $block
                 ->getLayout()
                 ->createBlock('mventory_tm/catalog_product_edit_tab_tm')
                 ->toHtml();

    $block->addTab('tm', compact('label', 'content'));
  }

  public function addSiteSwitcher ($observer) {
    $layout = $observer->getEvent()->getLayout();

    $storeId = Mage::app()->getStore()->getId();
    $code = 'site_version_' . $storeId;

    // check current site version
    if (Mage::getModel('core/cookie')->get($code) == 'mobile' ||
        (Mage::getModel('core/cookie')->get($code) === false &&
         Mage::getSingleton('core/session')->getData($code) == 'mobile')) {
      Mage::getSingleton('core/session')->unsetData('site_version_' . $storeId);
      $identifier = 'mobile_footer_links_' . $storeId;
    } else {
      $identifier = 'desktop_footer_links_' . $storeId;
    }

    // append cms block to the footer
    $block = $layout
               ->createBlock('cms/block')
               ->setBlockId($identifier);
    $layout->getBlock('footer')->append($block);
  }
}
