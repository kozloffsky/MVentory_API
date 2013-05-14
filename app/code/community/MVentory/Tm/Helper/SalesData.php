<?php

class MVentory_Tm_Helper_SalesData extends Mage_Sales_Helper_Data {

  /**
   * Check allow to send new order confirmation email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendNewOrderConfirmationEmail ($store = null) {
    return $this->_notApiCall()
           && parent::canSendNewOrderConfirmationEmail($store);
  }

  /**
   * Check allow to send new order email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendNewOrderEmail ($store = null) {
    return $this->_notApiCall() && parent::canSendNewOrderEmail($store);
  }

  /**
   * Check allow to send order comment email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendOrderCommentEmail ($store = null) {
    return $this->_notApiCall() && parent::canSendOrderCommentEmail($store);
  }

  /**
   * Check allow to send new shipment email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendNewShipmentEmail ($store = null) {
    return $this->_notApiCall() && parent::canSendNewShipmentEmail($store);
  }

  /**
   * Check allow to send shipment comment email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendShipmentCommentEmail ($store = null) {
    return $this->_notApiCall() && parent::canSendShipmentCommentEmail($store);
  }

  /**
   * Check allow to send new invoice email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendNewInvoiceEmail ($store = null) {
    return $this->_notApiCall() && parent::canSendNewInvoiceEmail($store);
  }

  /**
   * Check allow to send invoice comment email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendInvoiceCommentEmail ($store = null) {
    return $this->_notApiCall() && parent::canSendInvoiceCommentEmail($store);
  }

  /**
   * Check allow to send new creditmemo email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendNewCreditmemoEmail ($store = null) {
    return $this->_notApiCall() && parent::canSendNewCreditmemoEmail($store);
  }

  /**
   * Check allow to send creditmemo comment email
   *
   * @param mixed $store
   * @return bool
   */
  public function canSendCreditmemoCommentEmail ($store = null) {
    return $this->_notApiCall()
           && parent::canSendCreditmemoCommentEmail($store);
  }

  /**
   * Check if Magento was requested throw API
   *
   * @return bool
   */
  protected function _notApiCall () {
    return Mage::getSingleton('api/server')->getAdapter() == null;
  }
}
