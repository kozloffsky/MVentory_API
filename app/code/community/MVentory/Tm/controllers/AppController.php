<?php

class MVentory_Tm_AppController
  extends Mage_Core_Controller_Front_Action {

  public function profileAction () {
    $key = urldecode($this->getRequest()->getParam('key'));

    if (!($key && strlen($key) == 16)) {
      $this->norouteAction();
      return;
    }

    $customer = Mage::getResourceModel('customer/customer_collection')
      ->addAttributeToFilter(
          'mventory_app_profile_key',
          array('like' => $key . '-%')
        );

    if (!$customer->count()) {
      $this->norouteAction();
      return;
    }

    $customer = $customer->getFirstItem();
    $user = Mage::getModel('api/user')->loadByUsername($customer->getId());

    if (!$user->getId()) {
      $this->norouteAction();
      return;
    }

    if (($websiteId = $customer->getWebsiteId()) === null) {
      $this->norouteAction();
      return;
    }

    $store = Mage::app()
      ->getWebsite($websiteId)
      ->getDefaultStore();

    if ($store->getId() === null) {
      $this->norouteAction();
      return;
    }

    $timestamp = (float) substr(
      $customer->getData('mventory_app_profile_key'),
      17
    );

    if (!($timestamp && microtime(true) < $timestamp)) {
      $this->norouteAction();
      return;
    }

    $apiKey = base64_encode(mcrypt_create_iv(9));

    $user
      ->setIsActive(true)
      ->setApiKey($apiKey)
      ->save();

    $customer
      ->setData('mventory_app_profile_key', '')
      ->save();

    $output = $user->getUsername() . "\n"
              . $apiKey . "\n"
              . $store->getBaseUrl() . "\n";

    $response = $this->getResponse();

    $response
      ->setHttpResponseCode(200)
      ->setHeader('Content-type', 'text/plain', true)
      ->setHeader('Content-Length', strlen($output))
      ->clearBody();

     $response->setBody($output);
  }
}
