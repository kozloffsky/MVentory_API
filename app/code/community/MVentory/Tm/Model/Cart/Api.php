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
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Shopping cart api
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Cart_Api extends Mage_Checkout_Model_Cart_Api {

  public function createOrderForProduct ($sku, $price, $qty, $customerId,
                                         $transactionId = null, $name = null,
                                         $taxClass = null) {

    $helper = Mage::helper('mventory/product');

    $storeId = $helper->getCurrentStoreId();

    $productApi = Mage::getModel('mventory/product_api');

    //Try load order which was created but API client didn't received
    //its ID. It prevents from double ordering in case data lost during
    //communication
    if ($transactionId !== null) {
      $transactionId = (int) $transactionId;

      $orderId = Mage::getResourceModel('mventory/order_transaction')
                   ->getOrderIdByTransaction($transactionId);

      if ($orderId) {
        $result = $productApi->fullInfo($sku, 'sku');

        $result['order_id'] = $orderId;

        return $result;
      }
    }

    $price = (float) $price;
    $qty = $this->_parseQty($qty);

    $updateProduct = false;

    $product = Mage::getModel('catalog/product');

    $productId = (int) $product->getResource()->getIdBySku($sku);

    if (!$productId) {
      if ($taxClass == null)
        $taxClass = (int) $helper->getConfig(
          MVentory_API_Model_Config::_TAX_CLASS,
          $helper->getCurrentWebsite()
        );

      $product
        ->setWebsiteIds($helper->getWebsitesForProduct())
        ->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setTypeId('simple')
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(0)
        ->setWeight(0)
        ->setPrice($price)
        ->setTaxClassId($taxClass)
        ->setSku($sku)
        ->setName($name)
        ->setDescription($name)
        ->setShortDescription($name)
        ->save();

        $stockItem = Mage::getModel('cataloginventory/stock_item');

        $stockItem
          ->setStockId(1)
          ->setUseConfigManageStock(0)
          ->setProduct($product)
          ->setManageStock(1)
          ->setIsQtyDecimal(1)
          ->setIsInStock(1)
          ->setQty($qty)
          ->save();

        $updateProduct = true;
    } else {
      if (!$helper->hasApiUserAccess($productId, 'id'))
        $this->_fault('product_not_exists');

      $product->load($productId);
      $stockItem = $product->getStockItem();

      if ($stockItem->getManageStock()) {
        $productQty = (float) $stockItem->getQty();

        $saveStockItem = false;

        if ($productQty <= 0) {
          //Mage::getModel('cataloginventory/stock_item_api')
          //  ->update($sku, array('qty' => $qty, 'is_in_stock' => 1));
          //$product->setStockData(array('qty' => $qty, 'is_in_stock' => true));

          $productQtyNew = $productQty - $qty;

          $stockItem
            ->setUseConfigManageStock(0)
            ->setQty($qty)
            ->setIsInStock(1);

          $saveStockItem = true;
        } else if (!$stockItem->getIsInStock()) {
          //Change stock status if the qty is greater then zero but the product
          //marked as out of stock, so it won't produce out of stock error
          //on ordering.

          $stockItem->setIsInStock(1);

          $saveStockItem = true;
        }

        if (is_float($qty) && !$stockItem->getIsQtyDecimal()) {
          $stockItem
            ->setIsQtyDecimal(1);

          $saveStockItem = true;
        }

        if ($saveStockItem)
          $stockItem
            ->save();
      }
    }

    if ($product->getStatus()
          == Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
      Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

      $product
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->save();

      Mage::app()->setCurrentStore($storeId);

      $updateProduct = true;
    }

    $quoteId = $this->create($storeId);

    $cartCustomer = Mage::getModel('checkout/cart_customer_api');

    $data = array(
      'mode' => Mage_Checkout_Model_Cart_Customer_Api::MODE_CUSTOMER,
      'entity_id' => $customerId
    );

    $result = $cartCustomer->set($quoteId, $data, $storeId);

    $addressId = Mage::getModel('customer/customer')
                   ->load($customerId)
                   ->getDefaultBillingAddress()
                   ->getId();

    $data = array('mode'
                    => Mage_Checkout_Model_Cart_Customer_Api::ADDRESS_BILLING,
                  'use_for_shipping' => true,
                  'entity_id' => $addressId );

    $result = $cartCustomer->setAddresses($quoteId, array($data), $storeId);

    $cartProduct = Mage::getModel('checkout/cart_product_api');

    $data = array('product_id' => $product->getId(),
                  'qty' => $qty );

    $result = $cartProduct->add($quoteId, array($data), $storeId);

    $quote = $this->_getQuote($quoteId, $storeId);

    //var_dump($quote
    //           ->getItemsCollection()
    //           ->getFirstItem() );

    $quote
      ->getItemsCollection()
      ->getFirstItem()
      ->setCustomPrice($price)
      ->setOriginalCustomPrice($price);

    $quote
      ->collectTotals()
      ->save();

    $cartShipping = Mage::getModel('checkout/cart_shipping_api');

    $data = 'dummyshipping_dummyshipping';

    $result = $cartShipping->setShippingMethod($quoteId, $data, $storeId);

    $cartPayment = Mage::getModel('checkout/cart_payment_api');

    if ($price == 0)
      $data = array('method' => 'free', 0 => null);
    else
      $data = array('method' => 'dummy', 0 => null);

    $result = $cartPayment->setPaymentMethod($quoteId, $data, $storeId);

    $orderId = $this->createOrder($quoteId, $storeId);

    //Save transaction ID and orderId pair. So, it will return existing order
    //to API client if it will try to create order with same transaction ID
    //next time
    if ($transactionId !== null) {
      $transaction = Mage::getModel('mventory/order_transaction');

      $transaction
        ->setOrderId((int) $orderId)
        ->setTransactionId((int) $transactionId)
        ->save();
    }

    //create shipment and invoice to complete order
    $shipment = Mage::getModel('sales/order_shipment_api');
    $shipment->create($orderId);

    $invoice = Mage::getModel('sales/order_invoice_api');
    $invoice->create($orderId, null);

    if ($updateProduct) {
      Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

      $product
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
        ->save();

      Mage::app()->setCurrentStore($storeId);
    }

    if (isset($productQtyNew))
      $stockItem
        ->setUseConfigManageStock(0)
        ->setQty($productQtyNew)
        ->save();

    $result = $productApi->fullInfo($product->getId(), 'id');

    if ($orderId)
      $result['order_id'] = $orderId;

    return $result;
  }

  private function convertTo32bitTransactionId($transactionId)
  {
    return (int) ($transactionId % 2147483647); //max value of signed 32-bit integer
  }

  public function createOrderForMultipleProducts ($productsToOrder) {
  	$apiResult = array();
  	$apiResult['qtys'] = array();
  	
    $orderApi = Mage::getModel('mventory/order_api');

    $helper = Mage::helper('mventory/product');

    $storeId = $helper->getCurrentStoreId();

    $transactionId = 0;

    $productApi = Mage::getModel('mventory/product_api');

    if (is_array($productsToOrder) && count($productsToOrder)>0)
    {
      $firstTransaction = $productsToOrder[0];

      //Try load order which was created but API client didn't received
      //its ID. It prevents from double ordering in case data lost during
      //communication
      $transactionId = $this->convertTo32bitTransactionId($firstTransaction['transaction_id']);

      $orderId = Mage::getResourceModel('mventory/order_transaction')
                   ->getOrderIdByTransaction($transactionId);

      if ($orderId) {
        $apiResult['order_details'] = $orderApi->fullInfo($orderId);
        return $apiResult;
      }

    } else {
        $this->_fault('invalid_params');
    }

    foreach ($productsToOrder as &$productData)
    {
      $cartItem = Mage::getModel('mventory/cart_item');

      $cartItem->load($productData['transaction_id']);

      if (!$cartItem->getId())
      {
      	$this->_fault('transaction_not_exists');
      }

      if (!isset($customerId))
      {
      	$customerId = $cartItem['customer_id'];
      }	

      $productData['product_id'] = $cartItem['product_id'];

      if (!isset($productData['price'])) {
      	$productData['price'] = $cartItem['price'];
      }

      if (!isset($productData['qty'])) {
        $productData['qty'] = $cartItem['qty'];
      }
    }

    unset($productData);

    foreach ($productsToOrder as &$productData)
    {
      $price = (float) $productData['price'];
      $qty = $this->_parseQty($productData['qty']);

      $updateProduct = false;

      $product = Mage::getModel('catalog/product');

      $productId = (int) $productData['product_id'];

      if (!$helper->hasApiUserAccess($productId, 'id'))
        $this->_fault('product_not_exists');

      $product->load($productId);

      if (!$product->getId())
      {
        $this->_fault('product_not_exists');
      }

      $productData['product_model'] = $product;

      $stockItem = $product->getStockItem();

      if ($stockItem->getManageStock()) {
        $productQty = (float) $stockItem->getQty();

        $apiResult['qtys'][$product->getSku()] = "" . ($productQty - $qty);

        $saveStockItem = false;

        if ($productQty <= 0) {
          //Mage::getModel('cataloginventory/stock_item_api')
          //  ->update($sku, array('qty' => $qty, 'is_in_stock' => 1));
          //$product->setStockData(array('qty' => $qty, 'is_in_stock' => true));

          $productData['product_quantity_new'] = $productQty - $qty;

          $stockItem
            ->setUseConfigManageStock(0)
            ->setQty($qty)
            ->setIsInStock(1);

          $saveStockItem = true;
        } else if (!$stockItem->getIsInStock()) {
          //Change stock status if the qty is greater then zero but the product
          //marked as out of stock, so it won't produce out of stock error
          //on ordering.

          $stockItem->setIsInStock(1);

          $saveStockItem = true;
        }

        if (is_float($qty) && !$stockItem->getIsQtyDecimal()) {
          $stockItem
            ->setIsQtyDecimal(1);

          $saveStockItem = true;
        }

        if ($saveStockItem)
          $stockItem
            ->save();
      }

      if ($product->getStatus()== Mage_Catalog_Model_Product_Status::STATUS_DISABLED) {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $product
          ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
          ->save();

        Mage::app()->setCurrentStore($storeId);

        $productData['update_product'] = true;
      }
    }

    unset($productData);

    $quoteId = $this->create($storeId);

    $cartCustomer = Mage::getModel('checkout/cart_customer_api');

    $data = array('mode' => Mage_Checkout_Model_Cart_Customer_Api::MODE_CUSTOMER,
                  'entity_id' => $customerId);

    $result = $cartCustomer->set($quoteId, $data, $storeId);

    $addressId = Mage::getModel('customer/customer')
                   ->load($customerId)
                   ->getDefaultBillingAddress()
                   ->getId();

    $data = array('mode'
                    => Mage_Checkout_Model_Cart_Customer_Api::ADDRESS_BILLING,
                  'use_for_shipping' => true,
                  'entity_id' => $addressId );

    $result = $cartCustomer->setAddresses($quoteId, array($data), $storeId);

    $cartProduct = Mage::getModel('checkout/cart_product_api');

    $cartProductsData = array();

    foreach ($productsToOrder as $productData)
    {
      $data = array('product_id' => $productData['product_id'],
                     'qty' => $productData['qty'],
                     'custom_price' => $productData['price'] );

      $cartProductsData[] = $data;
    }

    $result = $cartProduct->add($quoteId, $cartProductsData, $storeId);

    $quote = $this->_getQuote($quoteId, $storeId);

    $i = 0;
    foreach ($quote->getItemsCollection() as $item)
    {
      $productToOrder = $productsToOrder[$i];

      $item->setCustomPrice($productToOrder['price'])
      ->setOriginalCustomPrice($productToOrder['price']);
      $i++;
    }

    $quote
      ->collectTotals()
      ->save();

    $cartShipping = Mage::getModel('checkout/cart_shipping_api');

    $data = 'dummyshipping_dummyshipping';

    $result = $cartShipping->setShippingMethod($quoteId, $data, $storeId);

    $cartPayment = Mage::getModel('checkout/cart_payment_api');

    $data = array('method' => 'dummy', 0 => null);

    $result = $cartPayment->setPaymentMethod($quoteId, $data, $storeId);

    $orderId = $this->createOrder($quoteId, $storeId);

    //create shipment and invoice to complete order
    $shipment = Mage::getModel('sales/order_shipment_api');
    $shipment->create($orderId);

    $invoice = Mage::getModel('sales/order_invoice_api');
    $invoice->create($orderId, null);

    //Save transaction ID and orderId pair. So, it will return existing order
    //to API client if it will try to create order with same transaction ID
    //next time
    $transaction = Mage::getModel('mventory/order_transaction');

    $transaction
      ->setOrderId((int) $orderId)
      ->setTransactionId($transactionId)
      ->save();

    foreach ($productsToOrder as $productData)
    {
      if (isset($productData['update_product']) && $productData['update_product']===true)
      {
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        $productData['product_model']
          ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
          ->save();

        Mage::app()->setCurrentStore($storeId);
      }

      if (isset($productData['product_quantity_new']))
        $productData['product_model']->getStockItem()
          ->setUseConfigManageStock(0)
          ->setQty($productData['product_quantity_new'])
          ->save();
    }

    foreach ($productsToOrder as $productData)
    {
      Mage::getModel('mventory/cart_item')->setId($productData['transaction_id'])->delete();
    }

    $apiResult['order_details'] = $orderApi->fullInfo($orderId);

    return $apiResult;
  }

  function addToCart($data)
  {
    $session = $this->_getSession();
    $user = $session->getUser();

    $data['user_name'] = $user->getFirstname() . " " . $user->getLastname();
    $data['store_id'] = Mage::helper('mventory')->getCurrentStoreId(null);

    Mage::getModel('mventory/cart_item')->setData($data)->save();
  }

  function getCart()
  {
    $cartItemLifeTime = Mage::getStoreConfig(
      MVentory_API_Model_Config::_ITEM_LIFETIME
    );
    $deleteBeforeTimestamp = time() - $cartItemLifeTime*60;
    $storeId = Mage::helper('mventory')->getCurrentStoreId(null);

    return Mage::getResourceModel('mventory/cart_item')
      ->getCart($deleteBeforeTimestamp, $storeId);
  }

  private function _parseQty ($value) {
    $iValue = (int) $value;
    $fValue = (float) $value;

    return $iValue == $fValue ? $iValue : $fValue;
  }
}
