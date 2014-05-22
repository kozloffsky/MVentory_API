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
 */

/**
 * TradeMe API
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Api {

  const LOG_FILE = 'trademe.log';

  const CACHE_CATEGORIES = 'TRADEME_CATEGORIES';
  const CACHE_CATEGORY_ATTRS = 'TRADEME_CATEGORY_ATTRS';

  //List of TradeMe categories to ignore. Categories are selected by its number
  private $_ignoreCategories = array(
    '0001-' => true, //Trade Me Motors
    '0350-' => true, //Trade Me Property
    '5000-' => true, //Trade Me Jobs
    '9374-' => true, //Travel, events & activities
  );

  private $_imageSize = array('width' => 670, 'height' => 502);

  private $_helper = null;

  private $_config = null;
  private $_host = 'trademe';
  private $_categories = array();

  private $_website = null;

  private $_accountId = null;
  private $_accountData = null;

  private $_attrTypes = array(
    0 => 'None',
    1 => 'Boolean',
    2 => 'Integer',
    3 => 'Decimal',
    4 => 'String',
    5 => 'DateTime',
    6 => 'StayPeriod'
  );

  private $_pickupValues = array(
    1 => 'Allow',
    2 => 'Demand',
    3 => 'Forbid'
  );

  private $_durations = array(
    2 => 'Two',
    3 => 'Three',
    4 => 'Four',
    5 => 'Five',
    6 => 'Six',
    7 => 'Seven'
  );

  public function __construct () {
    $this->_helper = Mage::helper('mventory_tm/product');
  }

  private function _getConfig ($path) {
    return $this
      ->_helper
      ->getConfig($path, $this->_website);
  }

  private function getConfig () {
    if ($this->_config)
      return $this->_config;

    $host = $this->_getConfig(MVentory_TradeMe_Model_Config::SANDBOX)
              ? 'tmsandbox'
                : 'trademe';

    $this->_config = array(
      'requestScheme' => Zend_Oauth::REQUEST_SCHEME_HEADER,
      'version' => '1.0',
      'signatureMethod' => 'HMAC-SHA1',
      'siteUrl' => 'https://secure.' . $host . '.co.nz/Oauth/',
      'requestTokenUrl'
        => 'https://secure.' . $host . '.co.nz/Oauth/RequestToken',
      'userAuthorisationUrl'
        => 'https://secure.' . $host . '.co.nz/Oauth/Authorize',
      'accessTokenUrl'
        => 'https://secure.' . $host . '.co.nz/Oauth/AccessToken',
      'consumerKey' => $this->_accountData['key'],
      'consumerSecret' => $this->_accountData['secret']
    );

    $request = Mage::app()->getRequest();

    //Check if code was invoked during HTTP request.
    //Don't set incorrect value when code is invoked by cron
    if ($request->getHttpHost())
      $this->_config['callbackUrl'] = Mage::helper('core/url')->getCurrentUrl();

    $this->_host = $host;

    return $this->_config;
  }

  private function getWebsiteId ($product) {
    $this->_website = $this
      ->_helper
      ->getWebsite($product);
  }

  public function setWebsiteId ($websiteId) {
    $this->_website = Mage::app()->getWebsite($websiteId);

    return $this;
  }

  public function setAccountId ($data) {
    if (is_array($data))
      $this->_accountId = isset($data['account_id'])
                            ? $data['account_id']
                              : null;
    else
      $this->_accountId = $data;

    $accounts = Mage::helper('trademe')->getAccounts($this->_website);

    if ($this->_accountId)
      $this->_accountData = $accounts[$this->_accountId];
    else {
      $this->_accountId = key($accounts);
      $this->_accountData = current($accounts);
    }

    return $this;
  }

  public function auth () {
    if (!(isset($this->_accountData['access_token'])
          && $data = $this->_accountData['access_token']))
      return null;

    $token = new Zend_Oauth_Token_Access();

    return $token->setParams(unserialize($data));
  }

  public function send ($product, $categoryId, $data) {
    self::debug();

    $helper = Mage::helper('trademe');

    $this->getWebsiteId($product);
    $this->setAccountId($data);

    $account = $helper->prepareAccounts(array($this->_accountData), $product);
    $account = $account[0];

    if (!isset($account['shipping_type']))
      return 'No settings for product\'s shipping type';

    if (!$isUpdateOptions = is_array($data))
      $_data = $helper->getFields($product, $account);
    else {
      $_data = $data;

      foreach ($_data as $key => $value)
        if ($value == -1 && isset($account[$key]))
          $_data[$key] = $account[$key];
    }

    self::debug(array('Final TradeMe options: ' => $_data));

    $return = 'Error';

    if ($accessToken = $this->auth()) {
      if (!$categoryId)
        return 'Product doesn\'t have matched TradeMe category';

      //$productShippingType = $this->_helper->getShippingType($product);

      //$shippingType = isset($_data['shipping_type'])
      //                  ? $_data['shipping_type']
      //                    : MVentory_TradeMe_Model_Config::SHIPPING_UNDECIDED;
      $shippingType = MVentory_TradeMe_Model_Config::SHIPPING_UNDECIDED;

      Mage::unregister('product');
      Mage::register('product', $product);

      $descriptionTmpl = $account['footer'];

      $description = '';

      if ($descriptionTmpl) {
        //if ($productShippingType == 'tab_ShipFree'
        //    || ($productShippingType == 'tab_ShipParcel'
        //        && $shippingType == MVentory_TradeMe_Model_Config::SHIPPING_FREE
        //        && isset($account['free_shipping_cost'])
        //        && $account['free_shipping_cost'] > 0))
        //  $_data['free_shipping_text'] = isset($account['free_shipping_text'])
        //                                   ? $account['free_shipping_text']
        //                                     : '';

        $description = $this->processDescription(
          $descriptionTmpl,
          $product->getData()
        );
      }

      if (strlen($description)
            > MVentory_TradeMe_Model_Config::DESCRIPTION_MAX_LENGTH)
        return 'Length of the description exceeded the limit of '
               .  MVentory_TradeMe_Model_Config::DESCRIPTION_MAX_LENGTH
               . ' characters';

      $description = htmlspecialchars($description);

      $photoId = null;

      $image = $product->getImage();

      if ($image && $image != 'no_selection') {
        $store = $this->_website->getDefaultStore();
        $changeStore = $store->getId != Mage::app()->getStore()->getId();

        if ($changeStore) {
          $emu = Mage::getModel('core/app_emulation');
          $origEnv = $emu->startEnvironmentEmulation($store);
        }

        ///!!!TODO: check if resized image exists before resizing it
        $image = Mage::getModel('catalog/product_image')
          ->setDestinationSubdir('image')
          ->setKeepFrame(false)
          ->setConstrainOnly(true)
          ->setWidth($this->_imageSize['width'])
          ->setHeight($this->_imageSize['height'])
          ->setBaseFile($image)
          ->resize()
          ->saveFile()
          ->getNewFile();

        if ($changeStore)
          $emu->stopEnvironmentEmulation($origEnv);

        unset($store, $changeStore, $emu, $origEnv);

        if (!file_exists($image))
          return 'Image doesn\'t exists';

        if (!is_int($photoId = $this->uploadImage($image)))
          return $photoId;
      }

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $title = $product->getName();

      if (strlen($title) > MVentory_TradeMe_Model_Config::TITLE_MAX_LENGTH)
        $title = htmlspecialchars(substr(
          $title,
          0,
          MVentory_TradeMe_Model_Config::TITLE_MAX_LENGTH - 1
        ))
        . '&#8230;';
      else
        $title = htmlspecialchars($title);

      //elseif ($productShippingType == 'tab_ShipParcel'
      //        && $shippingType == MVentory_TradeMe_Model_Config::SHIPPING_FREE
      //        && isset($account['free_shipping_cost'])
      //        && $account['free_shipping_cost'] > 0) {
      //  $freeShippingTitle = $title . ', free shipping';

      //  if (strlen($freeShippingTitle) <= MVentory_TradeMe_Model_Config::TITLE_MAX_LENGTH)
      //    $title = $freeShippingTitle;
      //}

      $price = $product->getPrice();

      //if ($shippingType != MVentory_TradeMe_Model_Config::SHIPPING_FREE) {

        //Add shipping rate using Volume/Weight based shipping method
        $price += $helper->getShippingRate(
          $product,
          $account['name'],
          $this->_website
        );
      //} else {

      //  //Add free shippih cost if product's shipping type is 'tab_ShipParcel'
      //  if ($productShippingType == 'tab_ShipParcel'
      //      && isset($account['free_shipping_cost'])
      //      && $account['free_shipping_cost'] > 0) {
      //    $price += (float) $account['free_shipping_cost'];
      //  }
      //}

      //Apply fees to price of the product if it's allowed
      $price = isset($_data['add_fees']) && $_data['add_fees']
                  ? $helper->addFees($price)
                    : $price;

      $buyNow = '';

      if (isset($_data['allow_buy_now']) && $_data['allow_buy_now'])
        $buyNow = '<BuyNowPrice>' . $price . '</BuyNowPrice>';

      $duration = $this->_durations[$helper->getDuration($account)];

      $shippingTypes
        = Mage::getModel('trademe/attribute_source_freeshipping')
            ->toArray();

      $shippingType
        = $shippingTypes[$shippingType];

      unset($shippingTypes);

      $pickup = $this->_getPickup($_data, $account);
      $pickup = $this->_pickupValues[$pickup];

      $isBrandNew = (int) $this->_getIsBrandNew($product);

      $xml = '<ListingRequest xmlns="http://api.trademe.co.nz/v1">
<Category>' . $categoryId . '</Category>
<Title>' . $title . '</Title>
<Description><Paragraph>' . $description . '</Paragraph></Description>
<StartPrice>' . $price . '</StartPrice>
<ReservePrice>' . $price . '</ReservePrice>'
. $buyNow .
'<Duration>' . $duration . '</Duration>
<Pickup>' . $pickup . '</Pickup>
<IsBrandNew>' . $isBrandNew . '</IsBrandNew>
<SendPaymentInstructions>true</SendPaymentInstructions>';

      if (isset($account['category_image']) && $account['category_image'])
        $xml .= '<HasGallery>true</HasGallery>';

      if ($photoId) {
        $xml .= '<PhotoIds><PhotoId>' . $photoId . '</PhotoId></PhotoIds>';
      }

      $xml .= '<ShippingOptions>';

      if (isset($account['shipping_options']) && $account['shipping_options'])
        foreach ($account['shipping_options'] as $shippingOption)
          $xml .= '<ShippingOption><Type>Custom</Type><Price>'
                  . $shippingOption['price']
                  . '</Price><Method>'
                  . $shippingOption['method']
                  . '</Method></ShippingOption>';
      else
        $xml .= '<ShippingOption><Type>'
                . $shippingType
                . '</Type></ShippingOption>';

      $xml .= '</ShippingOptions>
<PaymentMethods>
<PaymentMethod>CreditCard</PaymentMethod>
<PaymentMethod>Cash</PaymentMethod>
<PaymentMethod>BankDeposit</PaymentMethod>
</PaymentMethods>';

      $attributes = $this->getCategoryAttrs($categoryId);

      if ($attributes) {
        $attributes = $helper->fillAttributes(
          $product,
          $attributes,
          $helper->getMappingStore()
        );

        if ($attributes['error']) {
          if (isset($attributes['required']))
            return 'Product has empty "' . $attributes['required']
                   . '" attribute';

          if (isset($attributes['no_match']))
            return 'Error in matching "' . $attributes['no_match']
                   . '" attribute: incorrect value in "fake" store';
        }

        if ($attributes = $attributes['attributes']) {
          $xml .= '<Attributes>';

          foreach ($attributes as $attributeName => $attributeValue) {
            $xml .= '<Attribute>';
            $xml .= '<Name>' . htmlspecialchars($attributeName) . '</Name>';
            $xml .= '<Value>' . htmlspecialchars($attributeValue) . '</Value>';
            $xml .= '</Attribute>';
          }

          $xml .= '</Attributes>';
        }
      }

      $xml .= '</ListingRequest>';

      $client->setRawData($xml, 'application/xml');
      $response = $client->request();

      $xml = simplexml_load_string($response->getBody());

      if ($xml) {
        if ((string)$xml->Success == 'true') {

          if ($isUpdateOptions) {
            $_data['account_id'] = $this->_accountId;

            $helper->setFields($product, $data);
          }

          $product->setTmCurrentAccountId($this->_accountId);

          $return = (int)$xml->ListingId;
        } elseif ((string)$xml->ErrorDescription) {
          $return = (string)$xml->ErrorDescription;

          self::debug('Error on send (' . $return . ')');
        } elseif ((string)$xml->Description) {
          $return = (string)$xml->Description;

          self::debug('Error on send (' . $return . ')');
        }
      }
    }

    return $return;
  }

  public function remove($product) {
    self::debug();

    $this->getWebsiteId($product);

    $accountId = Mage::helper('trademe')
                   ->getCurrentAccountId($product->getId());

    $this->setAccountId($accountId);

    $error = 'error';

    if ($accessToken = $this->auth()) {
      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling/Withdraw.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $xml = '<WithdrawRequest xmlns="http://api.trademe.co.nz/v1">
<ListingId>' . $product->getTmCurrentListingId() . '</ListingId>
<Type>ListingWasNotSold</Type>
<Reason>Withdraw</Reason>
</WithdrawRequest>';

      $client->setRawData($xml, 'application/xml');
      $response = $client->request();

      $xml = simplexml_load_string($response->getBody());

      if ($xml) {
        if ((string)$xml->Success == 'true') {
          return true;
        } elseif ((string)$xml->Description) {
          $error = (string)$xml->Description;

          self::debug('Error on removing listing '
                      . $product->getTmCurrentListingId()
                      . ' (' . $error . ')');
        }
      }
    }

    return $error;
  }

  public function check ($product) {
    self::debug();

    $listingId = $product->getTmCurrentListingId();

    $this->getWebsiteId($product);
    $this->setAccountId($product->getTmCurrentAccountId());

    $json = $this->_loadListingDetailsAuth($listingId);

    if (!$json)
      return;

    $item = $this->_parseListingDetails($json);

    if (is_string($item)) {
      self::debug('Error on retrieving listing details '
                  . $listingId
                  . ' ('
                  . $item
                  . ')');

      return;
    }

    unset($json);

    //Check if item on sold
    if ($item['AsAt'] < $item['EndDate'])
      return 3;

    //Check if item was sold
    if ($item['BidCount'] > 0)
      return 2;

    //Item wasn't sold or was withdrawn
    return 1;
  }

  public function update ($product, $parameters = null, $_formData = null) {
    self::debug();

    $helper = Mage::helper('trademe');

    $this->getWebsiteId($product);

    if ($_formData && isset($_formData['account_id']))
      unset($_formData['account_id']);

    $accountId = $product->getTmCurrentAccountId();
    $this->setAccountId($accountId);

    $account = $helper->prepareAccounts(array($this->_accountData), $product);
    $account = $account[0];

    if (!isset($account['shipping_type']))
      return 'No settings for product\'s shipping type';

    $listingId = $product->getTmCurrentListingId();
    $return = 'Error';

    if ($accessToken = $this->auth()) {
      $client = $accessToken->getHttpClient($this->getConfig());

      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling/Edit.json');
      $client->setMethod(Zend_Http_Client::POST);
      $json = $this->_loadListingDetailsAuth($listingId);

      if (!$json){
        self::debug('Unable to retrieve data for listing ' . $listingId);

        $this->_helper->sendEmail(
          'Unable to retrieve data for TradeMe listing ',
          $return . ' product id ' . $product->getId() . ' listing id '
            . $listingId
        );

        return 'Unable to retrieve data from TradeMe';
      }

      $item = $this->_parseListingDetails($json);
      $item = $this->_listingDetailsToEditingRequest($item);

      $formData = $_formData;

      if ($formData)
        foreach ($formData as $key => $value)
          if ($value == -1 && isset($account[$key]))
            $formData[$key] = $account[$key];

      //$shippingType = isset($formData['shipping_type'])
      //                  ? $formData['shipping_type']
      //                    : MVentory_TradeMe_Model_Config::SHIPPING_UNDECIDED;

      $shippingType = MVentory_TradeMe_Model_Config::SHIPPING_UNDECIDED;

      //$productShippingType = $this->_helper->getShippingType($product);

      if (!isset($parameters['Category']) && isset($formData['category'])
          && $formData['category'])
        $parameters['Category'] = $formData['category'];

      if (!isset($parameters['Title'])) {
        $title = $product->getName();

        if (strlen($title) > MVentory_TradeMe_Model_Config::TITLE_MAX_LENGTH)
          //!!!TODO: use hellip instead 3 dots, see send() method
          $title = substr(
            $title,
            0,
            MVentory_TradeMe_Model_Config::TITLE_MAX_LENGTH - 3
          ) . '...';
        //elseif ($productShippingType == 'tab_ShipParcel'
        //        && $shippingType == MVentory_TradeMe_Model_Config::SHIPPING_FREE
        //        && isset($account['free_shipping_cost'])
        //        && $account['free_shipping_cost'] > 0) {
        //  $freeShippingTitle = $title . ', free shipping';

        //  if (strlen($freeShippingTitle) <= MVentory_TradeMe_Model_Config::TITLE_MAX_LENGTH)
        //    $title = $freeShippingTitle;
        //}

        $parameters['Title'] = $title;
      }

      if (!isset($parameters['ShippingOptions']))
        if (isset($account['shipping_options']) && $account['shipping_options'])
          foreach ($account['shipping_options'] as $shippingOption)
            $parameters['ShippingOptions'][] = array(
              'Type' => MVentory_TradeMe_Model_Config::SHIPPING_CUSTOM,
              'Price' => $shippingOption['price'],
              'Method' => $shippingOption['method'],
            );
        else
          $parameters['ShippingOptions'][]['Type'] = $shippingType;

      //set price
      if (!isset($parameters['StartPrice'])) {

        $price = $product->getPrice();

        //if ($shippingType != MVentory_TradeMe_Model_Config::SHIPPING_FREE) {

          //Add shipping rate using Volume/Weight based shipping method
          $price += $helper->getShippingRate(
            $product,
            $account['name'],
            $this->_website
          );
        //} else {

        //  //Add free shippih cost if product's shipping type is 'tab_ShipParcel'
        //  if ($productShippingType == 'tab_ShipParcel'
        //      && isset($account['free_shipping_cost'])
        //      && $account['free_shipping_cost'] > 0) {
        //    $price += (float) $account['free_shipping_cost'];
        //  }
        //}

        //Apply fees to price of the product if it's allowed
        $price = isset($formData['add_fees']) && $formData['add_fees']
                   ? $helper->addFees($price)
                     : $price;

        $parameters['StartPrice'] = $price;

        unset($price);
      }

      if(!isset($parameters['ReservePrice']))
        $parameters['ReservePrice'] = $parameters['StartPrice'];
      if(!isset($parameters['BuyNowPrice']) && ((isset($formData['allow_buy_now'])
        && $formData['allow_buy_now'])) || isset($item['BuyNowPrice']))
          $parameters['BuyNowPrice'] = $parameters['StartPrice'];

      //set description
      if(!isset($parameters['Description'])) {
        $descriptionTmpl = $account['footer'];

        $description = '';

        if ($descriptionTmpl) {
          //Set current product in Magento registry, it's required by the block
          //which shows product's attributes
          Mage::register('product', $product, true);

          $_data = $product->getData();

          //if ($productShippingType == 'tab_ShipFree'
          //    || ($productShippingType == 'tab_ShipParcel'
          //        && $shippingType == MVentory_TradeMe_Model_Config::SHIPPING_FREE
          //        && isset($account['free_shipping_cost'])
          //        && $account['free_shipping_cost'] > 0))
          //  $_data['free_shipping_text'] = isset($account['free_shipping_text'])
          //                                   ? $account['free_shipping_text']
          //                                     : '';

          $description = $this->processDescription($descriptionTmpl, $_data);

          unset($_data);

          $description = htmlspecialchars($description);
        }
        $parameters['Description'] = array($description);
      }
      else {
        $parameters['Description'] = array($parameters['Description']);
      }

      //set Duration
      $item['Duration'] = $helper->getDuration($account);

      //Set pickup option
      if (!isset($parameters['Pickup']) && isset($formData['pickup']))
        $parameters['Pickup'] = $this->_getPickup($formData, $account);

      //Set IsBrandNew option
      if (!isset($parameters['IsBrandNew']))
        $parameters['IsBrandNew'] = $this->_getIsBrandNew($product);

      //set Payment methods
      //  None = 0
      //  BankDeposit = 1
      //  CreditCard = 2
      //  Cash = 4
      //  SafeTrader = 8
      //  Other = 16
      $item['PaymentMethods'] = array(1,2,4);

      $item = array_merge($item,$parameters);
      $client->setRawData(Zend_Json::encode($item), 'application/json');

      $response = $client->request();
      $jsonResponse = json_decode($response->getBody());

      if (isset($jsonResponse->Success) && $jsonResponse->Success == 'true') {
        if ($_formData) {
          $helper->setFields($product, $_formData);

          $product->save();
        }

        $return = (int)$jsonResponse->ListingId;
      }
      else {
        if (isset($jsonResponse->Description) && (string)$jsonResponse->Description) {
          $return = (string)$jsonResponse->Description;
        } elseif (isset($jsonResponse->ErrorDescription)
                  && (string)$jsonResponse->ErrorDescription) {
            $return = (string)$jsonResponse->ErrorDescription;
        }

        $this->_helper->sendEmail(
          'Unable to update TradeMe listing ',
          $return .' product id ' . $product->getId() . ' listing id '
            . $listingId
        );

        self::debug(
          'Error on updating listing ' . $listingId . ' (' . $return . ')'
        );
      }
  	}
  	else {
  	  $this->_helper->sendEmail(
        'Unable to auth TradeMe',
        $return . ' product id ' . $product->getId() . ' listing id '
          . $listingId
      );

  	  self::debug(
        'Unable to auth when trying to update listing details ' . $listingId
  	   );
  	}

    return $return;
  }

  public function massCheck($products) {
    if (!$accessToken = $this->auth())
      return;

    if ($products instanceof Mage_Catalog_Model_Product) {
      $collection = new Varien_Data_Collection();
      $products = $collection->addItem($products);
    }

    $client = $accessToken->getHttpClient($this->getConfig());
    $client->setUri(
      'https://api.' . $this->_host
        . '.co.nz/v1/MyTradeMe/SellingItems/All.json'
    );
    $client->setMethod(Zend_Http_Client::GET);

    //Request more rows than number of products to be sure that all listings
    //from account will be included in a response
    $client->setParameterGet('rows', count($products) * 10);

    $response = $client->request();

    if ($response->getStatus() != 200)
      return;

    $items = json_decode($response->getBody(), true);

    foreach ($products as $product)
      foreach ($items['List'] as $item)
        if ($item['ListingId'] == $product->getTmCurrentListingId())
          $product->setIsSelling(true);

    return $items['TotalCount'];
  }

  public function relist ($product) {
    $this->getWebsiteId($product);

    $accountId = Mage::helper('trademe')
      ->getCurrentAccountId($product->getId());

    $this->setAccountId($accountId);

    if (!$listingId = $product->getTmCurrentListingId())
      return false;

    if (!$accessToken = $this->auth())
      return false;

    $client = $accessToken->getHttpClient($this->getConfig());

    $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling/Relist.json');
    $client->setMethod(Zend_Http_Client::POST);

    $data = array('ListingId' => $listingId);

    $client->setRawData(Zend_Json::encode($data), 'application/json');

    $response = $client->request();

    if ($response->getStatus() != 200)
      return false;

    $response = Zend_Json::decode($response->getBody());

    if (!$response['Success']) {
      Mage::log(
        'TradeMe: error on relisting '
        . $listingId
        . ' ('
        . $response['Description']
        . ')'
      );

      return false;
    }

    return $response['ListingId'];
  }

  public function uploadImage ($image) {
    self::debug();

    if (!$accessToken = $this->auth())
      return;

    $client = $accessToken->getHttpClient($this->getConfig());

    $url = 'https://api.' . $this->_host . '.co.nz/v1/Photos.json';

    $info = pathinfo($image);

    $data = array(
      'PhotoData' => base64_encode(file_get_contents($image)),
      'FileName' => $info['filename'],
      'FileType' => $info['extension'],
    );

    $client->setUri($url);
    $client->setMethod(Zend_Http_Client::POST);
    $client->setRawData(Zend_Json::encode($data), 'application/json');

    $response = $client->request();

    $result = Zend_Json::decode($response->getBody());

    if ($response->getStatus() != 200)
      return $result['ErrorDescription'];

    if ($result['Status'] != 1) {
      $msg = 'Error on image uploading ('
             . $image
             . '). Error description: '
             . $result['Description'];

      self::debug($msg);

      $this->_helper->sendEmail('Unable to upload image to TradeMe', $msg);

      return $result['Description'];
    }

    return $result['PhotoId'];
  }

  private function processDescription ($template, $data) {
    $search = array();
    $replace = array();

    $search[] = '{{url}}';
    $replace[] = rtrim($this->_getConfig('web/unsecure/base_url'), '/')
                 . '/'
                 . Mage::getModel('core/url')
                     ->setRouteParams(array('sku' => $data['sku']))
                     ->getRoutePath();

    $shortDescription = isset($data['short_description'])
                          ? $data['short_description']
                            : '';

    $search[] = '{{sd}}';
    $replace[] = strlen($shortDescription) > 5 ? $shortDescription : '';

    $fullDescription = isset($data['description']) ? $data['description'] : '';

    $search[] = '{{fd}}';
    $replace[] = strlen($fullDescription) > 5 ? $fullDescription : '';

    $search[] = '{{fs}}';
    $replace[] = isset($data['free_shipping_text'])
                   ? $data['free_shipping_text']
                     : '';

    $_attrs = Mage::app()
      ->getLayout()
      ->createBlock('mventory_tm/product_view_attributes')
      ->getAdditionalData(
          array('product_barcode_', 'mv_created_date'),
          false
        );

    $attrs = '';

    foreach ($_attrs as $_attr)
      $attrs .= $_attr['label'] . ': ' . $_attr['value'] . "\r\n";

    $search[] = '{{attrs}}';
    $replace[] = rtrim($attrs);

    $description = str_replace($search, $replace, $template);

    do {
      $before = strlen($description);

      $description = str_replace("\r\n\r\n\r\n", "\r\n\r\n", $description);

      $after = strlen($description);
    } while ($before != $after);

    return trim($description);
  }

  public function _loadCategories () {
    $options = array(
      CURLOPT_URL => 'http://api.trademe.co.nz/v1/Categories.json',
      CURLOPT_RETURNTRANSFER => true,
    );

    $curl = curl_init();

    if (!curl_setopt_array($curl, $options))
      return null;

    $output = curl_exec($curl);

    curl_close($curl);

    return $output;
  }

  public function _loadCategoryAttrs ($categoryId) {
    $options = array(
      CURLOPT_URL => 'http://api.trademe.co.nz/v1/Categories/'
                     . $categoryId
                     . '/Attributes.json',
      CURLOPT_RETURNTRANSFER => true,
    );

    $curl = curl_init();

    if (!curl_setopt_array($curl, $options))
      return null;

    $output = curl_exec($curl);

    curl_close($curl);

    return $output;
  }

  public function _loadListingDetails ($listingId) {
    $options = array(
      CURLOPT_URL => 'http://api.'
                     . $this->_host
                     . '.co.nz/v1/Listings/'
                     . $listingId
                     . '.json',
      CURLOPT_RETURNTRANSFER => true,
    );

    $curl = curl_init();

    if (!curl_setopt_array($curl, $options))
      return null;

    $output = curl_exec($curl);

    curl_close($curl);

    return $output;
  }

  public function _loadListingDetailsAuth ($listingId) {
    if (!$accessToken = $this->auth())
      return;

    $client = $accessToken->getHttpClient($this->getConfig());

    $url = 'http://api.'
           . $this->_host
           . '.co.nz/v1/Listings/'
           . $listingId
           . '.json';

    $client->setUri($url);
    $client->setMethod(Zend_Http_Client::GET);

    $response = $client->request();

    if ($response->getStatus() != 200)
      return;

    return $response->getBody();
  }

  public function _parseCategories (&$list, $categories, $names = array()) {
    foreach ($categories as $category) {
      if (isset($this->_ignoreCategories[$category['Number']]))
        continue;

      $_names = array_merge($names, array($category['Name']));

      if (isset($category['Subcategories']))
        $this->_parseCategories($list, $category['Subcategories'], $_names);
      else {
        $id = explode('-', $category['Number']);
        $id = (int) $id[count($id) - 2];

        $list[$id] = array(
          'name' => $_names,
          'path' => $category['Path']
        );
      }
    }
  }

  public function _parseListingDetails ($details) {
    $details = json_decode($details, true);

    if (isset($details['ErrorDescription']))
      return $details['ErrorDescription'];

    $details['EndDate'] = $this->_prepareTimestamp($details['EndDate']);
    $details['AsAt'] = $this->_prepareTimestamp($details['AsAt']);

    if (!isset($details['BidCount']))
      $details['BidCount'] = 0;

    return $details;
  }

  public function _listingDetailsToEditingRequest ($details) {

    //Prepare attached photos for editing request
    if (isset($details['Photos']) && $photos = $details['Photos']) {
      foreach ($photos as $photo)
        $details['PhotoIds'][] = $photo['Key'];

      unset($details['Photos']);
    }

    //Rename AllowsPickups option to Pickup
    if (isset($details['AllowsPickups'])) {
      $details['Pickup'] = $details['AllowsPickups'];

      unset($details['AllowsPickups']);
    }

    return $details;
  }

  private function _prepareTimestamp ($data) {
    return substr($data, 6, -2) / 1000;
  }

  protected function _getPickup ($data, $account) {
    if (isset($data['pickup'])) {
      $pickup = (int) $data['pickup'];

      if ($pickup == MVentory_TradeMe_Model_Config::PICKUP_ALLOW
          || $pickup == MVentory_TradeMe_Model_Config::PICKUP_DEMAND
          || $pickup == MVentory_TradeMe_Model_Config::PICKUP_FORBID)
        return $pickup;
    }

    return isset($account['allow_pickup']) && $account['allow_pickup']
             ? MVentory_TradeMe_Model_Config::PICKUP_ALLOW
               : MVentory_TradeMe_Model_Config::PICKUP_FORBID;

    if (!isset($account['allow_pickup']))
      return MVentory_TradeMe_Model_Config::PICKUP_FORBID;
  }

  protected function _getIsBrandNew ($product) {
    return ($value = $product->getData('mv_condition_')) === null
           || in_array(
                $value,
                explode(
                  ',',
                  $this->_getConfig(MVentory_TradeMe_Model_Config::LIST_AS_NEW)
                )
              );
  }

  //!!!TODO: remove method
  protected function _getDuration ($account) {
    if (!(isset($account['duration'])
          && $duration = (int) $account['duration']))
      return MVentory_TradeMe_Helper_Data::LISTING_DURATION_MAX;

    if ($duration < MVentory_TradeMe_Helper_Data::LISTING_DURATION_MIN)
      return MVentory_TradeMe_Helper_Data::LISTING_DURATION_MIN;

    if ($duration > MVentory_TradeMe_Helper_Data::LISTING_DURATION_MAX)
      return MVentory_TradeMe_Helper_Data::LISTING_DURATION_MAX;

    return $duration;
  }

  public function getCategories () {
    $app = Mage::app();

    if ($list = $app->loadCache(self::CACHE_CATEGORIES))
      return unserialize($list);

    $json = $this->_loadCategories();

    if (!$json)
      return null;

    $categories = json_decode($json, true);

    unset($json);

    $list = array();

    $this->_parseCategories($list, $categories['Subcategories']);

    unset($categories);

    if ($app->useCache(MVentory_TradeMe_Model_Config::CACHE_TYPE))
      $app->saveCache(
        serialize($list),
        self::CACHE_CATEGORIES,
        array(MVentory_TradeMe_Model_Config::CACHE_TAG)
      );

    return $list;
  }

  public function getCategoryAttrs ($categoryId) {
    if (!$categoryId)
      return null;

    $app = Mage::app();

    if ($attrs = $app->loadCache(self::CACHE_CATEGORY_ATTRS . $categoryId))
      return unserialize($attrs);

    $json = $this->_loadCategoryAttrs($categoryId);

    if (!$json)
      return null;

    $attrs = json_decode($json, true);

    if ($app->useCache(MVentory_TradeMe_Model_Config::CACHE_TYPE))
      $app->saveCache(
        serialize($attrs),
        self::CACHE_CATEGORY_ATTRS . $categoryId,
        array(MVentory_TradeMe_Model_Config::CACHE_TAG)
      );

    return $attrs;
  }

  public function getAttrTypeName ($id) {
    if (isset($this->_attrTypes[$id]))
      return $this->_attrTypes[$id];

    return 'Unknown';
  }

  public static function debug ($msg = null) {
    $backtrace = debug_backtrace();

    $callee = $backtrace[1];

    $name = (isset($callee['line']) ? '[' . $callee['line'] . '] ' : '')
            . $callee['class']
            . $callee['type']
            . $callee['function'];

    if ($msg === null)
      foreach ($callee['args'] as $arg) {
        if ($arg instanceof Varien_Object)
          $args[] = get_class($arg) . '('

                    . 'id: ' . $arg->getId() . ', '
                    . 'sku: ' . $arg->getData('sku')

                    . ')';
        else
          $args[] = print_r($arg, true);

        $msg = '(' . implode(', ', $args) . ')';
      }
    else if (is_array($msg))
      $msg = '(): ' . print_r($msg, true);
    else
      $msg = '(): ' . $msg;

    Mage::log($name . $msg, null, self::LOG_FILE);
  }
}
