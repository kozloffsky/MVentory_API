<?php

class MVentory_Tm_Helper_Product_Configurable
  extends MVentory_Tm_Helper_Product {

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
    $data['tm_relist'] = 0;
    $data['product_barcode_'] = null;
    $data['mv_stock_journal'] = null;

    $configurable = $product
                      ->setData('mventory_assigned_new_to_configurable', false)
                      ->setData('mventory_update_duplicate', $data)
                      ->duplicate();

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
}
