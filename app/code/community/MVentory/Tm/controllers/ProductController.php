<?php

/**
 * Product controller
 *
 * @category   MVentory
 * @package    MVentory_Tm
 */

require_once Mage::getModuleDir('controllers', 'Mage_Catalog')
             . DS
             . 'ProductController.php';

class MVentory_Tm_ProductController extends Mage_Catalog_ProductController {

  public function preDispatch () {
    Mage::getSingleton('core/session', array('name' => 'adminhtml'))
      ->start();

    Mage::register('is_admin_logged',
                   Mage::getSingleton('admin/session')->isLoggedIn());

    parent::preDispatch();

    return $this;
  }
}
