<?php
/**
 * Admin dashboard Stock Info tab block
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Block_Adminhtml_Dashboard_Tab_Stock 
  extends Mage_Adminhtml_Block_Template {
  
  protected $_totalStockQty;
  protected $_totalStockValue;
  
  const USE_CACHE = false;

  public function __construct() {
    parent::__construct();
    $this->setTemplate('mventory_tm/dashboard/tab/stock.phtml');
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
      if (self::USE_CACHE 
          && $cache->canUse(MVentory_Tm_Model_Connector::CACHE_TYPE_TM)) {
        $cache->save($this->_totalStockQty,
                     "total_stock_qty_" . $storeId,
                     array(MVentory_Tm_Model_Connector::CACHE_TAG_TM));
        $cache->save($this->_totalStockValue,
                     "total_stock_value_" . $storeId,
                     array(MVentory_Tm_Model_Connector::CACHE_TAG_TM));
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
