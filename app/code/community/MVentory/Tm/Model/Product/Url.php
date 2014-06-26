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
 * Product Url model
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Product_Url extends Mage_Catalog_Model_Product_Url {

  /**
   * Check product category
   *
   * The method is redifined to use first category the product is assigned
   * if there's no current category, i.e. generating URL in product editor
   * in the admin interface or in list of latest products on homepage.
   *
   * @param Mage_Catalog_Model_Product $product
   * @param array $params
   *
   * @return int|null
   */
  protected function _getCategoryIdForUrl ($product, $params) {
    $id = parent::_getCategoryIdForUrl($product, $params);

    if (isset($params['_ignore_category']) || $id !== null)
      return $id;

    $categories = $product->getCategoryIds();

    return isset($categories[0]) ? $categories[0] : null;
  }
}
