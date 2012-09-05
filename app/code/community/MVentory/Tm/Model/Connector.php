<?php

class MVentory_Tm_Model_Connector extends Mage_Core_Model_Abstract {

  const ACCESS_TOKEN_PATH = 'mventory_tm/settings/accept_token';
  const KEY_PATH = 'mventory_tm/settings/key';
  const SECRET_PATH = 'mventory_tm/settings/secret';
  const SANDBOX_PATH = 'mventory_tm/settings/sandbox';
  const FOOTER_PATH = 'mventory_tm/settings/footer';
  const BUY_NOW_PATH = 'mventory_tm/settings/allow_buy_now';

  const CACHE_TAG_TM = 'TM';
  const CACHE_TM_CATEGORIES = 'TM_CATEGORIES';

  private $_helper = null;

  private $_config = null;
  private $_host = 'trademe';
  private $_categories = array();

  private $_website = null;

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
        } catch(Exception $e) {
          return false;
        }
      } elseif ($request->getParam('denied'))
        return false;
      else
        try {
          $requestToken = $oAuth->getRequestToken();

          $session->setMventoryTmRequestToken(serialize($requestToken));

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

  public function send ($product, $categoryId) {
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

      if ($descriptionTmpl)
        $description = $this->processDescription($descriptionTmpl, $product);

      $buyNow = '';

      if ((bool) $this->_getConfig(self::BUY_NOW_PATH))
        $buyNow = '<BuyNowPrice>' . $product->getPrice() . '</BuyNowPrice>';

      $xml = '<ListingRequest xmlns="http://api.trademe.co.nz/v1">
<Category>' . $categoryId . '</Category>
<Title>' . $product->getName() . '</Title>
<Description><Paragraph>' . $description . '</Paragraph></Description>
<StartPrice>' . $product->getPrice() . '</StartPrice>
<ReservePrice>' . $product->getPrice() . '</ReservePrice>'
. $buyNow .
'<Duration>Seven</Duration>
<Pickup>Allow</Pickup>';

      if ($photoId) {
        $xml .= '<PhotoIds><PhotoId>' . $photoId . '</PhotoId></PhotoIds>';
      }

      $xml .= '<ShippingOptions>
<ShippingOption><Type>Undecided</Type></ShippingOption>
</ShippingOptions>
<PaymentMethods>
<PaymentMethod>CreditCard</PaymentMethod>
<PaymentMethod>Cash</PaymentMethod>
<PaymentMethod>BankDeposit</PaymentMethod>
</PaymentMethods>';

      $cache = Mage::getSingleton('core/cache');
      $xmlAttr = $cache->load('mventory_tm_attributes_' . $categoryId);

      if (!$xmlAttr) {
        $xmlAttr = file_get_contents('http://api.trademe.co.nz/v1/Categories/' . $categoryId . '/Attributes.xml');
        $cache->save($xmlAttr, 'mventory_tm_attributes_' . $categoryId, array('mventory_tm_attributes_' . $categoryId), 9999999999);
      }

      $xmlAttr = simplexml_load_string($xmlAttr);

      if ($xmlAttr) {
        if (count($xmlAttr)) {
          $xml .= '<Attributes>';

          foreach ($xmlAttr as $attribute) {
            if (!$product->getData(strtolower($attribute->Name)) && !$product->getData(strtolower($attribute->Name . '_'))) {
              return 'Product has empty ' . strtolower($attribute->Name) . ' attribute';
            }

            $xml .= '<Attribute>';
            $xml .= '<Name>' . $attribute->Name . '</Name>';
            $xml .= '<Value>' . ($product->getData(strtolower($attribute->Name)) ? $product->getData(strtolower($attribute->Name)) : $product->getData(strtolower($attribute->Name . '_')))  . '</Value>';
            $xml .= '</Attribute>';
          }

          $xml .= '</Attributes>';
        }
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

  public function _parseTmCategories (&$list, $categories, $names = array()) {
    foreach ($categories as $category) {
      $_names = array_merge($names, array($category['Name']));

      $subCategories = $category['Subcategories'];

      if (is_array($subCategories) && count($subCategories))
        $this->_parseTmCategories($list, $subCategories, $_names);
      else {
        $id = explode('-', $category['Number']);
        $id = (int) $id[count($id) - 2];

        $list[$id] = $_names;
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

    $cache
      ->save(serialize($list),
             self::CACHE_TM_CATEGORIES,
             array(self::CACHE_TAG_TM));

    return $list;
  }
}
