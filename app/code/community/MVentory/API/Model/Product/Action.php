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
 * Product massactions
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Product_Action extends Mage_Core_Model_Abstract {

  public function rebuildNames ($productIds, $storeId) {
    $numberOfRenamedProducts = 0;

    $templates = array();
    $frontends = array();

    $attributeResource
                   = Mage::getResourceSingleton('mventory/entity_attribute');

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

        if ($defaultValue) {
          $templates[$attributeSetId] = $defaultValue;

          $attrs = Mage::getResourceModel('eav/entity_attribute_collection')
                     ->setAttributeSetFilter($attributeSetId);

          foreach ($attrs as $attr) {
            $code = $attr->getAttributeCode();

            if (isset($frontends[$code]))
              continue;

            $resource = $product->getResource();

            $frontends[$code] = $attr
                                  ->setEntity($resource)
                                  ->getFrontend();

            $sortFrontends = true;
          }

          unset($attrs);

          if (isset($sortFrontends) && $sortFrontends)
            uksort(
              $frontends,
              function ($a, $b) { return strlen($a) < strlen($b); }
            );
        }
      }

      if (!$templates[$attributeSetId])
        continue;

      $mapping = array();

      foreach ($frontends as $code => $frontend) {
        $value = $frontend->getValue($product);

        //Try converting value of the field to a string. Set it to empty if
        //value of the field is array or class which doesn't support convertion
        //to string
        try {
          $value = (string) $value;

          //Ignore 'n/a', 'n-a', 'n\a' and 'na' values
          //Note: case insensitive comparing; delimeter can be surrounded
          //      with spaces
          if (preg_match('#^n(\s*[/-\\\\]\s*)?a$#i', trim($value)))
            $value = '';
        } catch (Exception $e) {
          $value = '';
        }

        $mapping[$code] = $value;
      }

      //Sort array by key length (desc)
      uksort($mapping, function ($a, $b) { return strlen($a) < strlen($b); });

      $name = explode(' ', $templates[$attributeSetId]);

      $replace = function (&$value, $key, $mapping) {
        foreach ($mapping as $search => $replace)
          if (($replaced = str_replace($search, $replace, $value)) !== $value)
            return $value = $replaced;
      };

      if (!array_walk($name, $replace, $mapping))
        continue;

      $name = implode(' ', $name);

      if ($name == $templates[$attributeSetId])
        continue;

      $name = trim($name, ', ');

      $name = preg_replace_callback(
        '/(?<needle>\w+)(\s+\k<needle>)+\b/i',
        function ($match) { return $match['needle']; },
        $name
      );

      //Remove duplicates of spaces and punctuation 
      $name = preg_replace(
        '/([,.!?;:\s])\1*(\s?)(\2)*(\s*\1\s*)*/',
        '\\1\\2',
        $name
      );

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
   * @param  array  $productIds array of products ids or products
   * @param  int|null $storeId
   * @param  bool $save
   * @return int
   */
  public function populateAttributes($productIds,
                                     $storeId = null,
                                     $save = true) {
    $numberOfPopulatedProducts = 0;

    foreach ($productIds as $productId) {

      if(is_numeric($productId)) {
        // load product by product id
        $product = Mage::getModel('catalog/product');
        if ($storeId)
          $product->setStoreId($storeId);
        $product = $product->load($productId);
      } else {
        $product = $productId;
      }

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

      if ($updateProduct && $save) {
        $product->save();

        ++$numberOfPopulatedProducts;
      }
    }

    return $numberOfPopulatedProducts;
  }

  public function matchCategories ($productIds) {
    $n = 0;

    foreach ($productIds as $productId) {
      $product = Mage::getModel('catalog/product')->load($productId);

      if (!$product->getId())
        continue;

      $category = Mage::getModel('mventory/matching')
        ->matchCategory($product);

      if ($category) {
        $product
          ->setCategoryIds((string) $category)
          ->setIsMventoryCategoryMatched(true)
          ->save();

        $n++;
      }
    }

    return $n;
  }
}

?>
