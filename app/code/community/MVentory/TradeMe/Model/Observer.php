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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

/**
 * Event handlers
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Observer {

  const SYNC_START_HOUR = 7;
  const SYNC_END_HOUR = 23;

  const TAG_FREE_SLOTS = 'tag_trademe_free_slots';
  const TAG_EMAILS = 'tag_trademe_emails';

  public function sortChildren ($observer) {
    $content = Mage::app()
      ->getFrontController()
      ->getAction()
      ->getLayout()
      ->getBlock('content');

    $matching = $content->getChild('trademe.matching');

    $content
      ->unsetChild('trademe.matching')
      ->append($matching);
  }

  public function addAccountsToConfig ($observer) {
    if (Mage::app()->getRequest()->getParam('section') != 'trademe')
      return;

    $settings = $observer
                  ->getConfig()
                  ->getNode('sections')
                  ->trademe
                  ->groups
                  ->settings;

    $template = $settings
                  ->account_template
                  ->asArray();

    if (!$accounts = Mage::registry('trademe_config_accounts')) {
      $groups = Mage::getSingleton('adminhtml/config_data')
                  ->getConfigDataValue('trademe');

      $accounts = array();

      if ($groups) foreach ($groups->children() as $id => $account)
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
      $group->addChild('frontend_model', 'trademe/account');
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

  public function restoreNewAccountInConfig ($observer) {
    $configData = $observer->getObject();

    if ($configData->getSection() != 'trademe')
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

    Mage::register('trademe_config_accounts', $accounts);
  }

  public function sync ($schedule) {
    //Get cron job config
    $jobsRoot = Mage::getConfig()->getNode('default/crontab/jobs');
    $jobConfig = $jobsRoot->{$schedule->getJobCode()};

    //Get website from the job config
    $website = Mage::app()->getWebsite((string) $jobConfig->website);

    //Get website's default store
    $store = $website->getDefaultStore();

    $trademe = Mage::helper('trademe');

    //Load TradeMe accounts which are used in specified website
    $accounts = $trademe->getAccounts($website);

    //Unset Random pseudo-account
    unset($accounts[null]);

    $helper = Mage::helper('mventory_tm/product');

    //Get time with Magento timezone offset
    $now = localtime(Mage::getModel('core/date')->timestamp(time()), true);

    //Check if we are in allowed hours
    $allowSubmit = $now['tm_hour'] >= self::SYNC_START_HOUR
                   && $now['tm_hour'] < self::SYNC_END_HOUR;

    if ($allowSubmit) {
      $cronInterval = (int) $helper->getConfig(
        MVentory_TradeMe_Model_Config::CRON_INTERVAL,
        $website
      );

      //Calculate number of runnings of the sync script during 1 day
      $runsNumber = $cronInterval
                      ? (self::SYNC_END_HOUR - self::SYNC_START_HOUR) * 60
                          / $cronInterval - 1
                        : 0;
    }

    foreach ($accounts as $accountId => &$accountData) {
      $products = Mage::getModel('catalog/product')
        ->getCollection()
        ->addAttributeToSelect('tm_relist')
        ->addAttributeToSelect('price')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('tm_current_listing_id', array('neq' => ''))
        ->addAttributeToFilter('tm_current_account_id', $accountId)
        ->addStoreFilter($store);

      //!!!Commented to allow loading out of stock products
      //If customer exists and loaded add price data to the product collection
      //filtered by customer's group ID
      //if ($customer->getId())
      //  $products->addPriceData($customer->getGroupId());

      $connector = (new MVentory_TradeMe_Model_Api())
        ->setWebsiteId($website->getId())
        ->setAccountId($accountId);

      $accountData['listings'] = $connector->massCheck($products);

      foreach ($products as $product) {
        try {
          if ($product->getIsSelling())
            continue;

          $result = $connector->check($product);

          if (!$result || $result == 3)
            continue;

          --$accountData['listings'];

          if ($result == 2) {
            $sku = $product->getSku();
            $price = $product->getPrice();
            $qty = 1;

            $shipping = $helper->getAttributesValue(
              $product->getId(),
              'mv_shipping_',
              $website
            );

            if (!isset($accountData['shipping_types'][$shipping]['buyer'])) {
              MVentory_TradeMe_Model_Api::debug(
                'Error: shipping type ' . $shippingType . ' doesn\t exists in '
                . $accountData['name'] . ' account. Product SKU: '
                . $sku
              );

              continue;
            }

            $buyer = $accountData['shipping_types'][$shipping]['buyer'];

            //API function for creating order requires curren store to be set
            Mage::app()->setCurrentStore($store);

            //Remember current website to use in API functions. The value is
            //used in getCurrentWebsite() helper function
            Mage::unregister('mventory_website');
            Mage::register('mventory_website', $website, true);

            //Set global flag to prevent removing product from TradeMe during
            //order creating. No need to remove it because it was bought
            //on TradeMe. The flag is used in removeListing() method
            Mage::register('trademe_disable_withdrawal', true, true);

            //Set global flag to enable our dummy shipping method
            Mage::register('tm_allow_dummyshipping', true, true);

            //Set customer ID for API access checks
            Mage::register('tm_api_customer', $buyer, true);

            //Make order for the product
            Mage::getModel('mventory_tm/cart_api')
              ->createOrderForProduct($sku, $price, $qty, $buyer);

            Mage::unregister('mventory_website');
          }

          $trademe->setListingId(0, $product->getId());
        } catch (Exception $e) {
          Mage::unregister('mventory_website');

          Mage::logException($e);
        }
      }

      if ($accountData['listings'] < 0)
        $accountData['listings'] = 0;
    }

    unset($accountId, $accountData);

    if (!($allowSubmit && $runsNumber))
      return;

    foreach ($accounts as $accountId => $accountData) {

      //Remember IDs of all existing accounts for further using
      $allAccountsIDs[$accountId] = true;

      if (($accountData['max_listings'] - $accountData['listings']) < 1)
        unset($accounts[$accountId]);
    }

    unset($accountId, $accountData);

    if (!count($accounts))
      return;

    $products = Mage::getModel('catalog/product')
      ->getCollection()
      ->addAttributeToFilter('type_id', 'simple')
      ->addAttributeToFilter('tm_relist', '1')
      ->addAttributeToFilter(
          'tm_current_listing_id',
          array(
            array('null' => true),
            array('in' => array('', 0))
          ),
          'left'
        )
      ->addAttributeToFilter('image', array('nin' => array('no_selection', '')))
      ->addAttributeToFilter(
          'status',
          Mage_Catalog_Model_Product_Status::STATUS_ENABLED
        )
      ->addStoreFilter($store);

    Mage::getSingleton('cataloginventory/stock')
      ->addInStockFilterToCollection($products);

    if (!$poolSize = count($products))
      return;

    //Calculate avaiable slots for current run of the sync script
    foreach ($accounts as $accountId => &$accountData) {
      $cacheId = implode(
        '_',
        array(
          'trademe_sync',
          $website->getCode(),
          $accountId,
        )
      );

      try {
        $syncData = unserialize(Mage::app()->loadCache($cacheId));
      } catch (Exception $e) {
        $syncData = null;
      }

      if (!is_array($syncData))
        $syncData = array(
          'free_slots' => 0,
          'duration' => MVentory_TradeMe_Helper_Data::LISTING_DURATION_MAX
        );

      $freeSlots = $accountData['max_listings']
                   / ($runsNumber * $syncData['duration'])
                   + $syncData['free_slots'];

      $_freeSlots = (int) floor($freeSlots);

      $syncData['free_slots'] = $freeSlots - $_freeSlots;

      if ($_freeSlots < 1) {
        Mage::app()->saveCache(
          serialize($syncData),
          $cacheId,
          array(self::TAG_FREE_SLOTS),
          null
        );

        unset($accounts[$accountId]);

        continue;
      }

      $accountData['free_slots'] = $_freeSlots;

      $accountData['cache_id'] = $cacheId;
      $accountData['sync_data'] = $syncData;

      $accountData['allowed_shipping_types']
        = array_keys($accountData['shipping_types']);
    }

    if (!count($accounts))
      return;

    unset($accountId, $accountData, $syncData);

    $ids = array_keys($products->getItems());

    shuffle($ids);

    foreach ($ids as $id) {
      $product = Mage::getModel('catalog/product')->load($id);

      if (!$product->getId())
        continue;

      if ($accountId = $product->getTmAccountId())
        if (!isset($allAccountsIDs[$accountId]))
          $product->setTmAccountId($accountId = null);
        else if (!isset($accounts[$accountId]))
          continue;

      $matchResult = Mage::getModel('trademe/matching')
        ->matchCategory($product);

      if (!(isset($matchResult['id']) && $matchResult['id'] > 0))
        continue;

      $accountIds = $accountId
                      ? (array) $accountId
                        : array_keys($accounts);

      shuffle($accountIds);

      $shippingType = $helper->getShippingType($product, true);

      foreach ($accountIds as $accountId) {
        $accountData = $accounts[$accountId];

        if (!in_array($shippingType, $accountData['allowed_shipping_types']))
          continue;

        $minimalPrice = (float) $accountData
          ['shipping_types']
          [$shippingType]
          ['minimal_price'];

        if ($minimalPrice && ($product->getPrice() < $minimalPrice))
          continue;

        $result = (new MVentory_TradeMe_Model_Api())
          ->send($product, $matchResult['id'], $accountId);

        if (trim($result) == 'Insufficient balance') {
          $cacheId = array(
            $website->getCode(),
            $accountData['name'],
            'negative_balance'
          );

          $cacheId = implode('_', $cacheId);

          if (!Mage::app()->loadCache($cacheId)) {
            $helper->sendEmailTmpl(
              'mventory_negative_balance',
              array('account' => $accountData['name']),
              $website
            );

            Mage::app()
              ->saveCache(true, $cacheId, array(self::TAG_EMAILS), 3600);
          }

          if (count($accounts) == 1)
            return;

          unset($accounts[$accountId]);

          continue;
        }

        if (is_int($result)) {
          $product
            ->setTmListingId($result)
            ->setTmCurrentListingId($result)
            ->save();

          if (!--$accounts[$accountId]['free_slots']) {
            $accountData['sync_data']['duration'] = $trademe
              ->getDuration($accountData['shipping_types'][$shippingType]);

            Mage::app()->saveCache(
              serialize($accountData['sync_data']),
              $accountData['cache_id'],
              array(self::TAG_FREE_SLOTS),
              null
            );

            if (count($accounts) == 1)
              return;

            unset($accounts[$accountId]);
          }

          break;
        }
      }
    }
  }

  public function removeListing ($observer) {
    if (Mage::registry('trademe_disable_withdrawal'))
      return;

    $order = $observer
               ->getEvent()
               ->getOrder();

    $items = $order->getAllItems();

    $productHelper = Mage::helper('mventory_tm/product');
    $trademe = Mage::helper('trademe');

    foreach ($items as $item) {
      $productId = (int) $item->getProductId();

      //We can use default store ID because the attribute is global
      $listingId = $trademe->getListingId($productId);

      if (!$listingId)
        continue;

      $stockItem = Mage::getModel('cataloginventory/stock_item')
        ->loadByProduct($productId);

      if (!($stockItem->getManageStock() && $stockItem->getQty() == 0))
        continue;

      $product = Mage::getModel('catalog/product')->load($productId);

      $website = $productHelper->getWebsite($product);
      $accounts = $trademe->getAccounts($website);
      $accounts = $trademe->prepareAccounts($accounts, $product);

      $accountId = $product->getTmCurrentAccountId();

      $account = $accountId && isset($accounts[$accountId])
                   ? $accounts[$accountId]
                     : null;

      //Avoid withdrawal by default
      $avoidWithdrawal = true;

      $fields = $trademe->getFields($product, $account);

      $attrs = $product->getAttributes();

      if (isset($attrs['tm_avoid_withdrawal'])) {
        $attr = $attrs['tm_avoid_withdrawal'];

        if ($attr->getDefaultValue() != $fields['avoid_withdrawal']) {
          $options = $attr
                      ->getSource()
                      ->getOptionArray();

          if (isset($options[$fields['avoid_withdrawal']]))
            $avoidWithdrawal = (bool) $fields['avoid_withdrawal'];
        }
      }

      $hasError = false;

      if ($avoidWithdrawal) {
        $price = $product->getPrice() * 5;

        if ($fields['add_fees'])
          $price = $trademe->addFees($price);

        $result = (new MVentory_TradeMe_Model_Api())
          ->update($product, array('StartPrice' => $price));

        if (!is_int($result))
          $hasError = true;
      } else {
        $result = (new MVentory_TradeMe_Model_Api())->remove($product);

        if ($result !== true)
          $hasError = true;
      }

      if ($hasError) {
        //Send email with error message to website's general contact address

        $productUrl = $productHelper->getUrl($product);
        $listingId = $trademe->getListingUrl($product);

        $subject = 'TradeMe: error on removing listing';
        $message = 'Error on increasing price or withdrawing listing ('
                   . $listingId
                   . ') linked to product ('
                   . $productUrl
                   . ')'
                   . ' Error: ' . $result;

        $productHelper->sendEmail($subject, $message);

        continue;
      }

      $trademe
        ->setListingId(0, $productId)
        ->setCurrentAccountId($productId, null);
    }
  }

  public function addTradeMeData ($observer) {
    $website = $observer->getWebsite();
    $product = $observer
      ->getProduct()
      ->getData();

    $helper = Mage::helper('mventory_tm/product');
    $trademe = Mage::helper('trademe');

    $accounts = $trademe->getAccounts($website);

    $id = isset($product['tm_account_id']) ? $product['tm_account_id'] : null;
    $account = $id && isset($accounts[$id]) ? $accounts[$id] : null;

    $data = $trademe->getFields($product, $account);

    //!!!TODO: fix getFields() method and all code which is used it
    //to use 'account' instead 'account _id'
    $data['account'] = $data['account_id'];
    unset($data['account_id']);

    $data['listing'] = $product['tm_current_listing_id'] > 0
                         ? (int) $product['tm_current_listing_id']
                           : null;

    if ($data['listing']) {
      $data['listing_url']
        = 'http://www.'
          . ($trademe->isSandboxMode($website) ? 'tmsandbox' : 'trademe')
          . '.co.nz/Browse/Listing.aspx?id='
          . $data['listing'];
    }

    $data['shipping_types']
      = Mage::getModel('trademe/attribute_source_freeshipping')
          ->getAllOptions();

    foreach ($accounts as $id => $account)
      $data['accounts'][$id] = $account['name'];

    $matchResult = Mage::getModel('trademe/matching')->matchCategory(
      Mage::getModel('catalog/product')->load($product['product_id'])
    );

    if ($matchResult)
      $data['matched_category'] = $matchResult;

    //Add shipping rate if product's shipping type is 'tab_ShipTransport'
    if (isset($product['mv_shipping_']) && $account) {
      $do = false;

      //Iterate over all attributes...
      foreach ($product['set_attributes'] as $attribute)
        //... to find attribute with shipping type info, then...
        if ($attribute['attribute_code'] == 'mv_shipping_')
          //... iterate over all its options...
          foreach ($attribute['options'] as $option)
            //... to find option with same value as in product and with
            //label equals 'tab_ShipTransport'
            if ($option['value'] == $product['mv_shipping_']
                && $do = ($option['label'] == 'tab_ShipTransport'))
              break 2;

      if ($do) {
        $rate = Mage::helper('trademe')->getShippingRate(
          $observer->getProduct(),
          $account['name'],
          $website
        );

        if ($rate !== null)
          $data['shipping_rate'] = $rate;
      }
    }

    $product['auction']['trademe'] = $data;

    //!!!TODO: remove after the app upgrade. This is for compatibility with
    //old versions of the app
    $product['tm_options'] = $this->_getTmOptions($product);

    //!!!TODO: remove after the app upgrade. This is for compatibility with
    //old versions of the app
    if (isset($product['auction']['trademe']['shipping_rate']))
      $product['shipping_rate']
        = $product['auction']['trademe']['shipping_rate'];
    else
      $product['shipping_rate'] = null;

    $observer
      ->getProduct()
      ->setData($product);
  }

  public function setAllowListingFlag ($observer) {
    $product = $observer->getProduct();

    if ($product->getId())
      return;

    $helper = Mage::helper('mventory_tm');

    $product->setData(
      'tm_relist',
      (bool) $helper->getConfig(
        MVentory_TradeMe_Model_Config::ENABLE_LISTING,
        $helper->getCurrentWebsite()
      )
    );
  }

  /**
   * !!!TODO: remove after the app upgrade. This is for compatibility with
   * old versions of the app
   */
  private function _getTmOptions ($product) {
    $data = $product['auction']['trademe'];

    $data['account_id'] = $data['account'];
    $data['add_tm_fees'] = $data['add_fees'];
    $data['tm_listing_id'] = $data['listing'];
    $data['shipping_types_list'] = $data['shipping_types'];

    if (isset($data['accounts']))
      $data['tm_accounts'] = $data['accounts'];

    if (isset($data['matched_category']))
      $data['preselected_categories'][$data['matched_category']['id']]
        = $data['matched_category']['category'];

    unset(
      $data['account'],
      $data['listing'],
      $data['shipping_types'],
      $data['accounts'],
      $data['shipping_rate']
    );

    return $data;
  }
}
