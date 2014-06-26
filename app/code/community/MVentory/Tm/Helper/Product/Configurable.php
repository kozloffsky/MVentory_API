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
 * Configurable product helper
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Helper_Product_Configurable
  extends MVentory_API_Helper_Product {

  public function getIdByChild ($child) {
    $id = $child instanceof Mage_Catalog_Model_Product
            ? $child->getId()
              : $child;

    if (!$id)
      return $id;

    $configurableType
      = Mage::getResourceSingleton('catalog/product_type_configurable');

    $parentIds = $configurableType->getParentIdsByChild($id);

    //Get first ID because we use only one configurable product
    //per simple product
    return $parentIds ? $parentIds[0] : null;
  }

  public function getChildrenIds ($configurable) {
    $id = $configurable instanceof Mage_Catalog_Model_Product
            ? $configurable->getId()
              : $configurable;

    $ids = Mage::getResourceSingleton('catalog/product_type_configurable')
             ->getChildrenIds($id);

    return $ids[0] ? $ids[0] : array();
  }

  public function getSiblingsIds ($product) {
    $id = $product instanceof Mage_Catalog_Model_Product
            ? $product->getId()
              : $product;

    if (!$configurableId = $this->getIdByChild($id))
      return array();

    if (!$ids = $this->getChildrenIds($configurableId))
      return array();

    //Unset product'd ID
    unset($ids[$id]);

    return $ids;
  }

  public function create ($product, $data = array()) {
    $sku = microtime();

    $data['sku'] = 'C' . substr($sku, 11) . substr($sku, 2, 6);

    $data += array(
      'stock_data' => array(
        'is_in_stock' => true
      )
    );

    $data['type_id'] = Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE;
    $data['status'] = Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
    $data['visibility'] = 4;
    $data['name'] = $product->getName();
    $data['mv_attributes_hash'] = $product->getData('mv_attributes_hash');

    //Reset value of attributes
    $data['product_barcode_'] = null;
    $data['mv_stock_journal'] = null;

    //Load media gallery if it's not loaded automatically (e.g. the product
    //is loaded in collection) to duplicate images
    if (!$product->getData('media_gallery'))
      Mage::getModel('catalog/product_attribute_backend_media')
        ->setAttribute(new Varien_Object(array(
            'id' => Mage::getResourceModel('eav/entity_attribute')
                      ->getIdByCode(
                          Mage_Catalog_Model_Product::ENTITY,
                          'media_gallery'
                        ),
            'attribute_code' => 'media_gallery'
          )))
        ->afterLoad($product);

    $configurable = $product
                      ->setData('mventory_update_duplicate', $data)
                      ->duplicate()
                      //Unset 'is duplicate' flag to prevent duplicating
                      //of images on subsequent saves
                      ->setIsDuplicate(false);

    if ($configurable->getId())
      return $configurable;
  }

  public function getConfigurableAttributes ($configurable) {
    return ($attrs = $configurable->getConfigurableAttributesData())
             ? $attrs
               : $configurable
                   ->getTypeInstance()
                   ->getConfigurableAttributesAsArray();
  }

  public function setConfigurableAttributes ($configurable, $attributes) {
    $configurable
      ->setConfigurableAttributesData($attributes)
      ->setCanSaveConfigurableAttributes(true);

    return $this;
  }

  public function addOptions ($configurable, $attribute, $products) {
    $_options = $attribute
                  ->getSource()
                  ->getAllOptions(false, true);

    if (!$_options)
      return $this;

    foreach ($_options as $option)
      $options[(int) $option['value']] = $option['label'];

    unset($_options);

    $id = $attribute->getAttributeId();
    $code = $attribute->getAttributeCode();

    $attributes = $this->getConfigurableAttributes($configurable);

    foreach ($attributes as &$data) {
      if ($data['attribute_id'] != $id)
        continue;

      $usedValues = $this->hasOptions($configurable, $attribute);

      foreach ($products as $product) {
        $value = $product->getData($code);

        if (isset($usedValues[$value]) || !isset($options[$value]))
          continue;

        $label = $options[$value];

        $data['values'][] = array(
          'value_index' => $value,
          'label' => $label,
          'default_label' => $label,
          'store_label' => $label,
          'is_percent' => 0,
          'pricing_value' => ''
        );
      }

      return $this->setConfigurableAttributes($configurable, $attributes);
    }

    return $this;
  }

  public function hasOptions ($configurable, $attribute) {
    $id = $attribute->getAttributeId();

    foreach ($this->getConfigurableAttributes($configurable) as $_attribute) {
      if ($_attribute['attribute_id'] != $id)
        continue;

      if (isset($_attribute['values']) && $_attribute['values']) {
        foreach ($_attribute['values'] as $value)
          $usedValues[(int) $value['value_index']] = true;

        return $usedValues;
      }
    }

    return false;
  }

  public function removeOption ($configurable, $attribute, $product) {
    $id = $attribute->getAttributeId();
    $value = $product->getData($attribute->getAttributeCode());
    $attributes = $this->getConfigurableAttributes($configurable);

    foreach ($attributes as &$_attribute) {
      if (!($_attribute['attribute_id'] == $id
            && isset($_attribute['values'])
            && $_attribute['values']))
        continue;

      foreach ($_attribute['values'] as $valueId => $_value)
        if ($_value['value_index'] == $value)
          unset($_attribute['values'][$valueId]);
    }

    $this->setConfigurableAttributes($configurable, $attributes);

    return $this;
  }

  public function addAttribute ($configurable, $attribute, $products) {
    if ($this->hasAttribute($configurable, $attribute))
      return $this->addOptions($configurable, $attribute, $products);

    $attributes = $this->getConfigurableAttributes($configurable);

    $attributes[] = array(
      'label' => $attribute->getStoreLabel(),
      'use_default' => true,
      'attribute_id' => $attribute->getAttributeId(),
      'attribute_code' => $attribute->getAttributeCode()
    );

    $this->setConfigurableAttributes($configurable, $attributes);

    return $this->addOptions($configurable, $attribute, $products);
  }

  public function hasAttribute ($configurable, $attribute) {
    $id = $attribute->getId();

    foreach ($this->getConfigurableAttributes($configurable) as $attribute)
      if ($attribute['attribute_id'] == $id)
        return true;

    return false;
  }

  public function recalculatePrices ($configurable, $attribute, $products) {
    $code = $attribute->getAttributeCode();

    $prices = array();
    $min = INF;

    //Find minimal price in products
    foreach ($products as $product) {
      if (($price = $product->getPrice()) < $min)
        $min = $price;

      $prices[(int) $product->getData($code)] = $price;
    }

    $id = $attribute->getAttributeId();

    $attributes = $this->getConfigurableAttributes($configurable);

    //Update prices
    foreach ($attributes as &$_attribute)
      if ($_attribute['attribute_id'] == $id) {
        foreach ($_attribute['values'] as &$values)
          if (isset($prices[$values['value_index']]))
            $values['pricing_value'] = $prices[$values['value_index']] - $min;

        break;
      }

    $this->setConfigurableAttributes($configurable, $attributes);

    $configurable->setPrice($min);

    return $this;
  }

  public function assignProducts ($configurable, $products) {
    foreach ($products as $product)
      $ids[] = $product->getId();

    $configurable->setConfigurableProductsData(array_flip(array_merge(
      $configurable->getTypeInstance()->getUsedProductIds(),
      $ids
    )));

    return $this;
  }

  public function unassignProduct ($configurable, $product) {
    $ids = array_flip($configurable->getTypeInstance()->getUsedProductIds());

    unset($ids[$product->getId()]);

    $configurable->setConfigurableProductsData($ids);

    return $this;
  }

  public function shareDescription ($configurable, $products, $description) {
    $description = trim($description);

    if (!$description)
      return this;

    foreach ($products as $product)
      $product
        ->setShortDescription($description)
        ->setDescription($description);

    $configurable
      ->setShortDescription($description)
      ->setDescription($description);

    return $this;
  }

  public function updateDescription ($configurable, $product) {
    $desc = trim($product->getDescription());
    $currentDesc = trim($configurable->getDescription());

    if (!$desc)
      return $currentDesc;

    if (!$currentDesc) {
      $configurable->setData('mventory_update_description', true);

      return $desc;
    }

    $search = array(' ', "\r", "\n");

    $_desc = str_replace($search, '', strtolower($desc));
    $_currentDesc = str_replace($search, '', strtolower($currentDesc));

    if (strpos($_currentDesc, $_desc) !== false)
      return $currentDesc;

    $configurable->setData('mventory_update_description', true);

    return $currentDesc . "\r\n" . $desc;
  }
}
