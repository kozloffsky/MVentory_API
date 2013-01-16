<?php

class MVentory_Tm_Model_Tm_Auth extends MVentory_Tm_Model_Tm {

  protected $_accountId = null;
  protected $_website = null;

  protected $_configuration = null;

  function __construct ($accountId, $website) {
    $this->_accountId = $accountId;
    $this->_website = $website;

    $helper = Mage::helper('mventory_tm/tm');

    $host = $helper->getConfig(self::SANDBOX_PATH, $website)
              ? 'tmsandbox'
                : 'trademe';

    $siteUrl = 'https://secure.' . $host . '.co.nz/Oauth/';

    $accounts = $helper->getAccounts($website);
    $account = $accounts[$accountId];

    $route = 'mventory_tm/adminhtml_tm/authorizeaccount';
    $params = array(
      'account_id' => $accountId,
      'website' => $website);

    $this->_configuration = array(
      'requestScheme' => Zend_Oauth::REQUEST_SCHEME_HEADER,
      'version' => '1.0',
      'signatureMethod' => 'HMAC-SHA1',
      'siteUrl' => $siteUrl,
      'requestTokenUrl' => $siteUrl . 'RequestToken',
      'authorizeUrl' => $siteUrl . 'Authorize',
      'accessTokenUrl' => $siteUrl . 'AccessToken',
      'consumerKey' => $account['key'],
      'consumerSecret' => $account['secret'],
      'callbackUrl' => Mage::helper('adminhtml')->getUrl($route, $params)
    );
  }

  public function authenticate () {
    $consumer = new Zend_Oauth_Consumer($this->_configuration);

    $requestToken = $consumer->getRequestToken();

    if (!$requestToken->isValid())
      return null;

    Mage::getSingleton('core/session')
      ->setTmRequestToken(serialize($requestToken));

    $params = array('scope' => 'MyTradeMeRead,MyTradeMeWrite');

    return $consumer->getRedirectUrl($params);
  }

  public function authorize ($data) {
    $session = Mage::getSingleton('core/session');

    $requestToken = $session->getTmRequestToken();

    if (!$requestToken)
      return;

    $consumer = new Zend_Oauth_Consumer($this->_configuration);

    $token = $consumer->getAccessToken($data, unserialize($requestToken));

    $data = array(
      Zend_Oauth_Token::TOKEN_PARAM_KEY => $token->getToken(),
      Zend_Oauth_Token::TOKEN_SECRET_PARAM_KEY => $token->getTokenSecret()
    );

    $path = 'mventory_tm/' . $this->_accountId . '/access_token';
    $websiteId = Mage::app()
                   ->getWebsite($this->_website)
                   ->getId();

    Mage::getConfig()
      ->saveConfig($path, serialize($data), 'websites', $websiteId)
      ->reinit();

    Mage::app()->reinitStores();

    $session->setTmRequestToken(null);

    return true;
  }
}
