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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Catalog product attribute api
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
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

  /**
   * Get information about attribute with list of options
   *
   * @param integer|string $attribute attribute ID or code
   * @return array
   */
  public function info ($attribute) {
    $storeId = Mage::helper('mventory')->getCurrentStoreId();

    $data = parent::info($attribute);

    $label = $data['frontend_label'][0]['label'];

    foreach($data['frontend_label'] as $_label)
      if ($_label['store_id'] == $storeId) {
        $label = $_label['label'];
        break;
      }

    return array(
      'attribute_id' => $data['attribute_id'],
      'attribute_code' => $data['attribute_code'],
      'frontend_input' => $data['frontend_input'],
      'default_value' => $data['default_value'],
      'is_required' => $data['is_required'],
      'is_configurable' => $data['is_configurable'],
      'label' => $label,

      //!!!DEPRECATED: replaced by 'label' key
      //!!!TODO: remove after the app will have been upgraded
      'frontend_label' => array(
        array('store_id' => 0, 'label' => $label)
      ),

      'options' => $this->optionsPerStoreView(
        $data['attribute_id'],
        $storeId
      )
    );
  }

  public function fullInfoList ($setId) {
    $_attributes = $this->items($setId);

    $attributes = array();

    foreach ($_attributes as $_attribute) {
      if (isset($this->_excludeFromSet[$_attribute['code']]))
        continue;

      $attribute = $this->info($_attribute['attribute_id']);

      if ($attribute['label'] == '~')
        continue;

      $attributes[] = $attribute;
    }

    return $attributes;
  }

  public function addOptionAndReturnInfo ($attribute, $value) {
    $storeId = Mage::helper('mventory')->getCurrentStoreId();

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

        $helper = Mage::helper('mventory');

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

    return $this->info($attributeId);
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
      } elseif (isset($defaultOptionLabels[$option->getId()])) {
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
