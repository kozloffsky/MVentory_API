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
 * Catalog product attribute api
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Model_Product_Attribute_Api
  extends Mage_Catalog_Model_Product_Attribute_Api {

  protected $_excludeFromSet = array(
    'old_id' => true,
    'news_from_date' => true,
    'news_to_date' => true,
    'country_of_manufacture' => true,
    'category_id' => true,
    'required_options' => true,
    'has_options' => true,
    'image_label' => true,
    'small_image_label' => true,
    'thumbnail_label' => true,
    'group_price' => true,
    'tier_price' => true,
    'msrp_enabled' => true,
    'minimal_price' => true,
    'msrp_display_actual_price_type' => true,
    'msrp' => true,
    'enable_googlecheckout' => true,
    'meta_title' => true,
    'meta_keyword' => true,
    'meta_description' => true,
    'is_recurring' => true,
    'recurring_profile' => true,
    'custom_design' => true,
    'custom_design_from' => true,
    'custom_design_to' => true,
    'custom_layout_update' => true,
    'page_layout' => true,
    'options_container' => true,
  );

  public function fullInfoList ($setId) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId(null);

    $_attributes = $this->items($setId);

    $attributes = array();

    foreach ($_attributes as $_attribute) {
      if (isset($this->_excludeFromSet[$_attribute['code']]))
        continue;

      $attribute = $this->info($_attribute['attribute_id']);

      $excludeAttribute = false;

      foreach($attribute['frontend_label'] as $label)
      {
        if ($label['store_id'] == $storeId && strcmp($label['label'], '~') == 0)
        {
          $excludeAttribute = true;
          break;
        }
      }

      if ($excludeAttribute)
      {
        continue;
      }

      $attributes[] = array(
        'attribute_id' => $attribute['attribute_id'],
        'attribute_code' => $attribute['attribute_code'],
        'frontend_input' => $attribute['frontend_input'],
        'default_value' => $attribute['default_value'],
        'is_required' => $attribute['is_required'],
        'frontend_label' => $attribute['frontend_label'],
        'options' => $this->optionsPerStoreView(
          $attribute['attribute_id'],
          $storeId
        )
      );
    }

    return $attributes;
  }

  public function addOptionAndReturnInfo ($attribute, $value) {
    $storeId = Mage::helper('mventory_tm')->getCurrentStoreId();

    $attribute = $this->_getAttribute($attribute);
    $attributeId = $attribute->getId();

    $options = Mage::getResourceModel('eav/entity_attribute_option_collection')
                 ->setAttributeFilter($attributeId)
                 ->setStoreFilter($storeId);

    $_value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $value));

    $hasOption = false;

    foreach ($options as $option) {
      $def = $option->getDefaultValue();
      $val = $option->getValue();

      if ($_value == strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $def))) {
        if ($val == '~')
          $this->_removeOptionValue($option->getId(), $storeId);

        $hasOption = true;

        break;
      }

      if ($def != $val
          && $val != '~'
          && $_value == strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $val))) {

        $hasOption = true;

        break;
      }
    }

    if (!$hasOption) {
      try {
        $data = array(
          'label' => array(
            array(
              'store_id' => 0,
              'value' => $value
            )
          ),

          'order' => 0,
          'is_default' => false
        );

        $this->addOption($attributeId, $data);

        $helper = Mage::helper('mventory_tm');

        $subject = 'New attribute value: ' . $value;
        $body = $subject;

        if ($customer = $helper->getCustomerByApiUser())
          $body .= "\n\n"
                   . 'Attribute code: ' . $attribute->getAttributeCode() . "\n"
                   . 'Customer ID: ' . $customer->getId() . "\n"
                   . 'Customer e-mail: ' . $customer->getEmail();

        $helper->sendEmail($subject, $body);
      } catch (Exception $e) {}
    }

    $info = $this->info($attributeId);

    $info['options'] = $this->optionsPerStoreView($attributeId, $storeId);

    return $info;
  }

  private function getOptionLabels($storeId, $attributeId)
  {
    $values = array();

    $valuesCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
      ->setAttributeFilter($attributeId)
      ->setStoreFilter($storeId, false)
      ->load();

    foreach ($valuesCollection as $item) {
      $values[$item->getId()] = $item->getValue();
    }

    return $values;
  }

  private function optionsPerStoreView($attribute, $storeId)
  {
    $attributeModel = Mage::getResourceModel('catalog/eav_attribute')
      ->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());

    if (is_numeric($attribute)) {
      $attributeModel->load(intval($attribute));
    } else {
      $attributeModel->load($attribute, 'attribute_code');
    }

    $attributeId = $attributeModel->getId();

    if (!$attributeId) {
      $this->_fault('not_exists');
    }

    $defaultOptionLabels = $this->getOptionLabels(0, $attributeId);
    $optionLabels = $this->getOptionLabels($storeId, $attributeId);

    $optionsCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
      ->setAttributeFilter($attributeId)
      ->setPositionOrder('desc', true)
      ->load();

    $values = array();

    foreach ($optionsCollection as $option)
    {
      $optionLabel = '~';

      if (isset($optionLabels[$option->getId()])) {
        $optionLabel = $optionLabels[$option->getId()];
      } else {
        $optionLabel = $defaultOptionLabels[$option->getId()];
      }

      if (!is_null($optionLabel) && strcmp($optionLabel, '~') != 0) {
        $values[] = array('value' => $option->getId(),
                          'label' => $optionLabel);
      }
    }
    return $values;
  }

  protected function _removeOptionValue ($optionId, $storeId) {
    $resource = Mage::getSingleton('core/resource');

    $table = $resource->getTableName('eav/attribute_option_value');

    $condition = array(
      'option_id = ?' => $optionId,
      'store_id = ?' => $storeId
    );

    return $resource
             ->getConnection('core_write')
             ->delete($table, $condition);
  }
}
