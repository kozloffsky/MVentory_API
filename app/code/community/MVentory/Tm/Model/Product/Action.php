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
  
  public function populateAttributes ($productIds, $storeId = null) {
    $numberOfPopulatedProducts = 0;

    foreach ($productIds as $productId) {
      $product = Mage::getModel('catalog/product');
      if($storeId) $product->setStoreId($storeId);
      $product = $product->load($productId);

      $updateProduct = false;

      $productData = $product->getData();
      $productResource = $product->getResource();

      foreach ($productData as $code => $value) {
        if (substr($code, -1) == '_') {
          $_value = $productResource
                      ->getAttribute($code)
                      ->getFrontend()
                      ->getValue($product);
          $defaultValue = $productResource
                      ->getAttribute($code)
                      ->getDefaultValue();

          if(empty($defaultValue)) continue;
          if (substr($defaultValue, 0, 1) != '~') continue;

          $parts = explode('{', $defaultValue);
          $func = substr($parts[0], 1);

          if($func == 'approx') {
            $delim = substr($parts[1], 0, 1);
            $attributeCodes = explode($delim, substr( substr($parts[1], 0, strlen($parts[1])-1), 1) );

            $values = explode($delim, $_value);
            
            foreach($attributeCodes as $i => $attributeCode) {
              $attribute = $productResource->getAttribute($attributeCode);

              if(!$attribute) continue;
              
              $attributeOptions = $attribute->getSource()->getAllOptions(false);

              $v = $values[$i];
              $min = 9999;
              $temp = -1;
              foreach($attributeOptions as $attributeOption) {
                if(!is_numeric($attributeOption['label'])) continue;
                
                if(abs($attributeOption['label'] - $v) < $min) {
                  $min = abs($attributeOption['label'] - $v);
                  $temp = $attributeOption;
                } elseif(abs($attributeOption['label'] - $v) == $min &&
                         $attributeOption['label'] > $temp['label']) {
                  $temp = $attributeOption;
                } 
              }
              if($temp != -1) {
                if($product->getData($attributeCode) != $temp['value']) {
                  $product->setData($attributeCode, $temp['value']);
                  $updateProduct = true;
                }
              }
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
