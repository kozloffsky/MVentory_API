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
 * TradeMe account controller
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_AccountController
  extends Mage_Adminhtml_Controller_Action
{
  protected function _construct() {
    $this->setUsedModuleName('MVentory_TradeMe');
  }

  /**
   * Obtaines a request token and sends URL to authorize endpoint
   *
   * @return null
   */
  public function authenticateAction () {
    $accountId = $this->getRequest()->getParam('account_id');
    $website = $this->getRequest()->getParam('website');

    if (!$accountId || !$website) {
      $body = array(
        'error' => true,
        'message' => $this->__('Account ID or website are not specified')
      );

      $body = Mage::helper('core')->jsonEncode($body);

      $this
        ->getResponse()
        ->setBody($body);

      return;
    }

    $auth = new MVentory_TradeMe_Model_Account($accountId, $website);

    $ajaxRedirect = $auth->authenticate();

    if ($ajaxRedirect)
      $body = compact('ajaxRedirect');
    else
      $body = array(
        'error' => true,
        'message'
          => $this->__('An error occurred while obtaining request token')
      );

    $body = Mage::helper('core')->jsonEncode($body);

    $this
      ->getResponse()
      ->setBody($body);

    return;
  }

  /**
   * Action for oAuth callback URL.
   * Redirects back to TradeMe config page if account is successfully authorised
   *
   * @return null
   */
  public function authoriseAction () {
    $request = $this->getRequest();

    $accountId = $request->getParam('account_id');
    $website = $request->getParam('website');

    $params = array(
      'section' => 'trademe',
      'website' => $website
    );

    if (!$accountId || !$website) {
      Mage::getSingleton('adminhtml/session')
        ->addError($this->__('Account ID or website are not specified'));

      $this->_redirect('adminhtml/system_config/edit', $params);

      return;
    }

    $auth = new MVentory_TradeMe_Model_Account($accountId, $website);

    $result = $auth->authorise($request->getParams());

    if ($result) {
      $accounts = Mage::helper('trademe')->getAccounts($website);
      $accountName = $accounts[$accountId]['name'];

      $message = '"' . $accountName . '" account is successfully authorized';

      Mage::getSingleton('adminhtml/session')->addSuccess($this->__($message));
    }
    else {
      $message = 'An error occurred while obtaining authorized Access Token';
      Mage::getSingleton('adminhtml/session')->addError($this->__($message));
    }

    $this->_redirect('adminhtml/system_config/edit', $params);
  }
}
