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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Order API
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_Model_Order_Api extends Mage_Sales_Model_Order_Api {

  public function listByStatus ($status = null) {
    $storeId = Mage::helper('mventory')->getCurrentStoreId();

    $limit = (int) Mage::getStoreConfig(
                     MVentory_Tm_Model_Product_Api::FETCH_LIMIT_PATH,
                     $storeId);

    $collection = Mage::getModel("sales/order")
                    ->getCollection();

    try {
      if ($status)
        $collection
          ->addFieldToFilter('status', $status);

      $collection
        ->addFieldToFilter('store_id', $storeId)
        ->setOrder('updated_at', Varien_Data_Collection_Db::SORT_ORDER_DESC)
        ->setPageSize($limit)
        ->setCurPage(1);
    } catch (Mage_Core_Exception $e) {
      $this->_fault('filters_invalid', $e->getMessage());
    }

    $orders = array();

    foreach ($collection as $order) {
      $items = $order->getAllItems();

      $productsNames = array();

      foreach ($items as $item)
        $productsNames[] = array('name' => $item->getName());

      $data = $order->getData();

      $orders[] = array(
        'increment_id' => $data['increment_id'],
        'created_at' => $data['created_at'],
        'customer_firstname' => $data['customer_firstname'],
        'customer_lastname' => $data['customer_lastname'],
        'customer_email' => $data['customer_email'],
        'items' => $productsNames
      );
    }

    unset($collection);

    $statuses = Mage::getModel('sales/order_status')
                    ->getCollection()
                    ->toOptionHash();

    return compact('statuses', 'orders');
  }

  public function fullInfo($orderIncrementId) {
    $order = $this->info($orderIncrementId);

    $orderId = (int) $order['order_id'];

    //Collect credit memos

    $order['credit_memos'] = array();

    $creditMemoApi = Mage::getModel('sales/order_creditmemo_api');

    $creditMemos = $creditMemoApi->items(array('order_id' => $orderId));

    foreach ($creditMemos as $creditMemo)
      $order['credit_memos'][]
        = $creditMemoApi->info($creditMemo['increment_id']);

    unset($creditMemos);

    //Collect invoices

    $order['invoices'] = array();

    $invoiceApi = Mage::getModel('sales/order_invoice_api');

    $invoices = $invoiceApi->items(array('order_id' => $orderId));

    foreach ($invoices as $invoice)
      $order['invoices'][]
        = $invoiceApi->info($invoice['increment_id']);

    unset($invoices);

    //Collect shipments

    $order['shipments'] = array();

    $shipmentApi = Mage::getModel('sales/order_shipment_api');

    $shipments = $shipmentApi->items(array('order_id' => $orderId));

    foreach ($shipments as $shipment)
      $order['shipments'][]
        = $shipmentApi->info($shipment['increment_id']);

    unset($shipments);

    return $order;
  }

  /**
   * Initialize basic order model
   *
   * The function is redefined to check if api user has access to the order
   *
   * @param mixed $orderIncrementId
   * @return Mage_Sales_Model_Order
   */
  protected function _initOrder ($orderIncrementId) {
    $order = parent::_initOrder($orderIncrementId);

    $userWebsite = Mage::helper('mventory')->getApiUserWebsite();

    if (!$userWebsite)
      $this->_fault('access_denied');

    $userWebsiteId = $userWebsite->getId();

    if ($userWebsiteId == 0)
      return $order;

    $orderWebsiteId = $order
                        ->getStore()
                        ->getWebsiteId();

    if ($orderWebsiteId != $userWebsiteId)
      $this->_fault('not_exists');

    return $order;
  }
}
