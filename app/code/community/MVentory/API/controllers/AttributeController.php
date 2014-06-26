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
 * Controller for product attribute
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_AttributeController extends Mage_Adminhtml_Controller_Action
{

  const __ATTR_CHANGED = <<<'EOT'
Warning: the attribute code changed from %s to %s
EOT;

  const __MORE_INFO = <<<'EOT'
<a target="_blank" href="http://mventory.com/help/magento-attributes/">More info &hellip;</a>
EOT;

  protected function _construct() {
    $this->setUsedModuleName('MVentory_API');
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

      $session->addWarning($this->__(
        self::__ATTR_CHANGED,
        $attr->getOrigData('attribute_code'),
        $attr->getData('attribute_code')
      ));

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

      $session->addSuccess($this->__(self::__MORE_INFO));
    } catch (Exception $e) {
      $session->addError($e->getMessage());
    }

    return $this->_redirect(
      'adminhtml/catalog_product_attribute/edit',
      array('_current' => true)
    );
  }
}
