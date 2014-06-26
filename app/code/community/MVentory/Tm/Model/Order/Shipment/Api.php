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
 * Sales order shippment API
 *
 * @package MVentory/API
 */
class MVentory_API_Model_Order_Shipment_Api extends Mage_Sales_Model_Order_Shipment_Api {

  public function createShipmentWithTracking($orderIncrementId, $carrier,
  $title, $trackNumber, $params = null) {

    $itemsQty = array();
    $comment = null;
    $email = false;
    $includeComment = false;

    if(is_array($params)) {
      if(array_key_exists('itemsQty', $params))
        $itemsQty = $params['itemsQty'];
      if(array_key_exists('comment', $params))
        $comment = $params['comment'];
      if(array_key_exists('email', $params))
        $email = $params['email'];
      if(array_key_exists('includeComment', $params))
        $includeComment = $params['includeComment'];
    }

    $order = Mage::getModel('sales/order')
               ->loadByIncrementId($orderIncrementId);

    if (!$order->getId())
      $this->_fault('order_not_exists');

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

    $shipmentId = $this->create($orderIncrementId,$itemsQty,$comment,$email,
      $includeComment);

    $this->addTrack($shipmentId,$carrier,$title,$trackNumber);

    $orderApi = Mage::getModel('mventory/order_api');

    return $orderApi->fullInfo($orderIncrementId);
  }
}
