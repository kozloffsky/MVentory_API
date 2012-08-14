<?php

class MVentory_Tm_Model_Connector extends Mage_Core_Model_Abstract {

  const ACCESS_TOKEN_PATH = 'mventory_tm/settings/accept_token';
  const KEY_PATH = 'mventory_tm/settings/key';
  const SECRET_PATH = 'mventory_tm/settings/secret';
  const SANDBOX_PATH = 'mventory_tm/settings/sandbox';
  const FOOTER_PATH = 'mventory_tm/settings/footer';

  private $_config = array();
  private $_host = 'trademe';
  private $_categories = array();

  public function _construct () {
    $this->_host = Mage::getStoreConfig(self::SANDBOX_PATH)
                     ? 'tmsandbox'
                       : 'trademe';

    $this->_config = array(
      'requestScheme' => Zend_Oauth::REQUEST_SCHEME_HEADER,
      'version' => '1.0',
      'signatureMethod' => 'HMAC-SHA1',
      'callbackUrl' => Mage::helper('core/url')->getCurrentUrl(),
      'siteUrl' => 'https://secure.' . $this->_host . '.co.nz/Oauth/',
      'requestTokenUrl'
              => 'https://secure.' . $this->_host . '.co.nz/Oauth/RequestToken',
      'userAuthorisationUrl'
                 => 'https://secure.' . $this->_host . '.co.nz/Oauth/Authorize',
      'accessTokenUrl'
               => 'https://secure.' . $this->_host . '.co.nz/Oauth/AccessToken',
      'consumerKey' => Mage::getStoreConfig(self::KEY_PATH),
      'consumerSecret' => Mage::getStoreConfig(self::SECRET_PATH)
    );
  }

  public function reset () {
    Mage::getConfig()->saveConfig(self::ACCESS_TOKEN_PATH, '');
    Mage::getConfig()->reinit();
    Mage::app()->reinitStores();

    Mage::app()->getResponse()->setRedirect(Mage::helper('core/url')->getCurrentUrl());
    Mage::app()->getResponse()->sendResponse();

    exit();
  }

  public function auth () {
    $accessTokenData = Mage::getStoreConfig(self::ACCESS_TOKEN_PATH);

    $request = Mage::app()->getRequest();
    
    $oAuthToken = $request->getParam('oauth_token');

    $session = Mage::getSingleton('core/session');

    $requestToken = $session->getMventoryTmRequestToken();

    if (!$accessTokenData) {
      $oAuth = new Zend_Oauth_Consumer($this->_config);

      if ($oAuthToken && $requestToken) {
        try {
          $accessToken= $oAuth->getAccessToken($request->getParams(),
                                                    unserialize($requestToken));

          $accessTokenData = serialize(array($accessToken->getToken(),
                                               $accessToken->getTokenSecret()));

          Mage::getConfig()
                        ->saveConfig(self::ACCESS_TOKEN_PATH, $accessTokenData);

          Mage::getConfig()->reinit();
          Mage::app()->reinitStores();

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

  public function send ($product) {
    $return = 'Error';

    if ($accessTokenData = $this->auth()) {
      $accessTokenData = unserialize($accessTokenData);

      $accessToken = new Zend_Oauth_Token_Access();
      $accessToken->setToken($accessTokenData[0]);
      $accessToken->setTokenSecret($accessTokenData[1]);

      $categories = Mage::getModel('catalog/category')->getCollection()
          ->addAttributeToSelect('mventory_tm_category')
          ->addFieldToFilter('entity_id', array('in' => $product->getCategoryIds()));

      $categoryId = null;

      foreach ($categories as $category) {
        if ($category->getMventoryTmCategory()) {
          $categoryId = $category->getMventoryTmCategory();
          break;
        }
      }

      if (!$categoryId) {
        return 'Product doesn\'t have matched tm category';
      }

      $photoId = null;

      $imagePath = Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product'
                     . $product->getImage();

      if (file_exists($imagePath)) {
        $imagePathInfo = pathinfo($imagePath);

        $signature = md5(Mage::getStoreConfig(self::KEY_PATH)
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

        $client = $accessToken->getHttpClient($this->_config);
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

      $client = $accessToken->getHttpClient($this->_config);
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $xml = '<ListingRequest xmlns="http://api.trademe.co.nz/v1">
<Category>' . $categoryId . '</Category>
<Title>' . $product->getName() . '</Title>
<Description><Paragraph>' . $product->getDescription() . ' ' . Mage::getStoreConfig(self::FOOTER_PATH) . ' ' . Mage::getBaseUrl() . $product->getUrlPath() . '</Paragraph></Description>
<StartPrice>' . $product->getPrice() . '</StartPrice>
<ReservePrice>' . $product->getPrice() . '</ReservePrice>
<BuyNowPrice>' . $product->getPrice() . '</BuyNowPrice>
<Duration>Seven</Duration>
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
    if ($accessTokenData = $this->auth()) {
      $error = false;

      $accessTokenData = unserialize($accessTokenData);

      $accessToken = new Zend_Oauth_Token_Access();
      $accessToken->setToken($accessTokenData[0]);
      $accessToken->setTokenSecret($accessTokenData[1]);

      $client = $accessToken->getHttpClient($this->_config);
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/Selling/Withdraw.xml');
      $client->setMethod(Zend_Http_Client::POST);

      $xml = '<WithdrawRequest xmlns="http://api.trademe.co.nz/v1">
<ListingId>' . $product->getMventoryTmId() . '</ListingId>
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
    if ($accessTokenData = $this->auth()) {
      $error = false;

      $accessTokenData = unserialize($accessTokenData);

      $accessToken = new Zend_Oauth_Token_Access();
      $accessToken->setToken($accessTokenData[0]);
      $accessToken->setTokenSecret($accessTokenData[1]);

      $client = $accessToken->getHttpClient($this->_config);
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/MyTradeMe/UnsoldItems.xml');
      $client->setMethod(Zend_Http_Client::GET);
      $client->setParameterGet('deleted', 'True');

      $response = $client->request();

      if ($response->getStatus() == '401') {
        $this->reset();
      }

      $xml = simplexml_load_string($response->getBody());

      foreach($xml->List->UnsoldItem as $item) {
        if ($item->ListingId == $product->getMventoryTmId()) {
          return 1;
        }
      }

      $client = $accessToken->getHttpClient($this->_config);
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/MyTradeMe/SoldItems.xml');
      $client->setMethod(Zend_Http_Client::GET);
      $client->setParameterGet('deleted', '1');

      $response = $client->request();

      if ($response->getStatus() == '401') {
        $this->reset();
      }

      $xml = simplexml_load_string($response->getBody());

      foreach($xml->List->SoldItem as $item) {
        if ($item->ListingId == $product->getMventoryTmId()) {
          return 2;
        }
      }

      $client = $accessToken->getHttpClient($this->_config);
      $client->setUri('https://api.' . $this->_host . '.co.nz/v1/MyTradeMe/SellingItems/All.xml');
      $client->setMethod(Zend_Http_Client::GET);
      $client->setParameterGet('deleted', '1');

      $response = $client->request();

      if ($response->getStatus() == '401') {
        $this->reset();
      }

      $xml = simplexml_load_string($response->getBody());

      foreach($xml->List->Item as $item) {
        if ($item->ListingId == $product->getMventoryTmId()) {
          return 3;
        }
      }
    }

    return 0;
  }
}
