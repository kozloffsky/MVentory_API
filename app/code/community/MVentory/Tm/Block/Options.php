<?php

/**
 * Grid of the TM options for CSV generation
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Block_Options
  extends Mage_Adminhtml_Block_Widget_Grid {

  protected $_helper = null;
  protected $_options = null;

  /**
   * Define grid properties
   *
   * @return void
   */
  public function __construct () {
    parent::__construct();

    $this->setId('optionsGrid');

    $this->_exportPageSize = 10000;

    $this->_helper = Mage::helper('mventory_tm/tm');

    $this->_options = array(
      'account_name' => 'Account',
      'shipping_type' => 'Shipping type',
      'minimal_price' => 'Minimal price',
      'free_shipping_cost' => 'Free shipping cost',
      'allow_buy_now' => 'Allow Buy Now',
      'avoid_withdraval' => 'Avoid withdrawal',
      'add_fees' => 'Add TM fees',
      'allow_pickup' => 'Allow pickup',
      'category_image' => 'Add category image',
      'buyer' => 'TM buyer ID',
      'footer' => 'Footer description'
    );
  }

  /**
   * Prepare options collection
   *
   * @return MVentory_Tm_Block_Options
   */
  protected function _prepareCollection () {
    $collection = new Varien_Data_Collection();

    $accounts = $this->_helper->getAccounts($this->getWebsiteId(), false);
    $_shippingTypes = $this->_getShippingTypes();

    if (count($accounts) && count($_shippingTypes))
      foreach ($accounts as $account) {
        $hasShippingTypes = isset($account['shipping_types'])
                            && count($account['shipping_types']);

        $shippingTypes = $hasShippingTypes
                           ? $account['shipping_types']
                             : $this->_fillShippingTypes($_shippingTypes);

        foreach ($shippingTypes as $id => $options) {
          $row = $options + array(
            'account_name' => $account['name'],
            'shipping_type' => $_shippingTypes[$id]
          );

          $collection->addItem(new Varien_Object($row));
        }
      }

    $this->setCollection($collection);

    return parent::_prepareCollection();
  }

  /**
   * Return array with empty options for all shipping types
   *
   * @param array $shippingTypes
   *
   * @return array
   */
  protected function _fillShippingTypes ($shippingTypes) {
    $emptyOptions = array_fill_keys(array_keys($this->_options), '');

    unset($emptyOptions['account_name'], $emptyOptions['shipping_type']);

    return array_fill_keys(array_keys($shippingTypes), $emptyOptions);
  }

  /**
   * Return all shipping options
   *
   * @return array
   */
  protected function _getShippingTypes () {
    return
      Mage::getModel('mventory_tm/system_config_source_allowedshippingtypes')
        ->toArray();
  }

  /**
   * Prepare table columns
   *
   * @return Mage_Adminhtml_Block_Widget_Grid
   */
  protected function _prepareColumns () {
    foreach ($this->_options as $id => $label)
      $this->addColumn($id, array(
        'header' => $this->_helper->__($label),
        'index' => $id
      ));

    return parent::_prepareColumns();
  }
}
