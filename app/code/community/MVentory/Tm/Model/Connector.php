<?php

class MVentory_Tm_Model_Connector extends Mage_Core_Model_Abstract {

  const ACCESS_TOKEN_PATH = 'mventory_tm/settings/accept_token';
  const ACCOUNTS_PATH = 'mventory_tm/settings/accounts';
  const SANDBOX_PATH = 'mventory_tm/settings/sandbox';
  const FOOTER_PATH = 'mventory_tm/settings/footer';
  const BUY_NOW_PATH = 'mventory_tm/settings/allow_buy_now';
  const ADD_TM_FEES_PATH = 'mventory_tm/settings/add_tm_fees';
  const SHIPPING_TYPE_PATH = 'mventory_tm/settings/shipping_type';
  const RELIST_IF_NOT_SOLD_PATH = 'mventory_tm/settings/relist_if_not_sold';
  const AVOID_WITHDRAWAL_PATH = 'mventory_tm/settings/avoid_withdrawal';
  const BUYER_PATH = 'mventory_tm/settings/buyer';

  const CACHE_TYPE_TM = 'tm';
  const CACHE_TAG_TM = 'TM';
  const CACHE_TM_CATEGORIES = 'TM_CATEGORIES';
  const CACHE_TM_CATEGORY_ATTRS = 'TM_CATEGORY_ATTRS';

  //TM shipping types
  const UNKNOWN = 0;
  const NONE = 0;
  const UNDECIDED = 1;
  const PICKUP = 2;
  const FREE = 3;
  const CUSTOM = 4;

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

  protected function _construct () {
    $this->_helper = Mage::helper('mventory_tm');
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
                        ->getWebsiteIdFromProduct($product);
  }

  public function setWebsiteId ($websiteId) {
    $this->_website = $websiteId;
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
  }

  public function reset () {
    $this->saveAccessToken();

    Mage::app()->getResponse()->setRedirect(Mage::helper('core/url')->getCurrentUrl());
    Mage::app()->getResponse()->sendResponse();

    exit();
  }

  public function auth () {
    $path = self::ACCESS_TOKEN_PATH . '_' . $this->_accountId;
    $data = $this->_getConfig($path);

    if (!$data)
      return null;

    $token = new Zend_Oauth_Token_Access();

    return $token->setParams(unserialize($data));
  }

  public function send ($product, $categoryId, $data) {
    $this->getWebsiteId($product);
    $this->setAccountId($data);

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

      Mage::register('product', $product);

      $descriptionTmpl = $this->_getConfig(self::FOOTER_PATH);

      $description = '';

      if ($descriptionTmpl) {
        $description = $this->processDescription($descriptionTmpl, $product);
        $description = htmlspecialchars($description);
      }

      if (strlen($description) > self::DESCRIPTION_MAX_LENGTH)
        return 'Length of the description exceeded the limit of '
               .  self::DESCRIPTION_MAX_LENGTH
               . ' characters';

      $photoId = null;

      $imagePath = $product->getImage();

      if ($imagePath && $imagePath != 'no_selection') {
        $imagePath = Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product'
                       . $imagePath;

        $downloadResult = Mage::helper('mventory_tm/s3')
                            ->download($imagePath, $this->_website);

        if (!$downloadResult)
          return 'Downloading image from S3 failed';

        if (file_exists($imagePath)) {
          $imagePathInfo = pathinfo($imagePath);

          $signature = md5($this->_accountData['key']
                           . $imagePathInfo['filename']
                           . $imagePathInfo['extension']
                           . 'False'
                           . 'False');

          $xml = '<PhotoUploadRequest xmlns="http://api.trademe.co.nz/v1">
  <Signature>' . $signature . '</Signature>
  <PhotoData>' . base64_encode(file_get_contents($imagePath)) . '</PhotoData>
  <FileName>' . $imagePathInfo['filename'] . '</FileName>
  <FileType>' . $imagePathInfo['extension'] . '</FileType>
  <IsWaterMarked>' . 0 . '</IsWaterMarked>
  <IsUsernameAdded>' . 0 . '</IsUsernameAdded>
  </PhotoUploadRequest>';

          $client = $accessToken->getHttpClient($this->getConfig());
          $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Photos.xml');
          $client->setMethod(Zend_Http_Client::POST);
          $client->setRawData($xml, 'application/xml');

          $response = $client->request();

          if ($response->getStatus() == '401') {
            $this->reset();
          }

          $startPos = strpos($response->getBody(), '<');
          $endPos = strrpos($response->getBody(), '>') + 1;
          $xml = simplexml_load_string(substr($response->getBody(), $startPos, $endPos - $startPos));

          if ($xml) {
            if ((string)$xml->Status == 'Success') {
              $photoId = (int)$xml->PhotoId;
            }
          }
        }
      }

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $title = $product->getName();

      if (strlen($title) > self::TITLE_MAX_LENGTH) {
        $title = substr($title, 0, self::TITLE_MAX_LENGTH - 3) . '...';
      }

      $tmHelper = Mage::helper('mventory_tm/tm');

      //Apply fees to price of the product if it's allowed
      $price = isset($data['add_tm_fees']) && $data['add_tm_fees']
                  ? $tmHelper->addFees($product->getPrice())
                    : $product->getPrice();

      unset($tmHelper);

      $buyNow = '';

      if (isset($data['allow_buy_now']) && $data['allow_buy_now'])
        $buyNow = '<BuyNowPrice>' . $price . '</BuyNowPrice>';

      $shippingTypes
        = Mage::getModel('mventory_tm/system_config_source_shippingtype')
            ->toArray();

      $shippingType = isset($data['shipping_type'])
                        ? $data['shipping_type']
                          : self::UNKNOWN;
      $shippingType
        = $shippingTypes[$shippingType];

      unset($shippingTypes);

      $xml = '<ListingRequest xmlns="http://api.trademe.co.nz/v1">
<Category>' . $categoryId . '</Category>
<Title>' . $title . '</Title>
<Description><Paragraph>' . $description . '</Paragraph></Description>
<StartPrice>' . $price . '</StartPrice>
<ReservePrice>' . $price . '</ReservePrice>'
. $buyNow .
'<Duration>Seven</Duration>
<Pickup>Allow</Pickup>';

      if ($photoId) {
        $xml .= '<PhotoIds><PhotoId>' . $photoId . '</PhotoId></PhotoIds>';
      }

      $xml .= '<ShippingOptions>
<ShippingOption><Type>' . $shippingType . '</Type></ShippingOption>
</ShippingOptions>
<SendPaymentInstructions>1</SendPaymentInstructions>
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

      if ($response->getStatus() == '401') {
        $this->reset();
      }

      $xml = simplexml_load_string($response->getBody());

      if ($xml) {
        if ((string)$xml->Success == 'true') {
          $product
            ->setTmRelist($data['relist'])
            ->setTmAccountId($this->_accountId);

          if (isset($data['avoid_withdrawal']))
          {
            $product
              ->setTmAvoidWithdrawal($data['avoid_withdrawal']);
          }

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
                   ->getAccountId($product->getId(), $this->_website);

    $this->setAccountId($accountId);

    if ($accessToken = $this->auth()) {
      $error = false;

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling/Withdraw.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $xml = '<WithdrawRequest xmlns="http://api.trademe.co.nz/v1">
<ListingId>' . $product->getTmListingId() . '</ListingId>
<Type>ListingWasNotSold</Type>
<Reason>Withdraw</Reason>
</WithdrawRequest>';

      $client->setRawData($xml, 'application/xml');
      $response = $client->request();

      if ($response->getStatus() == '401') {
        $this->reset();
      }

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
    $listingId = $product->getTmListingId();

    $this->getWebsiteId($product);
    $this->setAccountId($product->getTmAccountId());

    $json = $this->_loadTmListingDetailsAuth($listingId);

    if (!$json)
      return 'Error';

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

  public function update($product,$parameters=null,$formData=null){
    $tmHelper = Mage::helper('mventory_tm/tm');
    $helper = Mage::helper('mventory_tm');

    $this->getWebsiteId($product);

    $accountId = $product->getTmAccountId();
    $this->setAccountId($accountId);

    $listingId = $product->getTmListingId();
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

      if(!isset($parameters['Title'])) $parameters['Title'] = $product->getName();

      //set price
      if(!isset($parameters['StartPrice']))
        $parameters['StartPrice'] = isset($formData['add_tm_fees']) && $formData['add_tm_fees']
          ? $tmHelper->addFees($product->getPrice())
          : $product->getPrice();
      if(!isset($parameters['ReservePrice']))
        $parameters['ReservePrice'] = $parameters['StartPrice'];
      if(!isset($parameters['BuyNowPrice']) && ((isset($formData['allow_buy_now'])
        && $formData['allow_buy_now'])) || isset($item['BuyNowPrice']))
          $parameters['BuyNowPrice'] = $parameters['StartPrice'];

      //set description
      if(!isset($parameters['Description'])) {
        $descriptionTmpl = $this->_getConfig(self::FOOTER_PATH);

        $description = '';

        if ($descriptionTmpl) {
          //Set current product in Magento registry, it's required by the block
          //which shows product's attributes
          Mage::register('product', $product, true);

          $description = $this->processDescription($descriptionTmpl, $product);
          $description = htmlspecialchars($description);
        }
        $parameters['Description'] = array($description);
      }
      else {
        $parameters['Description'] = array($parameters['Description']);
      }
      //set Duration
      $item['Duration'] = 7;

      //set pickup option
      //  None = 0
      //  Allow = 1
      //  Demand = 2
      //  Forbid = 3
      $item['Pickup'] = 1;

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
    $client->setParameterGet('rows', count($products));

    $response = $client->request();

    if ($response->getStatus() == 401) {
      $this->reset();

      return;
    }

    if ($response->getStatus() != 200)
      return;

    $items = json_decode($response->getBody(), true);

    foreach ($products as $product)
      foreach ($items['List'] as $item)
        if ($item['ListingId'] == $product->getTmListingId())
          $product->setIsSelling(true);

    return true;
  }

  public function relist ($product) {
    $this->getWebsiteId($product);

    $accountId = Mage::helper('mventory_tm/tm')
                   ->getAccountId($product->getId(), $this->_website);

    $this->setAccountId($accountId);

    if (!$listingId = $product->getTmListingId())
      return;

    if (!$accessToken = $this->auth())
      return;

    $client = $accessToken->getHttpClient($this->getConfig());

    $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling/Relist.json');
    $client->setMethod(Zend_Http_Client::POST);

    $data = array('ListingId' => $listingId);

    $client->setRawData(Zend_Json::encode($data), 'application/json');

    $response = $client->request();

    if ($response->getStatus() == '401') {
      $this->reset();

      return;
    }

    if ($response->getStatus() != 200)
      return;

    $response = Zend_Json::decode($response->getBody());

    if (!$response['Success']) {
      Mage::log('TM: error on relisting '
                . $listingId
                . ' ('
                . $response['Description']
                . ')');

      return null;
    }

    return $response['ListingId'];
  }

  private function processDescription ($template, $product) {
    $search = array();
    $replace = array();

    $search[] = '{{url}}';
    $replace[] = rtrim($this->_getConfig('web/unsecure/base_url'), '/')
                 . '/'
                 . Mage::getModel('core/url')
                     ->setRouteParams(array('sku' => $product->getSku()))
                     ->getRoutePath();

    $shortDescription = $product->getShortDescription();

    $search[] = '{{sd}}';
    $replace[] = $shortDescription && strlen($shortDescription) > 5
                   ? $shortDescription
                     : '';

    $fullDescription = $product->getDescription();

    $search[] = '{{fd}}';
    $replace[] = $fullDescription && strlen($fullDescription) > 5
                   ? $fullDescription
                     : '';

    $_attrs = Mage::app()
              ->getLayout()
              ->getBlockSingleton('mventory_tm/product_view_attributes')
              ->getAdditionalData();

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

    if ($response->getStatus() == '401') {
      $this->reset();

      return;
    }

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

  private function _prepareTimestamp ($data) {
    return substr($data, 6, -2) / 1000;
  }

  public function getTmCategories () {
    $cache = Mage::getSingleton('core/cache');

    if ($list = $cache->load(self::CACHE_TM_CATEGORIES))
      return unserialize($list);

    $json = $this->_loadTmCategories();

    if (!$json)
      return null;

    $categories = json_decode($json, true);

    unset($json);

    $list = array();

    $this->_parseTmCategories($list, $categories['Subcategories']);

    unset($categories);

    if ($cache->canUse(self::CACHE_TYPE_TM))
      $cache->save(serialize($list),
                   self::CACHE_TM_CATEGORIES,
                   array(self::CACHE_TAG_TM));

    return $list;
  }

  public function getTmCategoryAttrs ($categoryId) {
    if (!$categoryId)
      return null;

    $cache = Mage::getSingleton('core/cache');

    if ($attrs = $cache->load(self::CACHE_TM_CATEGORY_ATTRS . $categoryId))
      return unserialize($attrs);

    $json = $this->_loadTmCategoryAttrs($categoryId);

    if (!$json)
      return null;

    $attrs = json_decode($json, true);

    if ($cache->canUse(self::CACHE_TYPE_TM))
      $cache->save(serialize($attrs),
                   self::CACHE_TM_CATEGORY_ATTRS . $categoryId,
                   array(self::CACHE_TAG_TM));

    return $attrs;
  }

  public function getAttrTypeName ($id) {
    if (isset($this->_attrTypes[$id]))
      return $this->_attrTypes[$id];

    return 'Unknown';
  }
}
