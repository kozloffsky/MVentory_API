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
 * TradeMe tab
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Block_Tab
  extends Mage_Adminhtml_Block_Widget
  implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
  const URL = 'http://www.trademe.co.nz';

  private $_helper = null;
  private $_website = null;
  private $_preselectedCategories = null;

  //TradeMe options from the session
  private $_session = null;

  private $_accounts = null;
  private $_accountId = null;

  public function __construct() {
    parent::__construct();

    $product = $this->getProduct();

    $this->_helper = Mage::helper('mventory_tm/product');
    $this->_website = $this->_helper->getWebsite($product);

    $productId = $product->getId();

    //Get TradeMe parameters from the session
    $session = Mage::getSingleton('adminhtml/session');

    $this->_session = $session->getData('trademe_data');
    $session->unsetData('trademe_data');

    $trademe = Mage::helper('trademe');

    $this->_accountId = isset($this->_session['account_id'])
                          ? $this->_session['account_id']
                            : $trademe
                                ->getAccountId($productId, $this->_website);

    $this->_accounts = $trademe->prepareAccounts(
      $trademe->getAccounts($this->_website),
      $product
    );

    foreach ($this->_accounts as $id => $account)
      if ($id && !isset($account['shipping_type']))
        unset($this->_accounts[$id]);

    if (!$this->_accountId)
      $this->_accountId = false;

    if (count($this->_accounts)) {
      //if ($this->_helper->getShippingType($product) != 'tab_ShipParcel')
        foreach ($this->_accounts as $id => $data)
          unset($this->_accounts[$id]['free_shipping_cost']);

      $this->_calculateShippingRates();
      $this->_calculateFees();
    }
  }

  /**
   * Return Tab label
   *
   * @return string
   */
  public function getTabLabel() {
    return $this->__('TradeMe');
  }

  /**
   * Return Tab title
   *
   * @return string
   */
  public function getTabTitle() {
    return $this->__('TradeMe');
  }

  /**
   * Can show tab in tabs
   *
   * @return boolean
   */
  public function canShowTab() {
    return true;
  }

  /**
   * Tab is hidden
   *
   * @return boolean
   */
  public function isHidden() {
    return false;
  }

  public function getProduct () {
    return Mage::registry('current_product');
  }

  public function getCategories () {
    return (new MVentory_TradeMe_Model_Api())->getCategories();
  }

  public function getPreselectedCategories () {
    if ($this->_preselectedCategories)
      return $this->_preselectedCategories;

    $this->_preselectedCategories = array();

    $matchResult = Mage::getModel('trademe/matching')
      ->matchCategory($this->getProduct());

    if (isset($matchResult['id']) && $matchResult['id'] > 0) {
      $this->_preselectedCategories[] = $matchResult['id'];
      $this->setCategory($this->_preselectedCategories[0]);
    }

    if (isset($this->_session['category'])
        && ($id = $this->_session['category'])
        && !in_array($id, $this->_preselectedCategories)) {

      $categories = $this->getCategories();

      if (isset($categories[$id])) {
        $this->_preselectedCategories[] = $id;
        $this->setCategory($id);
      }
    }

    return $this->_preselectedCategories;
  }

  public function getColsNumber () {
    if (!$categories = $this->getCategories())
      return 0;

    $cols = 0;

    foreach ($categories as $category)
      if (count($category['name']) > $cols)
        $cols = count($category['name']);

    return $cols;
  }

  public function getUrlTemplates () {
    $productId = $this
                   ->getProduct()
                   ->getId();

    $submit = $this->getUrl('trademe/listing/submit/',
                            array('id' => $productId));

    $categories = $this->getUrl('trademe/categories',
                                array('product_id' => $productId));
    $update = $this->getUrl('trademe/listing/update/',
                                array('id' => $productId));

    return Zend_Json::encode(compact('submit', 'categories','update'));
  }

  public function getSubmitButton () {
    $enabled = count($this->getPreselectedCategories()) > 0;

    $label = $this->__('Submit');
    $class = $enabled ? '' : 'disabled';

    return $this->getButtonHtml($label, null, $class, 'trademe-submit');
  }

  public function getStatusButton () {
    $label = $this->__('Check status');
    $onclick = 'setLocation(\''
               . $this->getUrl('trademe/listing/check/',
                               array('id' => $this->getProduct()->getId()))
               . '\')';

    return $this->getButtonHtml($label, $onclick, '', 'tm_status_button');
  }

  public function getRemoveButton () {
    $label = $this->__('Remove');
    $onclick = 'setLocation(\''
               . $this->getUrl('trademe/listing/remove/',
                               array('id' => $this->getProduct()->getId()))
               . '\')';

    return $this->getButtonHtml($label, $onclick, '', 'tm_remove_button');
  }

  public function getUpdateButton () {
    $label = $this->__('Update');

    return $this->getButtonHtml($label, null, '', 'trademe-update');
  }

  public function getCategoriesButton () {
    $label = $this->__('Show all categories');

    return $this->getButtonHtml($label, null, '', 'trademe-categories-show');
  }

  public function getPreparedAttributes ($categoryId) {
    $attributes = Mage::helper('trademe')->getAttributes($categoryId);

    if (!$attributes)
      return;

    $product = $this->getProduct();

    $existing = array();
    $missing = array();
    $optional = array();

    foreach ($attributes as $attribute) {
      if (!(isset($attribute['IsRequiredForSell'])
            && $attribute['IsRequiredForSell'])) {
        $optional[] = $attribute;

        continue;
      }

      $name = strtolower($attribute['Name']);

      if ($product->hasData($name) || $product->hasData($name . '_'))
        $existing[] = $attribute;
      else
        $missing[] = $attribute;
    }

    return compact('existing', 'missing', 'optional');
  }

  public function getAllowBuyNow () {
    $attr = 'tm_allow_buy_now';
    $field = 'allow_buy_now';

    return (int) $this->_getAttributeValue($attr, $field);
  }

  public function getAddFees () {
    return (int) $this->_getAttributeValue('tm_add_fees', 'add_fees');
  }

  public function getRelist () {
    return (int) $this->_getAttributeValue('tm_relist', 'relist');
  }

  public function getAvoidWithdrawal () {
    $attr = 'tm_avoid_withdrawal';
    $field = 'avoid_withdrawal';

    return (int) $this->_getAttributeValue($attr, $field);
  }

  public function getShippingType () {
    $attr = 'tm_shipping_type';
    $field = 'shipping_type';

    return (int) $this->_getAttributeValue($attr, $field);
  }

  public function getPickup () {
    $attr = 'tm_pickup';
    $field = 'pickup';

    return (int) $this->_getAttributeValue($attr, $field);
  }

  public function getAccounts () {
    $_accounts = array();

    foreach ($this->_accounts as $id => $account)
      $_accounts[] = array(
        'value' => $id,
        'label' => $account['name'],
        'selected' => $this->_accountId === $id
      );

    return $_accounts;
  }

  protected function _getAttributeValue ($code, $field = null) {
    $sources = array();

    if ($field)
      $sources[$field] = $this->_session;

    $sources[$code] = $this->getProduct()->getData();

    foreach ($sources as $key => $source)
      if (isset($source[$key])) {
        $value = $source[$key];

        if ($value === null)
          return -1;

        return $value;
      }

    return -1;
  }

  public function getShippingRate () {
    if (!isset($this->_accounts[$this->_accountId]))
      return null;

    $account = $this->_accounts[$this->_accountId];

    //$shippingType = $this->getShippingType();

    //if ($shippingType == -1 || $shippingType == null)
    //  $shippingType = $account['shipping_type'];

    //if ($shippingType == MVentory_TradeMe_Model_Config::SHIPPING_FREE)
    //  return isset($account['free_shipping_cost'])
    //           ? $account['free_shipping_cost']
    //             : null;

    return isset($account['shipping_rate']) ? $account['shipping_rate'] : null;
  }

  public function getFees () {
    if (!isset($this->_accounts[$this->_accountId]))
      return 0;

    $account = $this->_accounts[$this->_accountId];

    $addFees = $this->getAddFees();

    if ($addFees == -1)
      $addFees = $account['add_fees'];

    if (!$addFees)
      return 0;

    //$shippingType = $this->getShippingType();

    //if ($shippingType == -1 || $shippingType == null)
    //  $shippingType = $account['shipping_type'];

    //return $shippingType == MVentory_TradeMe_Model_Config::SHIPPING_FREE
    //         ? $account['free_shipping_fees']
    //           : $account['fees'];

    return $account['fees'];
  }

  public function prepareDataForJs () {
    $product = $this->getProduct();

    $data = array(
      'product' => array(
        'price' => $product->getPrice()
      ),
      'accounts' => $this->_accounts
    );

    foreach ($product->getData() as $key => $value)
      if (strpos($key, 'tm_') === 0)
        $data['product'][substr($key, 3)] = $value;

    foreach ($data['accounts'] as &$account)
      unset($account['key'], $account['secret'], $account['access_token']);

    return $data;
  }

  public function getAttributeOptions ($code) {
    $product = $this->getProduct();

    $attributes = $product->getAttributes();

    if (!isset($attributes[$code]))
      return array();

    $attribute = $attributes[$code];

    return $attribute
             ->getSource()
             ->getOptionArray();
  }

  public function getAttributeLabel ($code) {
    $product = $this->getProduct();

    $attributes = $product->getAttributes();

    if (!isset($attributes[$code]))
      return;

    $attribute = $attributes[$code];

    return $this->__($attributes[$code]->getFrontendLabel());
  }

  protected function _calculateShippingRates () {
    $helper = Mage::helper('trademe');

    $product = $this->getProduct();

    foreach ($this->_accounts as &$account)
      if (isset($account['shipping_type']))
        $account['shipping_rate'] = (float) $helper->getShippingRate(
          $product,
          $account['name'],
          $this->_website
        );
  }

  protected function _calculateFees () {
    $helper = Mage::helper('trademe');

    $price = $this
               ->getProduct()
               ->getPrice();

    foreach ($this->_accounts as &$account) {
      if (!isset($account['shipping_type']))
        continue;

      $shippingRate = isset($account['shipping_rate'])
                        ? $account['shipping_rate']
                          : 0;

      //$freeShippingCost = isset($account['free_shipping_cost'])
      //                      ? $account['free_shipping_cost']
      //                        : 0;

      $account['fees']
        = $helper->calculateFees($price + $shippingRate);

      //$account['free_shipping_fees']
      //  = $helper->calculateFees($price + $freeShippingCost);
    }
  }
}

