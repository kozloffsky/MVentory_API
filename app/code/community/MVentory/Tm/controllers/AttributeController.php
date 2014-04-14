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
 * Controller for product attribute
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_Tm_AttributeController extends Mage_Adminhtml_Controller_Action
{

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Add/remove attribute to/from MVentory
   */
  public function convertAction () {
    $id = (int) $this->getRequest()->getParam('attribute_id');

    if (!$id)
      return $this->_redirect('adminhtml/catalog_product_attribute');

    $attr = Mage::getModel('catalog/resource_eav_attribute')
      ->setEntityTypeId(
          Mage::getModel('eav/entity')
            ->setType(Mage_Catalog_Model_Product::ENTITY)
            ->getTypeId()
        )
      ->load($id);

    if (!$id = $attr->getId())
      return $this->_redirect('adminhtml/catalog_product_attribute');

    $code = substr($code = $attr->getAttributeCode(), -1) === '_'
              ? substr($code, 0, -1)
                : $code . '_';

    $session = Mage::getSingleton('adminhtml/session');

    try {
      $attr
        ->setAttributeCode($code)
        ->save();

      foreach (Mage::getModel('index/indexer')->getProcessesCollection() as $p)
        $p->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);

      $app = Mage::app();

      $app->cleanCache();
      Mage::dispatchEvent('adminhtml_cache_flush_system');

      $helper = Mage::helper('adminhtml');

      $session->addSuccess(
        $helper->__('The Magento cache storage has been flushed.')
      );

      Mage::dispatchEvent('adminhtml_cache_flush_all');
      $app->getCacheInstance()->flush();

      $session->addSuccess($helper->__('The cache storage has been flushed.'));
    } catch (Exception $e) {
      $session->addError($e->getMessage());
    }

    return $this->_redirect(
      'adminhtml/catalog_product_attribute/edit',
      array('_current' => true)
    );
  }
}
