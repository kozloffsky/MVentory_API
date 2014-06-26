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
 * Resource model for order transaction
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Resource_Order_Transaction
  extends Mage_Core_Model_Resource_Db_Abstract {

  protected function _construct() {
    $this->_init('mventory/order_transaction', 'id');
  }

  public function getOrderIdByTransaction ($transactionId) {
    return $this
             ->getReadConnection()
             ->fetchOne('select order_id from '
                        . $this->getMainTable()
                        . ' where transaction_id = ?', $transactionId);
  }
}
