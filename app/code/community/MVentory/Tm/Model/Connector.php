<?php

class MVentory_Tm_Model_Connector extends Mage_Core_Model_Abstract {

  const SANDBOX_PATH = 'mventory_tm/settings/sandbox';
  const LIST_AS_NEW_PATH = 'mventory_tm/settings/list_as_new';

  const CACHE_TYPE_TM = 'tm';
  const CACHE_TAG_TM = 'TM';
  const CACHE_TM_CATEGORIES = 'TM_CATEGORIES';
  const CACHE_TM_CATEGORY_ATTRS = 'TM_CATEGORY_ATTRS';

  //TM shipping types
  //const UNKNOWN = 0;
  //const NONE = 0;
  const UNDECIDED = 1;
  //const PICKUP = 2;
  const FREE = 3;
  //const CUSTOM = 4;

  const TM_MAX_IMAGE_SIZE = '670x502';

  const TITLE_MAX_LENGTH = 50;
  const DESCRIPTION_MAX_LENGTH = 2048;

  //List of TM categories to ignore. Categories are selected by its number.
  private $_ignoreTmCategories = array(
    '0001-' => true, //Trade Me Motors
    '0350-' => true, //Trade Me Property
    '5000-' => true, //Trade Me Jobs
    '9374-' => true, //Travel, events & activities
  );

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

  protected function _construct () {
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

    $host = $this->_getConfig(self::SANDBOX_PATH)
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

    $accounts = Mage::helper('mventory_tm/tm')->getAccounts($this->_website);

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

  public function send ($product, $categoryId, $_tmData) {
    $this->getWebsiteId($product);
    $this->setAccountId($_tmData);

    $updateTmOptions = is_array($_tmData);

    if (!$updateTmOptions)
      $tmData = Mage::helper('mventory_tm/product')
                  ->getTmFields($product, $this->_accountData);
    else {
      $tmData = $_tmData;

      foreach ($tmData as $key => $value)
        if ($value == -1 && isset($this->_accountData[$key]))
          $tmData[$key] = $this->_accountData[$key];
    }

    $return = 'Error';

    if ($accessToken = $this->auth()) {

      //$categories = Mage::getModel('catalog/category')->getCollection()
      //    ->addAttributeToSelect('mventory_tm_category')
      //    ->addFieldToFilter('entity_id', array('in' => $product->getCategoryIds()));

      //$categoryId = null;

      //foreach ($categories as $category) {
      //  if ($category->getMventoryTmCategory()) {
      //    $categoryId = $category->getMventoryTmCategory();
      //    break;
      //  }
      //}

      if (!$categoryId) {
        return 'Product doesn\'t have matched tm category';
      }

      $tmHelper = Mage::helper('mventory_tm/tm');

      $productShippingType = $tmHelper->getShippingType($product);

      $shippingType = isset($tmData['shipping_type'])
                        ? $tmData['shipping_type']
                          : self::UNDECIDED;

      Mage::unregister('product');
      Mage::register('product', $product);

      $descriptionTmpl = $this->_accountData['footer'];

      $description = '';

      if ($descriptionTmpl) {
        $_data = $product->getData();

        if ($productShippingType == 'tab_ShipFree'
            || ($productShippingType == 'tab_ShipParcel'
                && $shippingType == self::FREE
                && isset($this->_accountData['free_shipping_cost'])
                && $this->_accountData['free_shipping_cost'] > 0))
          $_data['free_shipping_text']
            = isset($this->_accountData['free_shipping_text'])
                ? $this->_accountData['free_shipping_text']
                  : '';

        $description = $this->processDescription($descriptionTmpl, $_data);

        unset($_data);

        $description = htmlspecialchars($description);
      }

      if (strlen($description) > self::DESCRIPTION_MAX_LENGTH)
        return 'Length of the description exceeded the limit of '
               .  self::DESCRIPTION_MAX_LENGTH
               . ' characters';

      $photoId = null;

      $image = $product->getImage();

      if ($image && $image != 'no_selection') {
        $image = Mage::getSingleton('catalog/product_media_config')
                   ->getMediaPath(self::TM_MAX_IMAGE_SIZE . $image);

        if (!file_exists($image))
          $image
            = Mage::helper('mventory_tm/s3')
                ->download($image, $this->_website, self::TM_MAX_IMAGE_SIZE);

        if (!$image)
          return 'Downloading image from S3 failed';

        if (!file_exists($image))
          return 'Image doesn\'t exists';
          
        if (!is_int($photoId = $this->uploadImage($image)))
          return $photoId;
      }

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $title = htmlspecialchars($product->getName());

      if (strlen($title) > self::TITLE_MAX_LENGTH)
        $title = substr($title, 0, self::TITLE_MAX_LENGTH - 3) . '...';
      elseif ($productShippingType == 'tab_ShipParcel'
              && $shippingType == self::FREE
              && isset($this->_accountData['free_shipping_cost'])
              && $this->_accountData['free_shipping_cost'] > 0) {
        $freeShippingTitle = $title . ', free shipping';

        if (strlen($freeShippingTitle) <= self::TITLE_MAX_LENGTH)
          $title = $freeShippingTitle;
      }

      $price = $product->getPrice();

      if ($shippingType != self::FREE) {

        //Add shipping rate if product's shipping type is 'tab_ShipTransport'
        if ($productShippingType == 'tab_ShipTransport') {
          $regionName = $this->_accountData['name'];

          $price += $tmHelper
                      ->getShippingRate($product, $regionName, $this->_website);

          unset($regionName, $website);
        }
      } else {

        //Add free shippih cost if product's shipping type is 'tab_ShipParcel'
        if ($productShippingType == 'tab_ShipParcel'
            && isset($this->_accountData['free_shipping_cost'])
            && $this->_accountData['free_shipping_cost'] > 0) {
          $price += (float) $this->_accountData['free_shipping_cost'];
        }
      }

      //Apply fees to price of the product if it's allowed
      $price = isset($tmData['add_fees']) && $tmData['add_fees']
                  ? $tmHelper->addFees($price)
                    : $price;

      unset($tmHelper);

      $buyNow = '';

      if (isset($tmData['allow_buy_now']) && $tmData['allow_buy_now'])
        $buyNow = '<BuyNowPrice>' . $price . '</BuyNowPrice>';

      $shippingTypes
        = Mage::getModel('mventory_tm/entity_attribute_source_freeshipping')
            ->toArray();

      $shippingType
        = $shippingTypes[$shippingType];

      unset($shippingTypes);

      $pickup = $this->_getPickup($product, $tmData, $this->_accountData);
      $pickup = $this->_pickupValues[$pickup];

      $isBrandNew = (int) $this->_getIsBrandNew($product);

      $xml = '<ListingRequest xmlns="http://api.trademe.co.nz/v1">
<Category>' . $categoryId . '</Category>
<Title>' . $title . '</Title>
<Description><Paragraph>' . $description . '</Paragraph></Description>
<StartPrice>' . $price . '</StartPrice>
<ReservePrice>' . $price . '</ReservePrice>'
. $buyNow .
'<Duration>Seven</Duration>
<Pickup>' . $pickup . '</Pickup>
<IsBrandNew>' . $isBrandNew . '</IsBrandNew>';

      if ($photoId) {
        $xml .= '<PhotoIds><PhotoId>' . $photoId . '</PhotoId></PhotoIds>';
      }

      $xml .= '<ShippingOptions>
<ShippingOption><Type>' . $shippingType . '</Type></ShippingOption>
</ShippingOptions>
<SendPaymentInstructions>true</SendPaymentInstructions>
<PaymentMethods>
<PaymentMethod>CreditCard</PaymentMethod>
<PaymentMethod>Cash</PaymentMethod>
<PaymentMethod>BankDeposit</PaymentMethod>
</PaymentMethods>';

      $attributes = $this->getTmCategoryAttrs($categoryId);

      if ($attributes && count($attributes)) {
        $xml .= '<Attributes>';

        foreach ($attributes as $attribute) {
          $name = strtolower($attribute['Name']);

          $data = null;

          if ($product->hasData($name))
            $data = $product->getData($name);
          else {
            $name .= '_';

            if ($product->hasData($name))
              $data = $product->getData($name);
          }

          if ($data == null)
            if ($attribute['IsRequiredForSell'])
              return 'Product has empty ' . $name . ' attribute';
            else
              continue;

          $xml .= '<Attribute>';
          $xml .= '<Name>' . $name . '</Name>';
          $xml .= '<Value>' . $data . '</Value>';
          $xml .= '</Attribute>';
        }

        $xml .= '</Attributes>';
      }

      $xml .= '</ListingRequest>';

      $client->setRawData($xml, 'application/xml');
      $response = $client->request();

      $xml = simplexml_load_string($response->getBody());

      if ($xml) {
        if ((string)$xml->Success == 'true') {

          if ($updateTmOptions) {
            $tmData['account_id'] = $this->_accountId;

            Mage::helper('mventory_tm/product')
              ->setTmFields($product, $_tmData);
          }

          $product->setTmCurrentAccountId($this->_accountId);

          $return = (int)$xml->ListingId;
        } elseif ((string)$xml->ErrorDescription) {
          $return = (string)$xml->ErrorDescription;
        } elseif ((string)$xml->Description) {
          $return = (string)$xml->Description;
        }
      }
    }

    return $return;
  }

  public function remove($product) {
    $this->getWebsiteId($product);

    $accountId = Mage::helper('mventory_tm/tm')
                   ->getCurrentAccountId($product->getId());

    $this->setAccountId($accountId);

    if ($accessToken = $this->auth()) {
      $error = false;

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
        }
      }
    }

    return $error;
  }

  public function check ($product) {
    $listingId = $product->getTmCurrentListingId();

    $this->getWebsiteId($product);
    $this->setAccountId($product->getTmCurrentAccountId());

    $json = $this->_loadTmListingDetailsAuth($listingId);

    if (!$json)
      return;

    $item = $this->_parseTmListingDetails($json);

    if (is_string($item)) {
      Mage::log('TM: error on retrieving listing details '
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
    $this->getWebsiteId($product);

    if ($_formData && isset($_formData['account_id']))
      unset($_formData['account_id']);

    $accountId = $product->getTmCurrentAccountId();
    $this->setAccountId($accountId);

    $helper = Mage::helper('mventory_tm/tm');

    $listingId = $product->getTmCurrentListingId();
    $return = 'Error';

    if ($accessToken = $this->auth()) {
      $client = $accessToken->getHttpClient($this->getConfig());

      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling/Edit.json');
      $client->setMethod(Zend_Http_Client::POST);
      $json = $this->_loadTmListingDetails($listingId);

      if (!$json){
        $helper->sendEmail('Unable to retrieve data for TM listing ',
          $return.' product id '.$product->getId().' listing id '.$listingId,
            $this->_website);
        return 'Unable to retrieve data from TM';
      }

      $item = $this->_parseTmListingDetails($json);
      $item = $this->_listingDetailsToEditingRequest($item);

      $formData = $_formData;

      if ($formData)
        foreach ($formData as $key => $value)
          if ($value == -1 && isset($this->_accountData[$key]))
            $formData[$key] = $this->_accountData[$key];

      $shippingType = isset($formData['shipping_type'])
                        ? $formData['shipping_type']
                          : self::UNDECIDED;

      $productShippingType = $helper->getShippingType($product);

      if (!isset($parameters['Category']) && isset($formData['category'])
          && $formData['category'])
        $parameters['Category'] = $formData['category'];

      if (!isset($parameters['Title'])) {
        $title = $product->getName();

        if (strlen($title) > self::TITLE_MAX_LENGTH)
          $title = substr($title, 0, self::TITLE_MAX_LENGTH - 3) . '...';
        elseif ($productShippingType == 'tab_ShipParcel'
                && $shippingType == self::FREE
                && isset($this->_accountData['free_shipping_cost'])
                && $this->_accountData['free_shipping_cost'] > 0) {
          $freeShippingTitle = $title . ', free shipping';

          if (strlen($freeShippingTitle) <= self::TITLE_MAX_LENGTH)
            $title = $freeShippingTitle;
        }

        $parameters['Title'] = $title;
      }

      if (!isset($parameters['ShippingOptions']))
        $parameters['ShippingOptions'][]['Type'] = $shippingType;

      //set price
      if (!isset($parameters['StartPrice'])) {

        $price = $product->getPrice();

        if ($shippingType != self::FREE) {

          //Add shipping rate if product's shipping type is 'tab_ShipTransport'
          if ($productShippingType == 'tab_ShipTransport') {
            $regionName = $this->_accountData['name'];

            $price += $helper->getShippingRate($product,
                                               $regionName,
                                               $this->_website);

            unset($regionName, $website);
          }
        } else {

          //Add free shippih cost if product's shipping type is 'tab_ShipParcel'
          if ($productShippingType == 'tab_ShipParcel'
              && isset($this->_accountData['free_shipping_cost'])
              && $this->_accountData['free_shipping_cost'] > 0) {
            $price += (float) $this->_accountData['free_shipping_cost'];
          }
        }

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
        $descriptionTmpl = $this->_accountData['footer'];

        $description = '';

        if ($descriptionTmpl) {
          //Set current product in Magento registry, it's required by the block
          //which shows product's attributes
          Mage::register('product', $product, true);

          $_data = $product->getData();

          if ($productShippingType == 'tab_ShipFree'
              || ($productShippingType == 'tab_ShipParcel'
                  && $shippingType == self::FREE
                  && isset($this->_accountData['free_shipping_cost'])
                  && $this->_accountData['free_shipping_cost'] > 0))
            $_data['free_shipping_text']
              = isset($this->_accountData['free_shipping_text'])
                  ? $this->_accountData['free_shipping_text']
                    : '';

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
      $item['Duration'] = 7;

      //Set pickup option
      if (!isset($parameters['Pickup']) && isset($formData['pickup']))
        $parameters['Pickup']
          = $this->_getPickup($product, $formData, $this->_accountData);

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
        if ($_formData)
          Mage::helper('mventory_tm/product')
            ->setTmFields($product, $_formData);

        $product->save();

        $return = (int)$jsonResponse->ListingId;
      }
      else {
        if (isset($jsonResponse->Description) && (string)$jsonResponse->Description) {
          $return = (string)$jsonResponse->Description;
        } elseif (isset($jsonResponse->ErrorDescription)
            && (string)$jsonResponse->ErrorDescription) {
            $return = (string)$jsonResponse->ErrorDescription;
        }
        $helper->sendEmail('Unable to update TM listing ',
          $return.' product id '.$product->getId().' listing id '.$listingId,
            $this->_website);
        Mage::log('TM: error on updating TM listing details '
          . $listingId
          );
      }
  	}
  	else{
  	  $helper->sendEmail('Unable to auth TM',
        $return.' product id '.$product->getId().' listing id '.$listingId,
          $this->_website);
  	  Mage::log('TM: Unable to auth when trying to update listing details '
  	    . $listingId
  	    );
  	}
    return $return;
  }

  public function massCheck($products) {
    if (!$accessToken = $this->auth())
      return 0;

    if ($products instanceof Mage_Catalog_Model_Product) {
      $collection = new Varien_Data_Collection();
      $products = $collection->addItem($products);
    }

    $client = $accessToken->getHttpClient($this->getConfig());
    $client->setUri('https://api.' . $this->_host . '.co.nz/v1/MyTradeMe/SellingItems/All.json');
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

    return true;
  }

  public function relist ($product) {
    $this->getWebsiteId($product);

    $accountId = Mage::helper('mventory_tm/tm')
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
      Mage::log('TM: error on relisting '
                . $listingId
                . ' ('
                . $response['Description']
                . ')');

      return false;
    }

    return $response['ListingId'];
  }

  public function uploadImage ($image) {
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
      $msg = 'TM: error on image uploading ('
             . $image
             . '). Error description: '
             . $result['Description'];

      Mage::log($msg);

      Mage::helper('mventory_tm')
        ->sendEmail('Unable to upload image to TM', $msg, $this->_website);

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
              ->getAdditionalData(array(), false);

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

  public function _loadTmCategories () {
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

  public function _loadTmCategoryAttrs ($categoryId) {
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

  public function _loadTmListingDetails ($listingId) {
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

  public function _loadTmListingDetailsAuth ($listingId) {
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

  public function _parseTmCategories (&$list, $categories, $names = array()) {
    foreach ($categories as $category) {
      if (isset($this->_ignoreTmCategories[$category['Number']]))
        continue;

      $_names = array_merge($names, array($category['Name']));

      if (isset($category['Subcategories']))
        $this->_parseTmCategories($list, $category['Subcategories'], $_names);
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

  public function _parseTmListingDetails ($details) {
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

  protected function _getPickup ($product, $data, $account) {
    if (isset($data['pickup'])) {
      $pickup = (int) $data['pickup'];

      if ($pickup == MVentory_Tm_Model_Tm::PICKUP_ALLOW
          || $pickup == MVentory_Tm_Model_Tm::PICKUP_DEMAND
          || $pickup == MVentory_Tm_Model_Tm::PICKUP_FORBID)
        return $pickup;
    }

    if (!isset($account['allow_pickup_for']))
      return MVentory_Tm_Model_Tm::PICKUP_FORBID;

    $allowPickupFor = explode(',', $account['allow_pickup_for']);

    if (!count($allowPickupFor))
      return MVentory_Tm_Model_Tm::PICKUP_FORBID;

    $shippingType = Mage::helper('mventory_tm/tm')
                      ->getShippingType($product, true);

    return in_array($shippingType, $allowPickupFor)
             ? MVentory_Tm_Model_Tm::PICKUP_ALLOW
               : MVentory_Tm_Model_Tm::PICKUP_FORBID;
  }

  protected function _getIsBrandNew ($product) {
    return in_array(
      $product->getData('mv_condition_'),
      explode(',', $this->_getConfig(self::LIST_AS_NEW_PATH))
    );
  }

  public function getTmCategories () {
    $app = Mage::app();

    if ($list = $app->loadCache(self::CACHE_TM_CATEGORIES))
      return unserialize($list);

    $json = $this->_loadTmCategories();

    if (!$json)
      return null;

    $categories = json_decode($json, true);

    unset($json);

    $list = array();

    $this->_parseTmCategories($list, $categories['Subcategories']);

    unset($categories);

    if ($app->useCache(self::CACHE_TYPE_TM))
      $app->saveCache(
        serialize($list),
        self::CACHE_TM_CATEGORIES,
        array(self::CACHE_TAG_TM)
      );

    return $list;
  }

  public function getTmCategoryAttrs ($categoryId) {
    if (!$categoryId)
      return null;

    $app = Mage::app();

    if ($attrs = $app->loadCache(self::CACHE_TM_CATEGORY_ATTRS . $categoryId))
      return unserialize($attrs);

    $json = $this->_loadTmCategoryAttrs($categoryId);

    if (!$json)
      return null;

    $attrs = json_decode($json, true);

    if ($app->useCache(self::CACHE_TYPE_TM))
      $app->saveCache(
        serialize($attrs),
        self::CACHE_TM_CATEGORY_ATTRS . $categoryId,
        array(self::CACHE_TAG_TM)
      );

    return $attrs;
  }

  public function getAttrTypeName ($id) {
    if (isset($this->_attrTypes[$id]))
      return $this->_attrTypes[$id];

    return 'Unknown';
  }
}
