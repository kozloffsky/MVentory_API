<?php

/**
 * Order API
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Model_Order_Api extends Mage_Sales_Model_Order_Api {

  public function listByStatus ($status) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

    $collection = Mage::getModel("sales/order")
                    ->getCollection();

    try {
      $collection
        ->addFieldToFilter('status', $status)
        ->addFieldToFilter('store_id', $storeId);
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

    return $orders;
  }
}
