<?php

class MVentory_Tm_Model_Connector extends Mage_Core_Model_Abstract {

  const ACCESS_TOKEN_PATH = 'mventory_tm/settings/accept_token';
  const KEY_PATH = 'mventory_tm/settings/key';
  const SECRET_PATH = 'mventory_tm/settings/secret';
  const SANDBOX_PATH = 'mventory_tm/settings/sandbox';
  const FOOTER_PATH = 'mventory_tm/settings/footer';
  const BUY_NOW_PATH = 'mventory_tm/settings/allow_buy_now';
  const ADD_TM_FEES_PATH = 'mventory_tm/settings/add_tm_fees';
  const SHIPPING_TYPE_PATH = 'mventory_tm/settings/shipping_type';

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

  private $_helper = null;

  private $_config = null;
  private $_host = 'trademe';
  private $_categories = array();

  private $_website = null;

  private $_attrTypes = array(
    0 => 'None',
    1 => 'Boolean',
    2 => 'Integer',
    3 => 'Decimal',
    4 => 'String',
    5 => 'DateTime',
    6 => 'StayPeriod'
  );

  //Restored original request data, used only after auth process
  private $_requestData = null;

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
      'callbackUrl' => Mage::helper('core/url')->getCurrentUrl(),
      'siteUrl' => 'https://secure.' . $host . '.co.nz/Oauth/',
      'requestTokenUrl'
                     => 'https://secure.' . $host . '.co.nz/Oauth/RequestToken',
      'userAuthorisationUrl'
                        => 'https://secure.' . $host . '.co.nz/Oauth/Authorize',
      'accessTokenUrl'
               => 'https://secure.' . $host . '.co.nz/Oauth/AccessToken',
      'consumerKey' => $this->_getConfig(self::KEY_PATH),
      'consumerSecret' => $this->_getConfig(self::SECRET_PATH)
    );

    $this->_host = $host;

    return $this->_config;
  }

  private function getWebsiteId ($product) {
    $this->_website = $this
                        ->_helper
                        ->getWebsiteIdFromProduct($product);
  }

  private function saveAccessToken ($token = '') {
    $scope = 'default';
    $scopeId = 0;

    if ($this->_website) {
      $scope = 'websites';
      $scopeId = $this->_website;
    }

    Mage::getConfig()
      ->saveConfig(self::ACCESS_TOKEN_PATH, $token, $scope, $scopeId)
      ->reinit();

    Mage::app()->reinitStores();
  }

  public function reset () {
    $this->saveAccessToken();

    Mage::app()->getResponse()->setRedirect(Mage::helper('core/url')->getCurrentUrl());
    Mage::app()->getResponse()->sendResponse();

    exit();
  }

  public function auth () {
    $accessTokenData = $this->_getConfig(self::ACCESS_TOKEN_PATH);

    $request = Mage::app()->getRequest();
    
    $oAuthToken = $request->getParam('oauth_token');

    $session = Mage::getSingleton('core/session');

    $requestToken = $session->getMventoryTmRequestToken();

    if (!$accessTokenData) {
      $oAuth = new Zend_Oauth_Consumer($this->getConfig());

      if ($oAuthToken && $requestToken) {
        try {
          $accessToken= $oAuth->getAccessToken($request->getParams(),
                                                    unserialize($requestToken));

          $accessTokenData = serialize(array($accessToken->getToken(),
                                               $accessToken->getTokenSecret()));

          $this->saveAccessToken($accessTokenData);

          $session->setMventoryTmRequestToken(null);

          //Restore original request data from session to use
          //during interaction with TM
          $this->_requestData
            = $session->getData('original_request_data', true);
        } catch(Exception $e) {

          //Auth failed, so we don't need data stored earlier
          $session->unsetData('original_request_data');
          
          return false;
        }
      } elseif ($request->getParam('denied'))
        return false;
      else
        try {
          $requestToken = $oAuth->getRequestToken();

          $session->setMventoryTmRequestToken(serialize($requestToken));

          //Store original request data in session for using after
          //auth success
          $session->setData('original_request_data', $request->getParams());

          $requestToken = explode('=', str_replace('&', '=', $requestToken));

          $response = Mage::app()->getResponse();

          $response
            ->setRedirect(
              'https://secure.'
              . $this->_host
              . '.co.nz/Oauth/Authorize?scope=MyTradeMeRead,MyTradeMeWrite&oauth_token='
              . $requestToken[1]);

          $response->sendResponse();

          exit;
        } catch(Exception $e) {
          return false;
        }
    }

    return $accessTokenData;
  }

  public function send ($product, $categoryId, $data) {
    $this->getWebsiteId($product);

    $return = 'Error';

    if ($accessTokenData = $this->auth()) {
      $accessTokenData = unserialize($accessTokenData);

      $accessToken = new Zend_Oauth_Token_Access();
      $accessToken->setToken($accessTokenData[0]);
      $accessToken->setTokenSecret($accessTokenData[1]);

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

      if (!$categoryId && $this->_requestData
          && isset($this->_requestData['tm'])) {

        $data = $this->_requestData['tm'];
        $categoryId = $data['category'];
      }

      if (!$categoryId) {
        return 'Product doesn\'t have matched tm category';
      }

      $photoId = null;

      $imagePath = Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product'
                     . $product->getImage();

      if (file_exists($imagePath)) {
        $imagePathInfo = pathinfo($imagePath);

        $signature = md5($this->_getConfig(self::KEY_PATH)
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

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $descriptionTmpl = $this->_getConfig(self::FOOTER_PATH);

      $description = '';

      if ($descriptionTmpl) {
        $description = $this->processDescription($descriptionTmpl, $product);
        $description = htmlspecialchars($description);
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
<Title>' . $product->getName() . '</Title>
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

    if ($accessTokenData = $this->auth()) {
      $error = false;

      $accessTokenData = unserialize($accessTokenData);

      $accessToken = new Zend_Oauth_Token_Access();
      $accessToken->setToken($accessTokenData[0]);
      $accessToken->setTokenSecret($accessTokenData[1]);

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

  public function check($product) {
    $this->getWebsiteId($product);

    if ($accessTokenData = $this->auth()) {
      $error = false;

      $accessTokenData = unserialize($accessTokenData);

      $accessToken = new Zend_Oauth_Token_Access();
      $accessToken->setToken($accessTokenData[0]);
      $accessToken->setTokenSecret($accessTokenData[1]);

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/MyTradeMe/UnsoldItems.xml');
      $client->setMethod(Zend_Http_Client::GET);
      $client->setParameterGet('deleted', 'True');

      $response = $client->request();

      if ($response->getStatus() == '401') {
        $this->reset();
      }

      $xml = simplexml_load_string($response->getBody());

      foreach($xml->List->UnsoldItem as $item) {
        if ($item->ListingId == $product->getTmListingId()) {
          return 1;
        }
      }

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/MyTradeMe/SoldItems.xml');
      $client->setMethod(Zend_Http_Client::GET);
      $client->setParameterGet('deleted', '1');

      $response = $client->request();

      if ($response->getStatus() == '401') {
        $this->reset();
      }

      $xml = simplexml_load_string($response->getBody());

      foreach($xml->List->SoldItem as $item) {
        if ($item->ListingId == $product->getTmListingId()) {
          return 2;
        }
      }

      $client = $accessToken->getHttpClient($this->getConfig());
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/MyTradeMe/SellingItems/All.xml');
      $client->setMethod(Zend_Http_Client::GET);
      $client->setParameterGet('deleted', '1');

      $response = $client->request();

      if ($response->getStatus() == '401') {
        $this->reset();
      }

      $xml = simplexml_load_string($response->getBody());

      foreach($xml->List->Item as $item) {
        if ($item->ListingId == $product->getTmListingId()) {
          return 3;
        }
      }
    }

    return 0;
  }

  public function relist ($product) {
    $this->getWebsiteId($product);

    if (!$listingId = $product->getTmListingId())
      return;

    if (!$accessTokenData = $this->auth())
      return;

    $accessTokenData = unserialize($accessTokenData);

    $accessToken = new Zend_Oauth_Token_Access();

    $accessToken->setToken($accessTokenData[0]);
    $accessToken->setTokenSecret($accessTokenData[1]);

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

    Mage::register('product', $product);

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

  public function _parseTmCategories (&$list, $categories, $names = array()) {
    foreach ($categories as $category) {
      $_names = array_merge($names, array($category['Name']));

      $subCategories = $category['Subcategories'];

      if (is_array($subCategories) && count($subCategories))
        $this->_parseTmCategories($list, $subCategories, $_names);
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
