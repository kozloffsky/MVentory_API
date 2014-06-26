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
 * Shopping cart payment api
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Cart_Payment_Api
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
