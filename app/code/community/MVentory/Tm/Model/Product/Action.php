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
}

?>
