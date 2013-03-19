<?php

class MVentory_Tm_Adminhtml_IndexController
  extends Mage_Adminhtml_Controller_Action {

  protected $_tmParams = array(
    'category',
    'account_id',
    'shipping_type',
    'allow_buy_now',
    'add_fees',
    'relist',
    'avoid_withdrawal',
    'pickup'
  );

  public function submitAction () {
    $helper = Mage::helper('mventory_tm');
    $request = $this->getRequest();

    $params = $request->getParams();

    $productId = isset($params['id']) ? $params['id'] : null;

    $data = array();

    if (isset($params['product']) && count($params['product']))
      foreach ($this->_tmParams as $name)
        $data[$name] = isset($params['product']['tm_' . $name])
                         ? $params['product']['tm_' . $name]
                           : null;

    $session = Mage::getSingleton('adminhtml/session');

    //Store TM params in session to use them on mVentory tab
    //when submit is failed
    $session->setData('tm_params', $data);

    //Get website which the product is assigned to
    $website = $helper->getWebsite($productId);

    //Load product with website scope (with website's default store scope)
    $product = Mage::getModel('catalog/product')
                 ->setStoreId($website->getDefaultStore()->getId())
                 ->load($productId);

    if (!$product->getId()) {
      $session->addError($helper->__('Can\'t load product'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $stock = Mage::getModel('cataloginventory/stock_item')
               ->loadByProduct($product);

    if ($stock->getManageStock() && $stock->getQty() == 0
        && !$product->getTmListingId()) {
      $session->addError($helper->__('Item is not available in inventory'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $connector = Mage::getModel('mventory_tm/connector');

    $result = $connector->send($product, $data['category'], $data);

    if (!is_int($result)) {
      $session->addError($helper->__($result));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $path = MVentory_Tm_Model_Connector::SANDBOX_PATH;
    $website = $helper->getWebsiteIdFromProduct($product);

    $host = $helper->getConfig($path, $website) ? 'tmsandbox' : 'trademe';
    $url = 'http://www.' . $host . '.co.nz/Browse/Listing.aspx?id=' . $result;

    $link = '<a href="' . $url . '">' . $url . '</a>';

    $session->addSuccess($helper->__('Listing URL') . ': ' . $link);

    $product
      ->setTmListingId($result)
      ->save();

    //Remove TM options from the session on successful submit
    $session->unsetData('tm_params');

    $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);
  }

  public function removeAction () {
    $id = $this->_request->getParam('id');
    $product = Mage::getModel('catalog/product')->load($id);
    $result = 'Error';

    if ($product->getId()) {
      $connector = Mage::getModel('mventory_tm/connector');
      $result = $connector->remove($product);
    }

    $helper = Mage::helper('mventory_tm');

    if ($result === true) {
      $path = MVentory_Tm_Model_Connector::SANDBOX_PATH;
      $website = $helper->getWebsiteIdFromProduct($product);

      $host = $helper->getConfig($path, $website) ? 'tmsandbox' : 'trademe';
      $url = 'http://www.'
             . $host
             . '.co.nz/Browse/Listing.aspx?id='
             . $product->getTmListingId();

      $link = '<a href="' . $url . '">' . $url . '</a>';

      Mage::getSingleton('adminhtml/session')
        ->addSuccess($helper->__('Removed from') . ': ' . $link);

      $product
        ->setTmListingId(0)
        ->save();
    } else
      Mage::getSingleton('adminhtml/session')->addError($helper->__($result));

    $this->_redirect('adminhtml/catalog_product/edit/id/' . $id);
  }

  public function checkAction () {
    $id = $this->_request->getParam('id');
    $product = Mage::getModel('catalog/product')->load($id);

    $helper = Mage::helper('mventory_tm');

    if ($product->getId()) {
      $connector = Mage::getModel('mventory_tm/connector');
      $result = $connector->check($product);

      $path = MVentory_Tm_Model_Connector::SANDBOX_PATH;
      $website = $helper->getWebsiteIdFromProduct($product);

      $host = $helper->getConfig($path, $website) ? 'tmsandbox' : 'trademe';
      $url = 'http://www.'
             . $host
             . '.co.nz/Browse/Listing.aspx?id='
             . $product->getTmListingId();

      $link = '<a href="' . $url . '">' . $url . '</a>';

      switch ($result) {
        case 1:
          Mage::getSingleton('adminhtml/session')
            ->addSuccess($helper->__('Wasn\'t sold') . ': ' . $link);

          $product->setTmListingId(0);
          $product->save();

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
            ->setTmListingId(0)
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
    $helper = Mage::helper('mventory_tm');

    $params = $request->getParams();

    if (!isset($params['id'])) {
      Mage::getSingleton('adminhtml/session')
        ->addError($helper->__('No product ID parameter'));

      $this->_redirect('adminhtml/catalog_product/index');

      return;
    }

    $data = array();

    if (isset($params['product']) && count($params['product']))
      foreach ($this->_tmParams as $name)
        $data[$name] = isset($params['product']['tm_' . $name])
                         ? $params['product']['tm_' . $name]
                           : null;

    $product = Mage::getModel('catalog/product')->load($params['id']);

    if (!$product->getId()) {
      Mage::getSingleton('adminhtml/session')
        ->addError($helper->__('Can\'t load product'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $params['id']);

      return;
    }

    $result = Mage::getModel('mventory_tm/connector')
                ->update($product, null, $data);

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
