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
 * Grid of the volume based carrier rates for CSV generation
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Block_Carrier_Volumerate_Grid
  extends Mage_Adminhtml_Block_Widget_Grid {

  /**
   * Define grid properties
   *
   * @return void
   */
  public function __construct () {
    parent::__construct();

    $this->setId('shippingVolumerateGrid');

    $this->_exportPageSize = 10000;
  }

  /**
   * Prepare shipping table rate collection
   *
   * @return Mage_Adminhtml_Block_Shipping_Carrier_Tablerate_Grid
   */
  protected function _prepareCollection () {
    $collection
      = Mage::getResourceModel('mventory/carrier_volumerate_collection')
          ->setWebsiteFilter($this->getWebsiteId());

    $this->setCollection($collection);

    $shippingTypes
      = Mage::getModel('mventory/system_config_source_allowedshippingtypes')
          ->toArray();

    foreach ($collection as $rate) {
      $name = $rate->getConditionName();

      if ($name == 'weight')
        $rate->setWeight($rate->getConditionValue());
      else if ($name == 'volume')
        $rate->setVolume($rate->getConditionValue());

      $shippingType = $rate->getShippingType();

      $shippingType = isset($shippingTypes[$shippingType])
                        ? $shippingTypes[$shippingType]
                          : '';

      $rate->setShippingType($shippingType);
    }

    return parent::_prepareCollection();
  }

  /**
   * Prepare table columns
   *
   * @return Mage_Adminhtml_Block_Widget_Grid
   */
  protected function _prepareColumns () {
    $helper = Mage::helper('mventory');

    $columns = array(
      'shipping_type' => array(
        'header' => $helper->__('Shipping Type'),
        'index' => 'shipping_type',
      ),
      'dest_country' => array(
        'header' => $helper->__('Country'),
        'index' => 'dest_country',
        'default' => '*',
      ),
      'dest_region' => array(
        'header' => $helper->__('Region/State'),
        'index' => 'dest_region',
        'default' => '*',
      ),
      'dest_zip' => array(
        'header' => $helper->__('Zip/Postal Code'),
        'index' => 'dest_zip',
        'default' => '*',
      ),
      'weight' => array(
        'header' => $helper->__('Weight'),
        'index' => 'weight',
      ),
      'volume' => array(
        'header' => $helper->__('Volume'),
        'index' => 'volume',
      ),
      'price' => array(
        'header' => $helper->__('Shipping Price'),
        'index' => 'price',
      ),
      'min_rate' => array(
        'header' => $helper->__('Minimal Rate'),
        'index' => 'min_rate',
      )
    );

    foreach ($columns as $id => $data)
      $this->addColumn($id, $data);

    return parent::_prepareColumns();
  }
}
