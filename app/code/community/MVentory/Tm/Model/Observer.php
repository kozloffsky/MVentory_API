<?php

class MVentory_Tm_Model_Observer {

  private $supportedImageTypes = array(
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_JPEG => 'jpeg',
    IMAGETYPE_PNG => 'png'
  );

  public function removeListingFromTm ($observer) {
    $order = $observer
               ->getEvent()
               ->getOrder();

    $items = $order->getAllItems();

    foreach ($items as $item) {
      $productId = (int) $item->getProductId();

      $storeId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

      //We can use default store ID because the attribute is global
      $listingId
        = Mage::getResourceModel('catalog/product')
            ->getAttributeRawValue($productId, 'tm_listing_id', $storeId);

      if (!$listingId)
        continue;

      $stockItem = Mage::getModel('cataloginventory/stock_item')
                     ->loadByProduct($productId);

      if (!($stockItem->getManageStock() && $stockItem->getQty() == 0))
        continue;

      $connector = Mage::getModel('mventory_tm/connector');

      //!!!FIXME: temporarely load the whole product object, but need only
      //a couple of attributes.
      $product = Mage::getModel('catalog/product')->load($productId);

      //Try to increase price of selling listing on TM if it's allowed,
      //otherwise try to withdraw listing
      if ($product->getTmAvoidWithdrawal()
          /*!!! && !$result = $connector->update($product, $newPrice)*/)
        $result = $connector->remove($product);

      if ($result !== true) {
        //Send email with error message to website's general contact address

        $helper = Mage::helper('mventory_tm/product');

        $website = $helper->getWebsite($product);

        $productUrl = $helper->getUrl($product);
        $listingId = Mage::helper('mventory_tm/tm')->getListingUrl($product);

        $subject = 'TM: error on removing listing';
        $message = 'Error on increasing price or withdrawing listing ('
                   . $listingId
                   . ') linked to product ('
                   . $productUrl
                   . ')';

        $helper->sendEmail($subject, $message, $website);

        continue;
      }

      $productId = array($productId);
      $attribute = array('tm_listing_id' => 0);

      Mage::getResourceSingleton('catalog/product_action')
        ->updateAttributes($productId, $attribute, $storeId);
    }
  }

  public function populateAttributes ($observer) {
    $event = $observer->getEvent();

    //Populate product attributes
    Mage::getSingleton('mventory_tm/product_action')
      ->populateAttributes(array($event->getProduct()), null, false);
  }

  public function saveProductCreateDate ($observer) {
    $product = $observer
                 ->getEvent()
                 ->getProduct();

    if ($product->getId())
      return;

    $product->setData('mv_created_date', time());
  }

  public function saveApiUserId ($observer) {
    $session = Mage::getSingleton('api/session');

    if (!$session->getSessionId())
      return;

    $product = $observer
                 ->getEvent()
                 ->getProduct();

    if ($product->getId())
      return;

    $userId = $session
                ->getUser()
                ->getId();

    $product->setData('mv_created_userid', $userId);
  }

  public function sync ($schedule) {
    //Get cron job config
    $jobsRoot = Mage::getConfig()->getNode('default/crontab/jobs');
    $jobConfig = $jobsRoot->{$schedule->getJobCode()};

    //Get website from the job config
    $website = Mage::app()->getWebsite((string) $jobConfig->website);

    //Get website's default store
    $store = $website->getDefaultStore();

    //Get default customer ID for TM sells (TM buyer)
    $path = MVentory_Tm_Model_Connector::BUYER_PATH;
    $customerId = Mage::helper('mventory_tm')->getConfig($path, $website);

    $customer = Mage::getModel('customer/customer')->load($customerId);

    //Load TM accounts which are used in specified website
    $accounts = Mage::helper('mventory_tm/tm')->getAccounts($website);

    //Add tempararely account with empty ID, it needs to load products that
    //were submitted before implementing TM multiple accounts because
    //tm_account_id attribute is empty in such products. Also it triggers code
    //to use default TM account (first in the list of accounts)
    $accounts[''] = true;

    foreach ($accounts as $accountId => $accountData) {
      $products = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('tm_relist')
                    ->addAttributeToSelect('tm_account_id')
                    ->addAttributeToSelect('price')
                    ->addFieldToFilter('tm_listing_id', array('neq' => ''))
                    ->addFieldToFilter('tm_account_id',
                                       array('eq' => $accountId))
                    ->addStoreFilter($store);

      //!!!Commented to allow loading out of stock products
      //If customer exists and loaded add price data to the product collection
      //filtered by customer's group ID
      //if ($customer->getId())
      //  $products->addPriceData($customer->getGroupId());

      //Continue if there're products assigned to current TM account
      if (!count($products))
        continue;

      $connector = Mage::getModel('mventory_tm/connector');

      $connector->setWebsiteId($website->getId());
      $connector->setAccountId($accountId);

      $result = $connector->massCheck($products);

      if (!$result)
        return;

      foreach ($products as $product) {
        if ($product->getIsSelling())
          continue;

        $result = $connector->check($product);

        if (!$result)
          continue;

        $newListingId = $product->getTmListingId();

        if ($result == 1)
          if ($product->getTmRelist()
              && $product->getStockItem()->getIsInStock())
            $newListingId = $connector->relist($product);
          else
            $newListingId = 0;

        if ($result == 2) {
          $sku = $product->getSku();
          $price = $product->getPrice();
          $qty = 1;

          //API function for creating order requires curren store to be set
          Mage::app()->setCurrentStore($store);

          //Set global flag to enable our dummy shipping method
          Mage::register('tm_allow_dummyshipping', true);

          //Make order for the product
          Mage::getModel('mventory_tm/cart_api')
            ->createOrderForProduct($sku, $price, $qty, $customerId);

          $newListingId = 0;
        }

        $product
          ->setTmListingId($newListingId)
          ->save();
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

    //Return if last visited vategory was not used
    if ($category && $category->getId() != $lastId)
      return;

    //Return if categories are same, nothing to change
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

    $session = Mage::getSingleton('core/session');

    $cookieValue = Mage::getModel('core/cookie')->get($code);
    $sessionValue = $session->getData($code);

    $identifier = 'desktop_footer_links_';

    //Check current site version
    if ($cookieValue == 'mobile'
        || ($cookieValue === false && $sessionValue == 'mobile')) {
      $session->unsetData($code);

      $identifier = 'mobile_footer_links_';
    }

    $identifier .= $storeId;

    //Append cms block to the footer
    $block = $layout
               ->createBlock('cms/block')
               ->setBlockId($identifier);

    //Check if footer block exists. It doesn't exist in AJAX requests
    if ($footer = $layout->getBlock('footer'))
      $footer->append($block);
  }
}
