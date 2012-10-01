<?php

class MVentory_Tm_Adminhtml_IndexController
  extends Mage_Adminhtml_Controller_Action {

  public function submitAction () {
    $helper = Mage::helper('mventory_tm');
    $request = $this->getRequest();

    $params = $request->getParams();

    $productId = isset($params['id']) ? $params['id'] : null;
    $categoryId = isset($params['tm']['category'])
                    ? $params['tm']['category']
                      : null;
    $data = isset($params['tm']) ? $params['tm'] : array();

    $product = Mage::getModel('catalog/product')->load($productId);

    if (!$product->getId()) {
      Mage::getSingleton('adminhtml/session')
        ->addError($helper->__('Can\'t load product'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $stock = Mage::getModel('cataloginventory/stock_item')
               ->loadByProduct($product);

    if ($stock->getManageStock() && $stock->getQty() == 0
        && !$product->getTmListingId()) {
      Mage::getSingleton('adminhtml/session')
        ->addError($helper->__('Item is not available in inventory'));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $connector = Mage::getModel('mventory_tm/connector');

    $result = $connector->send($product, $categoryId, $data);

    if (!is_int($result)) {
      Mage::getSingleton('adminhtml/session')->addError($helper->__($result));

      $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);

      return;
    }

    $path = MVentory_Tm_Model_Connector::SANDBOX_PATH;
    $website = $helper->getWebsiteIdFromProduct($product);

    $host = $helper->getConfig($path, $website) ? 'tmsandbox' : 'trademe';
    $url = 'http://www.' . $host . '.co.nz/Browse/Listing.aspx?id=' . $result;

    $link = '<a href="' . $url . '">' . $url . '</a>';

    Mage::getSingleton('adminhtml/session')
      ->addSuccess($helper->__('Listing URL') . ': ' . $link);

    $product
      ->setTmListingId($result)
      ->save();

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
}
