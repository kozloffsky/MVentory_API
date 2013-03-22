<?php

/**
 * TM controller
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Adminhtml_TmController
  extends Mage_Adminhtml_Controller_Action {

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Return table with TM categories
   *
   * @return null
   */
  public function categoriesAction () {
    $categoryId = (int) $this->getRequest()->getParam('category_id');

    if (!$categoryId) { 
      $productId = (int) $this->getRequest()->getParam('product_id');

      //Get category ID from the product if product ID was sent
      //instead category ID 
      if ($productId) {
        $product = Mage::getModel('catalog/product')->load($productId);

        $categories = $product->getCategoryIds();

        if (count($categories))
          $categoryId = $categories[0];
      }
    }

    $category = $categoryId
                  ? Mage::getModel('catalog/category')->load($categoryId)
                    : null;

    $request = $this->getRequest();

    if (!$type = $request->getParam('type'))
      $type = MVentory_Tm_Block_Categories::TYPE_CHECKBOX;

    $body = $this
              ->getLayout()
              ->createBlock('mventory_tm/categories')
              //Set category in loaded block for futher using
              ->setCategory($category)
              ->setInputType($type)
              ->toHtml();

    $this->getResponse()->setBody($body);
  }

  /**
   * Obtaines a request token and sends URL to authorize endpoint
   *
   * @return null
   */
  public function authenticateAccountAction () {
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

    $auth = new MVentory_Tm_Model_Tm_Auth($accountId, $website);

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
   * Redirects back to TM config page if account is successfully authorized
   *
   * @return null
   */
  public function authorizeAccountAction () {
    $request = $this->getRequest();

    $accountId = $request->getParam('account_id');
    $website = $request->getParam('website');

    $params = array(
      'section' => 'mventory_tm',
      'website' => $website
    );

    if (!$accountId || !$website) {
      Mage::getSingleton('adminhtml/session')
        ->addError($this->__('Account ID or website are not specified'));

      $this->_redirect('adminhtml/system_config/edit', $params);

      return;
    }

    $auth = new MVentory_Tm_Model_Tm_Auth($accountId, $website);

    $result = $auth->authorize($request->getParams());

    if ($result) {
      $accounts = Mage::helper('mventory_tm/tm')->getAccounts($website);
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
