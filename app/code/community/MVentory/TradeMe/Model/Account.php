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
 * TradeMe account model
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Account extends MVentory_Tm_Model_Tm
{
  protected $_accountId = null;
  protected $_website = null;

  protected $_configuration = null;

  function __construct ($accountId, $website) {
    $this->_accountId = $accountId;
    $this->_website = $website;

    $helper = Mage::helper('mventory_tm/tm');

    $host = $helper->getConfig(MVentory_TradeMe_Model_Config::SANDBOX, $website)
              ? 'tmsandbox'
                : 'trademe';

    $siteUrl = 'https://secure.' . $host . '.co.nz/Oauth/';

    $accounts = Mage::helper('trademe')->getAccounts($website);
    $account = $accounts[$accountId];

    $route = 'trademe/account/authorise';
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
      ->setTradeMeRequestToken(serialize($requestToken));

    $params = array('scope' => 'MyTradeMeRead,MyTradeMeWrite');

    return $consumer->getRedirectUrl($params);
  }

  public function authorise ($data) {
    $session = Mage::getSingleton('core/session');

    $requestToken = $session->getTradeMeRequestToken();

    if (!$requestToken)
      return;

    $consumer = new Zend_Oauth_Consumer($this->_configuration);

    $token = $consumer->getAccessToken($data, unserialize($requestToken));

    $data = array(
      Zend_Oauth_Token::TOKEN_PARAM_KEY => $token->getToken(),
      Zend_Oauth_Token::TOKEN_SECRET_PARAM_KEY => $token->getTokenSecret()
    );

    $path = 'trademe/' . $this->_accountId . '/access_token';
    $websiteId = Mage::app()
                   ->getWebsite($this->_website)
                   ->getId();

    Mage::getConfig()
      ->saveConfig($path, serialize($data), 'websites', $websiteId)
      ->reinit();

    Mage::app()->reinitStores();

    $session->setTradeMeRequestToken(null);

    return true;
  }
}
