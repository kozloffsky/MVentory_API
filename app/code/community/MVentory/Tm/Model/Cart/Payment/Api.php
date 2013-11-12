<?php

class MVentory_Tm_Model_Cart_Payment_Api
  extends Mage_Checkout_Model_Cart_Payment_Api {

  public function setPaymentMethod ($quoteId, $paymentData, $store = null) {
    $quote = $this->_getQuote($quoteId, $store);
    $store = $quote->getStoreId();

    if (!$paymentData = $this->_preparePaymentData($paymentData))
      $this->_fault('payment_method_empty');

    $isVirtual = $quote->isVirtual();

    $address = $isVirtual
                 ? $quote->getBillingAddress()
                   : $address = $quote->getShippingAddress();

    //Check if address is set
    if ($address->getId() === null)
      if ($isVirtual)
        $this->_fault('billing_address_is_not_set');
      else
        $this->_fault('shipping_address_is_not_set');

    $address->setPaymentMethod(
      isset($paymentData['method']) ? $paymentData['method'] : null
    );

    if (!$isVirtual && ($address = $quote->getShippingAddress()))
      $address->setCollectShippingRates(true);

    $total = $quote->getBaseSubtotal();
    $methods = Mage::helper('payment')->getStoreMethods($store, $quote);

    foreach ($methods as $method) {
      if ($method->getCode() != $paymentData['method'])
        continue;

      if (!$this->_canUsePaymentMethod($method, $quote))
        $this->_fault('method_not_allowed');

      break;
    }

    try {
      $payment = $quote->getPayment();
      $payment->importData($paymentData);

      $quote
        ->setTotalsCollectedFlag(false)
        ->collectTotals()
        ->save();
    } catch (Mage_Core_Exception $e) {
      $this->_fault('payment_method_is_not_set', $e->getMessage());
    }

    return true;
  }
}
