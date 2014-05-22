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
 * TradeMe listing controller
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_ListingController
  extends Mage_Adminhtml_Controller_Action
{
  public function submitAction () {
    $helper = Mage::helper('mventory_tm/product');
    $request = $this->getRequest();

    $params = $request->getParams();

    $productId = isset($params['id']) ? $params['id'] : null;

    $data = isset($params['product']) && is_array($params['product'])
              ? Mage::helper('trademe')->getFields($params['product'])
                : array();

    $data['category'] = isset($params['trademe_category'])
                          ? $params['trademe_category']
                            : null;

    $session = Mage::getSingleton('adminhtml/session');

    //Store TradeMe data in session to use it on TradeMe tab
    //when submit fails
    $session->setData('trademe_data', $data);

    if (!(isset($data['account_id']) && $data['account_id'])) {
      $session->addError($helper->__('Please, select account'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $product = Mage::getModel('catalog/product')->load($productId);

    if (!$product->getId()) {
      $session->addError($helper->__('Can\'t load product'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $stock = Mage::getModel('cataloginventory/stock_item')
               ->loadByProduct($product);

    if ($stock->getManageStock() && $stock->getQty() == 0
        && !$product->getTmCurrentListingId()) {
      $session->addError($helper->__('Item is not available in inventory'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $connector = new MVentory_TradeMe_Model_Api();

    $result = $connector->send($product, $data['category'], $data);

    if (!is_int($result)) {
      $session->addError($helper->__($result));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $path = MVentory_TradeMe_Model_Config::SANDBOX;
    $website = $helper->getWebsite($product);

    $host = $helper->getConfig($path, $website) ? 'tmsandbox' : 'trademe';
    $url = 'http://www.' . $host . '.co.nz/Browse/Listing.aspx?id=' . $result;

    $link = '<a href="' . $url . '">' . $url . '</a>';

    $session->addSuccess($helper->__('Listing URL') . ': ' . $link);

    $product
      ->setTmListingId($result)
      ->setTmCurrentListingId($result)
      ->save();

    //Remove TradeMe data from the session on successful submit
    $session->unsetData('trademe_data');

    $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);
  }

  public function removeAction () {
    $id = $this->_request->getParam('id');
    $product = Mage::getModel('catalog/product')->load($id);
    $result = 'Error';

    if ($product->getId()) {
      $connector = new MVentory_TradeMe_Model_Api();
      $result = $connector->remove($product);
    }

    $helper = Mage::helper('mventory_tm/product');

    if ($result === true) {
      $path = MVentory_TradeMe_Model_Config::SANDBOX;
      $website = $helper->getWebsite($product);

      $host = $helper->getConfig($path, $website) ? 'tmsandbox' : 'trademe';
      $url = 'http://www.'
             . $host
             . '.co.nz/Browse/Listing.aspx?id='
             . $product->getTmCurrentListingId();

      $link = '<a href="' . $url . '">' . $url . '</a>';

      Mage::getSingleton('adminhtml/session')
        ->addSuccess($helper->__('Removed from') . ': ' . $link);

      $product
        ->setTmCurrentListingId(0)
        ->setTmCurrentAccountId(null)
        ->save();
    } else
      Mage::getSingleton('adminhtml/session')->addError($helper->__($result));

    $this->_redirect('adminhtml/catalog_product/edit/id/' . $id);
  }

  public function checkAction () {
    $id = $this->_request->getParam('id');
    $product = Mage::getModel('catalog/product')->load($id);

    $helper = Mage::helper('mventory_tm/product');

    if ($product->getId()) {
      $connector = new MVentory_TradeMe_Model_Api();
      $result = $connector->check($product);

      $path = MVentory_TradeMe_Model_Config::SANDBOX;
      $website = $helper->getWebsite($product);

      $host = $helper->getConfig($path, $website) ? 'tmsandbox' : 'trademe';
      $url = 'http://www.'
             . $host
             . '.co.nz/Browse/Listing.aspx?id='
             . $product->getTmCurrentListingId();

      $link = '<a href="' . $url . '">' . $url . '</a>';

      switch ($result) {
        case 1:
          Mage::getSingleton('adminhtml/session')
            ->addSuccess($helper->__('Wasn\'t sold') . ': ' . $link);

          $product
            ->setTmCurrentListingId(0)
            ->setTmCurrentAccountId(null)
            ->save();

          break;
        case 2:
          Mage::getSingleton('adminhtml/session')
            ->addSuccess($helper->__('Successfully sold ') . ': ' . $link);

          $stock = Mage::getModel('cataloginventory/stock_item')
                     ->loadByProduct($product);

          if ($stock->getManageStock() && $stock->getQty()) {
            $stockData = $stock->getData();
            $stockData['qty'] -= 1;
            $product->setStockData($stockData);
          }

          $product
            ->setTmCurrentListingId(0)
            ->setTmCurrentAccountId(null)
            ->save();

          break;
        case 3:
          Mage::getSingleton('adminhtml/session')
            ->addSuccess($helper->__('Listing is active ') . ': ' . $link);

          break;
        default:
          Mage::getSingleton('adminhtml/session')
            ->addError($helper->__('Listing doesn\'t exist'));
      }
    } else
      Mage::getSingleton('adminhtml/session')->addError($helper->__('Error'));

    $this->_redirect('adminhtml/catalog_product/edit/id/' . $id);
  }

  public function updateAction () {
    $request = $this->getRequest();
    $helper = Mage::helper('mventory_tm/product');

    $params = $request->getParams();

    if (!isset($params['id'])) {
      Mage::getSingleton('adminhtml/session')
        ->addError($helper->__('No product ID parameter'));

      $this->_redirect('adminhtml/catalog_product/index');

      return;
    }

    $data = isset($params['product']) && is_array($params['product'])
              ? Mage::helper('trademe')->getFields($params['product'])
                : array();

    $data['category'] = isset($params['trademe_category'])
                          ? $params['trademe_category']
                            : null;

    $product = Mage::getModel('catalog/product')->load($params['id']);

    if (!$product->getId()) {
      Mage::getSingleton('adminhtml/session')
        ->addError($helper->__('Can\'t load product'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $params['id']);

      return;
    }

    $result = (new MVentory_TradeMe_Model_Api())->update($product, null, $data);

    if (!is_int($result)) {
      Mage::getSingleton('adminhtml/session')->addError($helper->__($result));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $params['id']);

      return;
    }

    Mage::getSingleton('adminhtml/session')
      ->addSuccess($helper->__('Listing has been updated '));

    $this->_redirect('adminhtml/catalog_product/edit/id/' . $params['id']);
  }
}
