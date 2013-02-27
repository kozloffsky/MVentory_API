<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog product media api
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Product_Attribute_Media_Api
  extends Mage_Catalog_Model_Product_Attribute_Media_Api {

  public function createAndReturnInfo ($productId, $data, $storeId = null,
                                       $identifierType = null) {

    //$storeId = Mage::helper('mventory_tm')->getCurrentStoreId($storeId);

    //Temp solution, apply image settings globally
    $storeId = null;

    $images = $this->items($productId, $storeId, $identifierType);

    $hasMainImage = false;
    $hasSmallImage = false;
    $hasThumbnail = false;

    foreach ($images as $image) {
      if (in_array('image', $image['types']))
        $hasMainImage = true;

      if (in_array('small_image', $image['types']))
        $hasSmallImage = true;

      if (in_array('thumbnail', $image['types']))
        $hasThumbnail = true;
    }

    if (!$hasMainImage)
      $data['types'][] = 'image';

    if (!$hasSmallImage)
      $data['types'][] = 'small_image';

    if (!$hasThumbnail)
      $data['types'][] = 'thumbnail';

    //Exclude uploaded image from additional images on product page
    //if it's a first uploaded image of a product
    if (!count($images))
      $data['exclude'] = 1;

    $this->create($productId, $data, $storeId, $identifierType);

    $productApi = Mage::getModel('mventory_tm/product_api');

    return $productApi->fullInfo($identifierType == 'sku' ? null : $productId,
                                 $productId);
  }

  /**
   * Retrieve product
   *
   * The function is redefined to check if api user has access to the product
   *
   * @param int|string $productId
   * @param string|int $store
   * @param  string $identifierType
   * @return Mage_Catalog_Model_Product
   */
  protected function _initProduct($productId, $store = null,
                                  $identifierType = null) {

    if (!Mage::helper('mventory_tm/product')
           ->hasApiUserAccess($productId, $identifierType))
      $this->_fault('access_denied');

    return parent::_initProduct($productId, $store, $identifierType);
  }
}
