<?php

class MVentory_Tm_Model_Observer {

  const XML_PATH_CDN_ACCESS_KEY = 'mventory_tm/cdn/access_key';
  const XML_PATH_CDN_SECRET_KEY = 'mventory_tm/cdn/secret_key';
  const XML_PATH_CDN_BUCKET = 'mventory_tm/cdn/bucket';
  const XML_PATH_CDN_PREFIX = 'mventory_tm/cdn/prefix';
  const XML_PATH_CDN_DIMENSIONS = 'mventory_tm/cdn/resizing_dimensions';

  const XML_PATH_CRON_INTERVAL = 'mventory_tm/settings/cron';

  const SYNC_START_HOUR = 7;
  const SYNC_END_HOUR = 23;
  const SYNC_PERIOD_DAYS = 7;

  private $supportedImageTypes = array(
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_JPEG => 'jpeg',
    IMAGETYPE_PNG => 'png'
  );

  public function removeListingFromTm ($observer) {
    if (Mage::registry('tm_disable_withdrawal'))
      return;

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
      $doRemoveFromTM = false;
      if ($product->getTmAvoidWithdrawal())
      {
        $addTMFees = false;

        $accountId = $product->getAccountId();

        if ($accountId) {
          $website = Mage::helper('mventory_tm')->getWebsite($product);
          $accounts = Mage::helper('mventory_tm/tm')->getAccounts($website);

          $addTMFees = $accounts[$accountId]['add_fees'];
        }

        $newPrice = $product->getPrice()*5;

        if ($addTMFees)
        {
          $tmTmHelper = Mage::helper('mventory_tm/tm');
          $newPrice = $tmTmHelper->addFees($newPrice);
        }

        if (is_int($connector->update($product, array('StartPrice' => $newPrice), null)))
        {
          $result = true;
        }
        else
        {
          $doRemoveFromTM = true;
        }
      }
      else
      {
        $doRemoveFromTM = true;
      }

      if ($doRemoveFromTM)
      {
        $result = $connector->remove($product);
      }

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

    $helper = Mage::helper('mventory_tm/product');

    //Get time with Magento timezone offset
    $now = localtime(Mage::getModel('core/date')->timestamp(time()), true);

    //Check if we are in allowed hours
    $allowSubmit = $now['tm_hour'] >= self::SYNC_START_HOUR
                   && $now['tm_hour'] < self::SYNC_END_HOUR;

    if ($allowSubmit) {
      $cronInterval
        = (int) $helper->getConfig(self::XML_PATH_CRON_INTERVAL, $website);

      //Calculate number of sync script runs during period
      $runsNumber = $cronInterval
                      ? (self::SYNC_END_HOUR - self::SYNC_START_HOUR) * 60
                          * self::SYNC_PERIOD_DAYS / $cronInterval
                        : 0;
    }

    foreach ($accounts as $accountId => $accountData) {
      $products = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('tm_relist')
                    ->addAttributeToSelect('price')
                    ->addFieldToFilter('tm_listing_id', array('neq' => ''))
                    ->addStoreFilter($store);

      //Add filtering by account's ID if it's not empty
      //Workaround for products which were submitted before implementing
      //TM multiple accounts because value of 'tm_account_id' attribute is null
      //in such products
      if ($accountId)
        $products->addFieldToFilter('tm_account_id', array('eq' => $accountId));

      //!!!Commented to allow loading out of stock products
      //If customer exists and loaded add price data to the product collection
      //filtered by customer's group ID
      //if ($customer->getId())
      //  $products->addPriceData($customer->getGroupId());

      $connector = Mage::getModel('mventory_tm/connector');

      $connector->setWebsiteId($website->getId());
      $connector->setAccountId($accountId);

      //Check status of listings if there're products assigned
      //to current TM account
      if ($numberOfListings = count($products)) {
        $result = $connector->massCheck($products);

        if (!$result)
          continue;
      }

      foreach ($products as $product) {
        if ($product->getIsSelling())
          continue;

        $result = $connector->check($product);

        if (!$result || $result == 3)
          continue;

        --$numberOfListings;

        if ($result == 2) {
          $sku = $product->getSku();
          $price = $product->getPrice();
          $qty = 1;

          //API function for creating order requires curren store to be set
          Mage::app()->setCurrentStore($store);

          //Set global flag to prevent removing product from TM during order
          //creating. No need to remove it because it was bought on TM.
          //The flag is used in removeListingFromTm() method
          Mage::register('tm_disable_withdrawal', true, true);

          //Set global flag to enable our dummy shipping method
          Mage::register('tm_allow_dummyshipping', true, true);

          //Make order for the product
          Mage::getModel('mventory_tm/cart_api')
            ->createOrderForProduct($sku, $price, $qty, $customerId);
        }

        $helper->setListingId(0, $product->getId());
      }

      if (!($allowSubmit && $runsNumber))
        continue;

      $freeSlots = $accountData['max_listings'] - $numberOfListings;

      if ($freeSlots <= 0)
        continue;

      $enabled = Mage_Catalog_Model_Product_Status::STATUS_ENABLED;

      $products = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addFieldToFilter('tm_listing_id', '')
                    ->addFieldToFilter('tm_relist', '1')
                    ->addFieldToFilter('tm_account_id', $accountId)
                    ->addFieldToFilter('status', $enabled)
                    ->addStoreFilter($store);


      Mage::getSingleton('cataloginventory/stock')
        ->addInStockFilterToCollection($products);

      if (!$poolSize = count($products))
        continue;

      //Calculate avaiable slots for current run of the sync script
      $freeSlots = ($poolSize < $freeSlots)
                     ? round($poolSize / $runsNumber)
                       : round($freeSlots / $runsNumber);

      //One product should be uploaded at least
      if ($freeSlots < 1)
        $freeSlots = 1;

      $products = $products->getItems();

      $ids = $freeSlots >= $poolSize
               ? array_keys($products)
                 : array_rand($products, $freeSlots);

      //array_rand returns key in case $freeSlots = 1,
      //wrap it with array
      if (!is_array($ids))
        $ids = array($ids);

      foreach ($ids as $id) {
        $product = Mage::getModel('catalog/product')
                     ->setStoreId($store->getId())
                     ->load($id);

        if (!($product->getId()
              && ($tmCategory = $product->getTmCategory()) > 0))
          continue;

        $tmData = array(
          'account_id' => $accountId,
          'category' => $tmCategory,
          'add_fees' => $product->getTmAddFees(),
          'allow_buy_now' => $product->getTmAllowBuyNow(),
          'shipping_type' => $product->getTmShippingType(),
          'relist' => $product->getTmRelist()
        );

        $listingId
          = $connector->send($product, $tmCategory, $tmData);

        if (is_int($listingId))
          $product
            ->setTmListingId($listingId)
            ->save();
      }
    }
  }

  public function mediaSaveBefore($observer) {
    if (!function_exists('exif_read_data'))
      return;

    //There's nothing to process because we're using images
    //from original product in duplicate
    if ($observer->getProduct()->getIsDuplicate())
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

  public function uploadImageToCdn ($observer) {
    $product = $observer->getEvent()->getProduct();

    //There's nothing to process because we're using images
    //from original product in duplicate
    if ($product->getIsDuplicate())
      return;

    $images = $observer->getEvent()->getImages();

    $helper = Mage::helper('mventory_tm');

    $website = $helper->getWebsite($product);

    //Get settings for S3
    $accessKey = $helper->getConfig(self::XML_PATH_CDN_ACCESS_KEY, $website);
    $secretKey = $helper->getConfig(self::XML_PATH_CDN_SECRET_KEY, $website);
    $bucket = $helper->getConfig(self::XML_PATH_CDN_BUCKET, $website);
    $prefix = $helper->getConfig(self::XML_PATH_CDN_PREFIX, $website);
    $dimensions = $helper->getConfig(self::XML_PATH_CDN_DIMENSIONS, $website);

    //Return if S3 settings are empty
    if (!($accessKey && $secretKey && $bucket && $prefix))
      return;

    //Build prefix for all files on S3
    $cdnPrefix = $bucket . '/' . $prefix . '/';

    //Parse dimension. Split string to pairs of width and height
    $dimensions = str_replace(', ', ',', $dimensions);
    $dimensions = explode(',', $dimensions);

    //Prepare meta data for uploading. All uploaded images are public
    $meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                    => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

    $config = Mage::getSingleton('catalog/product_media_config');

    $s3 = new Zend_Service_Amazon_S3($accessKey, $secretKey);

    foreach ($images['images'] as &$image) {
      //Process new images only
      if (isset($image['value_id']))
        continue;

      //Get name of the image and create its key on S3
      $fileName = $image['file'];
      $cdnPath = $cdnPrefix . 'full' . $fileName;

      //Full path to uploaded image
      $file = $config->getMediaPath($fileName);

      //Check if object with the key exists
      if ($s3->isObjectAvailable($cdnPath)) {
        $position = strrpos($fileName, '.');

        //Split file name and extension
        $name = substr($fileName, 0, $position);
        $ext = substr($fileName, $position);

        //Search key
        $_key = $prefix .'/full' . $name . '_';

        //Get all objects which is started with the search key
        $keys = $s3->getObjectsByBucket($bucket, array('prefix' => $_key));

        $index = 1;

        //If there're objects which names begin with the search key then...
        if (count($keys)) {
          $extLength = strlen($ext);

          $_keys = array();

          //... store object names without extension as indeces of the array
          //for fast searching
          foreach ($keys as $key)
            $_keys[substr($key, 0, -$extLength)] = true;

          //Find next unused object name
          while(isset($_keys[$_key . $index]))
            ++$index;

          unset($_keys);
        }

        //Build new name and path with selected index
        $fileName = $name . '_' . $index . $ext;
        $cdnPath = $cdnPrefix . 'full' . $fileName;

        //Get new name for uploaded file
        $_file = $config->getMediaPath($fileName);

        //Rename file uploaded to Magento
        rename($file, $_file);

        //Update values of media attribute in the product after renaming
        //uploaded image if the image was marked as 'image', 'small_image'
        //or 'thumbnail' in the product
        foreach ($product->getMediaAttributes() as $mediaAttribute) {
          $code = $mediaAttribute->getAttributeCode();

          if ($product->getData($code) == $image['file'])
            $product->setData($code, $fileName);
        }

        //Save its new name in Magento
        $image['file'] = $fileName;
        $file = $_file;

        unset($_file);
      }

      //Upload original image
      if (!$s3->putFile($file, $cdnPath, $meta)) {
        $msg = 'Can\'t upload original image (' . $file . ') to S3 with '
               . $cdnPath . ' key';

        throw new Mage_Core_Exception($msg);
      }

      //Go to next newly uploaded image if image dimensions for resizing
      //were not set
      if (!count($dimensions))
        continue;

      //For every dimension...
      foreach ($dimensions as $dimension) {
        //... resize original image and get path to resized image
        $newFile = Mage::getModel('catalog/product_image')
                     ->setSize($dimension)
                     ->setBaseFile($fileName)
                     ->setKeepFrame(false)
                     ->setConstrainOnly(true)
                     ->resize()
                     ->saveFile()
                     ->getNewFile();

        //Build S3 path for the resized image
        $newCdnPath = $cdnPrefix . $dimension . $fileName;

        //Upload resized images
        if (!$s3->putFile($newFile, $newCdnPath, $meta)) {
          $msg = 'Can\'t upload resized (' . $dimension . ') image (' . $file
                 . ') to S3 with ' . $cdnPath . ' key';

          throw new Mage_Core_Exception($msg);
        }
      }
    }
  }

  public function restoreNewAccountInConfig ($observer) {
    $configData = $observer->getObject();

    if ($configData->getSection() != 'mventory_tm')
      return;

    $groups = $configData->getGroups();

    $accounts = array();

    foreach ($groups as $id => $group)
      if (strpos($id, 'account_', 0) === 0)
        if ($group['fields']['name']['value']
            && $group['fields']['key']['value']
            && $group['fields']['secret']['value'])
          $accounts[$id] = $group['fields']['name']['value'];
        else
          unset($groups[$id]);

    $configData->setGroups($groups);

    Mage::register('tm_config_accounts', $accounts);
  }

  public function addAccountsToConfig ($observer) {
    if (Mage::app()->getRequest()->getParam('section') != 'mventory_tm')
      return;

    $settings = $observer
                  ->getConfig()
                  ->getNode('sections')
                  ->mventory_tm
                  ->groups
                  ->settings;

    $template = $settings
                  ->account_template
                  ->asArray();

    if (!$accounts = Mage::registry('tm_config_accounts')) {
      $groups = Mage::getSingleton('adminhtml/config_data')
                  ->getConfigDataValue('mventory_tm');

      $accounts = array();

      foreach ($groups->children() as $id => $account)
        if (strpos($id, 'account_', 0) === 0)
          $accounts[$id] = (string) $account->name;

      unset($id);
      unset($account);

      $accounts['account_' . str_replace('.', '_', microtime(true))]
        = '+ Add account';
    }

    $noAccounts = count($accounts) == 1;

    $position = 0;

    foreach ($accounts as $id => $account) {
      $group = $settings
                 ->fields
                 ->addChild($id);

      $group->addAttribute('type', 'group');
      $group->addChild('frontend_model',
                       'mventory_tm/system_config_form_fieldset_account');
      $group->addChild('label', $account);
      $group->addChild('show_in_default', 0);
      $group->addChild('show_in_website', 1);
      $group->addChild('show_in_store', 0);
      $group->addChild('expanded', (int) $noAccounts);
      $group->addChild('sort_order', $position++);

      $fields = $group->addChild('fields');

      foreach ($template as $name => $field) {
        $node = $fields->addChild($name);

        if (isset($field['@'])) {
          foreach ($field['@'] as $key => $value)
            $node->addAttribute($key, $value);

          unset($field['@']);

          unset($key);
          unset($value);
        }

        foreach ($field as $key => $value)
          $node->addChild($key, $value);

        unset($key);
        unset($value);
      }
    }
  }

  public function checkAccessToWebsite ($observer) {
    $apiUser = $observer->getModel();

    $helper = Mage::helper('mventory_tm');

    $website = $helper->getApiUserWebsite($apiUser);

    if (!$website) {
      $apiUser->setId(null);

      return;
    }

    //Allow access to all websites for customers assigned to Admin website
    if (($websiteId = $website->getId()) == 0)
      return;

    if ($helper->getCurrentWebsite()->getId() != $websiteId)
      $apiUser->setId(null);
  }

  public function hideProductsWithoutSmallImages ($observer) {
    $observer
      ->getCollection()
      ->addAttributeToFilter('small_image', array('neq' => 'no_selection'));
  }

  /**
   * Unset is_duplicate flag to prevent coping image files
   * in Mage_Catalog_Model_Product_Attribute_Backend_Media::beforeSave() method
   *
   * @param Varien_Event_Observer $observer Event observer
   */
  public function unsetDuplicateFlagInProduct ($observer) {
    $observer
      ->getNewProduct()
      ->setIsDuplicate(false)
      ->setOrigIsDuplicate(true);
  }

  /**
   * Restore is_duplicate flag to not affect other code, such as in
   * Mage_Catalog_Model_Product_Attribute_Backend_Media::afterSave() method
   *
   * @param Varien_Event_Observer $observer Event observer
   */
  public function restoreDuplicateFlagInProduct ($observer) {
    $product = $observer->getProduct();

    if ($product->getOrigIsDuplicate())
      $product->setIsDuplicate(true);
  }
}
