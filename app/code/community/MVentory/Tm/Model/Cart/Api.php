<?php

class MVentory_Tm_Model_Cart_Api extends Mage_Checkout_Model_Cart_Api {

  const TAX_CLASS_PATH = 'mventory_tm/api/tax_class';

  public function createOrderForProduct ($sku, $price, $qty, $customerId,
                                         $transactionId = null, $name = null,
                                         $taxClass = null) {

    $helper = Mage::helper('mventory_tm');

    $storeId = $helper->getCurrentStoreId();

    $productApi = Mage::getModel('mventory_tm/product_api');

    //Try load order which was created but API client didn't received
    //its ID. It prevents from double ordering in case data lost during
    //communication
    if ($transactionId !== null) {
      $transactionId = (int) $transactionId;

      $orderId = Mage::getResourceModel('mventory_tm/order_transaction')
                   ->getOrderIdByTransaction($transactionId);

      if ($orderId) {
        $result = $productApi->fullInfo(null, $sku);

        $result['order_id'] = $orderId;

        return $result;
      }
    }

    $price = (float) $price;

    $qtyIsDecimal = is_float($qty);

    $qty = (float) $qty;

    $updateProduct = false;

    $product = Mage::getModel('catalog/product');

    $productId = (int) $product->getResource()->getIdBySku($sku);

    if (!$productId) {
      if ($taxClass == null)
        $taxClass = (int) $helper->getConfig(self::TAX_CLASS_PATH,
                                             $helper->getCurrentWebsite());

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

        if ($qtyIsDecimal) {
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

    //create shipment and invoice to complete order
    $shipment = Mage::getModel('sales/order_shipment_api');
    $shipment->create($orderId);

    $invoice = Mage::getModel('sales/order_invoice_api');
    $invoice->create($orderId, null);

    //Save transaction ID and orderId pair. So, it will return existing order
    //to API client if it will try to create order with same transaction ID
    //next time
    if ($transactionId !== null) {
      $transaction = Mage::getModel('mventory_tm/order_transaction');

      $transaction
        ->setOrderId((int) $orderId)
        ->setTransactionId((int) $transactionId)
        ->save();
    }

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

    $result = $productApi->fullInfo($product->getId());

    if ($orderId)
      $result['order_id'] = $orderId;

    return $result;
  }
  
  function addToCart($data)
  {
  	$session = $this->_getSession();
    $user = $session->getUser();
    
    $data['user_name'] = $user->getFirstname() . " " . $user->getLastname();
    $data['store_id'] = Mage::helper('mventory_tm')->getCurrentStoreId(null);
    
    Mage::getModel('mventory_tm/cart_item')->setData($data)->save();
  }
  
  function getCart()
  {
    $cartItemLifeTime = Mage::getStoreConfig('mventory_tm/api/cart-item-lifetime');
    $deleteBeforeTimestamp = time() - $cartItemLifeTime*60;
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId(null);
    
    return Mage::getResourceModel('mventory_tm/cart_item')
      ->getCart($deleteBeforeTimestamp, $storeId);
  }
}
