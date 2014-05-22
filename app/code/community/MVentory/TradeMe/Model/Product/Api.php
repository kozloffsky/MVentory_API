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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * TradeMe product API
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Product_Api extends MVentory_Tm_Model_Product_Api
{
  public function submit ($productId, $data) {
    $product = $this->_getProduct($productId, null, 'id');

    if (is_null($product->getId()))
      $this->_fault('product_not_exists');

    $match = Mage::getModel('trademe/matching')->matchCategory($product);

    if (!(isset($match['id']) && $match['id'] > 0))
      $this->_fault('unable_to_match_category');

    $connector = new MVentory_TradeMe_Model_Api();

    $result = $connector->send(
      $product,
      $match['id'],
      $data['account_id']
    );

    if (is_int($result))
      $product
        ->setTmCurrentListingId($result)
        ->setTmListingId($result)
        ->save();

    $_result = $this->fullInfo($productId, 'id');

    if (!is_int($result))
      $_result['tm_error'] = $result;

    return $_result;
  }
}