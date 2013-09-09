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
 * Catalog product attribute set api
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Product_Attribute_Set_Api
  extends Mage_Catalog_Model_Product_Attribute_Set_Api {

  public function fullInfoList () {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId(null);

    $attributeApi = Mage::getModel('mventory_tm/product_attribute_api');

    $sets = $this->itemsPerStoreView($storeId);

    foreach ($sets as &$set)
      $set['attributes'] = $attributeApi
                             ->fullInfoList($set['set_id']);

    return $sets;
  }

  private function itemsPerStoreView($storeId)
  {
    $attributeApi = Mage::getModel('mventory_tm/product_attribute_api');
    $attributeModel = Mage::getModel('catalog/resource_eav_attribute')
      ->setEntityTypeId(Mage::getModel('eav/entity')->setType(Mage_Catalog_Model_Product::ENTITY)->getTypeId());

    $entityType = Mage::getModel('catalog/product')->getResource()->getEntityType();
    $collection = Mage::getResourceModel('eav/entity_attribute_set_collection')
      ->setEntityTypeFilter($entityType->getId());

    $result = array();

    foreach ($collection as $attributeSet)
    {
      /* Assume the set needs to be included in the response but try to prove otherwise */
      $isIncluded = true;

      $attributesList = $attributeApi->items($attributeSet->getId());

      /* Search for the formatting attribute */
      foreach ($attributesList as $attribute)
      {
        $attributeModel->load($attribute['attribute_id']);

        $frontendLabel = $attributeModel->getFrontend()->getLabel();
        if (is_array($frontendLabel))
        {
          $frontendLabel = array_shift($frontendLabel);
        }

        if (strcmp($frontendLabel, $attributeSet->getAttributeSetName()) == 0)
        {
          /* We found the formatting attribute. Check if its label for the current storeview equals "~"
           * and exclude the entire attribute set if this is true. */
          $storeLabels = $attributeModel->getStoreLabels();
          $storeLabel = isset($storeLabels[$storeId]) ? $storeLabels[$storeId] : '';
              	
          if (strcmp($storeLabel, "~") == 0)
          {
            $isIncluded = false;

            /* We have just proven this attribute set needs to be excluded. No need to iterate anymore. */
            break;
          }
        }
      }

      if ($isIncluded)
      {
        $result[] = array(
          'set_id' => $attributeSet->getId(),
          'name'   => $attributeSet->getAttributeSetName()
        );
      }
    }

    return $result;
  }
}
