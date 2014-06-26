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
 * Collection for the volume based shipping carrier model
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Resource_Carrier_Volumerate_Collection
  extends Mage_Shipping_Model_Resource_Carrier_Tablerate_Collection {

  /**
   * Define resource model and item
   */
  protected function _construct () {
    $this->_init('mventory/carrier_volumerate');

    $this->_shipTable = $this->getMainTable();
    $this->_countryTable = $this->getTable('directory/country');
    $this->_regionTable = $this->getTable('directory/country_region');
  }
}
