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
 * Admin dashboard Stock Info tab block
 *
 * @package MVentory/API
 */
class MVentory_API_Block_Adminhtml_Dashboard_Tab_Stock
  extends Mage_Adminhtml_Block_Template {

  protected $_totalStockQty;
  protected $_totalStockValue;

  const USE_CACHE = false;
  const CACHE_TYPE = 'mventory';
  const CACHE_TAG = 'MVENTORY';

  public function __construct() {
    parent::__construct();
    $this->setTemplate('mventory/dashboard/tab/stock.phtml');
  }

  /**
   * Get current selected store view
   */
  protected function _getStore() {
    $storeId = (int) $this->getRequest()->getParam('store', 0);
    return Mage::app()->getStore($storeId);
  }

  /**
   * Get "In Stock" product collection by store view
   */
  protected function _prepareCollection() {
    $store      = $this->_getStore();
    $collection = Mage::getModel('catalog/product')->getCollection();

    if (Mage::helper('catalog')->isModuleEnabled('Mage_CatalogInventory')) {
      $collection
        ->joinField('qty',
                    'cataloginventory/stock_item',
                    'qty', 'product_id=entity_id',
                    '{{table}}.stock_id=1 AND {{table}}.is_in_stock=1
                    AND {{table}}.manage_stock=1 AND {{table}}.qty>0', 'left');
    }
    if ($store->getId()) {
      //$collection->setStoreId($store->getId());
      $collection->addStoreFilter($store);

      $collection->joinAttribute(
        'price',
        'catalog_product/price',
        'entity_id',
        null,
        'left',
        $store->getId()
      );
    } else {
      $collection->addAttributeToSelect('price');
    }

    $collection->joinAttribute(
      'status',
      'catalog_product/status',
      'entity_id',
      null,
      'inner',
      $store->getId());
    $collection->joinAttribute(
      'visibility',
      'catalog_product/visibility',
      'entity_id',
      null,
      'inner',
      $store->getId());

    return $collection;
  }

  /**
   * Preload Total Stock Qty and Total Stock Value
   */
  public function _prepareLayout() {
    $cache = Mage::getSingleton('core/cache');

    $storeId = $this->_getStore()->getId();

    $this->_totalStockQty = $cache->load("total_stock_qty_" . $storeId);
    $this->_totalStockValue = $cache->load("total_stock_value_" . $storeId);
    if ($this->_totalStockQty === false || $this->_totalStockValue === false) {

      $productsCollection = $this->_prepareCollection();

      $this->_totalStockQty = 0;
      $this->_totalStockValue = 0;
      foreach ($productsCollection as $product) {
        $this->_totalStockQty += $product->getQty();
        $this->_totalStockValue += $product->getQty() * $product->getPrice();
      }

      // save to cache if needed
      if (self::USE_CACHE && $cache->canUse(self::CACHE_TYPE)) {
        $cache->save(
          $this->_totalStockQty,
          'total_stock_qty_' . $storeId,
          array(self::CACHE_TAG)
        );

        $cache->save(
          $this->_totalStockValue,
          'total_stock_value_' . $storeId,
          array(srlf::CACHE_TAG)
        );
      }
    }
  }

  public function getTotalStockQty() {
    return $this->_totalStockQty;
  }

  public function getTotalStockValue() {
    return $this->_totalStockValue;
  }
}
