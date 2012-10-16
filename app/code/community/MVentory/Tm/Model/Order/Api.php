<?php

/**
 * Order API
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Model_Order_Api extends Mage_Sales_Model_Order_Api {

  public function listByStatus ($status = null) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

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
  
  public function createShipmentWithTracking($orderIncrementId, $carrier,
    $title, $trackNumber, $itemsQty = array(), $comment = null, $email = false,
     $includeComment = false){
  	
  	$order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

    
    // Check order existing
    
    if (!$order->getId()) {
      $this->_fault('order_not_exists');
    }

    
    // Check shipment create availability
    
    if (!$order->canShip()) {
      $this->_fault('data_invalid', Mage::helper('sales')->__('Cannot do 
        shipment for order.'));
    }

    // @var $shipment Mage_Sales_Model_Order_Shipment 
    $shipment = $order->prepareShipment($itemsQty);
    if ($shipment) {
      $shipment->register();
      $shipment->addComment($comment, $email && $includeComment);
      if ($email) {
        $shipment->setEmailSent(true);
      }
      $shipment->getOrder()->setIsInProcess(true);
      try {
        $transactionSave = Mage::getModel('core/resource_transaction')
          ->addObject($shipment)
          ->addObject($shipment->getOrder())
          ->save();
        $shipment->sendEmail($email, ($includeComment ? $comment : ''));
      } 
      catch (Mage_Core_Exception $e) {
        $this->_fault('data_invalid', $e->getMessage());
      }
    }
        
    // Get carriers 
    $carriers = array();
    $carrierInstances = Mage::getSingleton('shipping/config')->getAllCarriers(
      $shipment->getStoreId()
    );
    $carriers['custom'] = Mage::helper('sales')->__('Custom Value');
    foreach ($carrierInstances as $code => $car) {
      if ($car->isTrackingAvailable()) {
        $carriers[$code] = $car->getConfigData('title');
      }
    }
    if (!isset($carriers[$carrier])) {
      $this->_fault('data_invalid', Mage::helper('sales')->__('Invalid carrier
        specified.'));
    }

    $track = Mage::getModel('sales/order_shipment_track')
      ->setNumber($trackNumber)
      ->setCarrierCode($carrier)
      ->setTitle($title);

    $shipment->addTrack($track);

    try {
      $shipment->save();
      $track->save();
    } 
    catch (Mage_Core_Exception $e) {
      $this->_fault('data_invalid', $e->getMessage());
    }

    return $this->fullInfo($orderIncrementId);
  }
}