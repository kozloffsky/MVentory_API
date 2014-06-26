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
 * Source model for allowed shipping types field
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_System_Config_Source_Allowedshippingtypes {

  protected $_options = array();

  public function __construct () {
    $attribute = Mage::getModel('eav/entity_attribute')
                   ->loadByCode(Mage_Catalog_Model_Product::ENTITY,
                                'mv_shipping_');

    if ($attribute->getId())
      $this->_options = $attribute
                   ->getSource()
                   ->getAllOptions(false);
  }

  /**
   * Options getter
   *
   * @return array
   */
  public function toOptionArray () {
    return $this->_options;
  }

  /**
   * Return options as id => label array
   *
   * @return array
   */
  public function toArray () {
    $options = array();

    foreach ($this->_options as $option)
      $options[$option['value']] = $option['label'];

    return $options;
  }
}

?>
