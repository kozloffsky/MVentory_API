<?php

class MVentory_Tm_Model_Product_Action extends Mage_Core_Model_Abstract {

  public function rebuildNames ($productIds, $storeId) {
    $numberOfRenamedProducts = 0;

    $templates = array();

    $attributeResource
                   = Mage::getResourceSingleton('mventory_tm/entity_attribute');

    foreach ($productIds as $productId) {
      $product = Mage::getModel('catalog/product')
                   ->setStoreId($storeId)
                   ->load($productId);

      $attributeSetId = $product->getAttributeSetId();

      if (!isset($templates[$attributeSetId])) {
        $templates[$attributeSetId] = null;

        $attribueSet = Mage::getModel('eav/entity_attribute_set')
                        ->load($attributeSetId);

        if (!$attribueSet->getId())
          continue;

        $attributeSetName = $attribueSet->getAttributeSetName();

        $defaultValue = $attributeResource
                          ->getDefaultValueByLabel($attributeSetName, $storeId);

        if ($defaultValue)
          $templates[$attributeSetId] = $defaultValue;
      }

      if (!$templates[$attributeSetId])
        continue;

      $productData = $product->getData();

      $productResource = $product->getResource();

      $search = array();
      $replace = array();

      foreach ($productData as $code => $value) {
        if (substr($code, -1) == '_') {
          $_value = $productResource
                      ->getAttribute($code)
                      ->getFrontend()
                      ->getValue($product);

          $search[] = $code;
          $replace[] = $_value;
        }
      }

      $name = str_replace($search, $replace, $templates[$attributeSetId]);

      if ($name == $templates[$attributeSetId])
        continue;

      $name = trim($name, ', ');

      do {
        $before = strlen($name);

        $name = str_replace(', , ', ', ', $name);

        $after = strlen($name);
      } while ($before != $after);

      if ($name && $name != $product->getName()) {
        $product
          ->setName($name)
          ->save();

        ++$numberOfRenamedProducts;
      }
    }

    return $numberOfRenamedProducts;
  }
  
  /**
   * Populate product attributes      
   * 
   * @param  array  $productIds array of products ids
   * @param  int|null $storeId   
   * @return int     
   */
  public function populateAttributes($productIds, $storeId = null) {
    $numberOfPopulatedProducts = 0;

    foreach ($productIds as $productId) {

      // load product by product id
      $product = Mage::getModel('catalog/product');
      if ($storeId)
        $product->setStoreId($storeId);
      $product = $product->load($productId);

      $updateProduct = false;

      $productData     = $product->getData();
      $productResource = $product->getResource();

      // process product attributes
      foreach ($productData as $code => $value) {

        // filter attributes which have '_' as a last symbol
        if (substr($code, -1) != '_')
          continue;

        // get attribute default value
        $defaultValue = $productResource
                          ->getAttribute($code)
                          ->getDefaultValue();

        // Example default value: 
        // ~approx{/size_width/size_length/size_thickness}

        // skip this attribute if the default value is empty or 
        // the first symbol isn't '~' 
        if (empty($defaultValue) || substr($defaultValue, 0, 1) != '~')
          continue;

        $parts = explode('{', $defaultValue);

        // get function ('approx' for example)
        $func = substr($parts[0], 1);

        // Approximation function
        // See issue 258 for details
        if ($func == 'approx') {

          // get delimeter ('/' for example)
          $delim = substr($parts[1], 0, 1);

          // get approximated attributes codes from default value using delimeter
          $attributeCodes = explode($delim, substr(substr($parts[1], 0, strlen($parts[1]) - 1), 1));
          
          // get attribute value
          $_value = $productResource
                  ->getAttribute($code)
                  ->getFrontend()
                  ->getValue($product);
          
          // get values which needed to be approximated
          $values = explode($delim, $_value);

          foreach ($attributeCodes as $i => $attributeCode) {

            if(!isset($values[$i]))
              break;
            
            // get attribute object from attribute code
            $attribute = $productResource->getAttribute($attributeCode);

            if (!$attribute)
              continue;

            $attributeOptions = $attribute->getSource()->getAllOptions(false);

            // get value for this attribute that needed to be approzimated
            $v = $values[$i];

            $min  = 9999;
            $temp = -1;

            // search the nearest attribute's option for the given value $v
            foreach ($attributeOptions as $attributeOption) {
              // skip non numeric options
              if (!is_numeric($attributeOption['label']))
                continue;

              if (abs($attributeOption['label'] - $v) < $min) {
                $min  = abs($attributeOption['label'] - $v);
                $temp = $attributeOption;
              } elseif (abs($attributeOption['label'] - $v) == $min &&
                      $attributeOption['label'] > $temp['label']) {
                $temp = $attributeOption;
              }
            }

            // check if the nearest attribute's option is found and
            // update value for the approximated attribute only if it's changed
            if ($temp != -1 
                && $product->getData($attributeCode) != $temp['value']) {
                $product->setData($attributeCode, $temp['value']);
                $updateProduct = true;
            }
          }
        }
      }

      if ($updateProduct) {
        $product->save();

        ++$numberOfPopulatedProducts;
      }
    }

    return $numberOfPopulatedProducts;
  }
}

?>
