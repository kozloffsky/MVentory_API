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
 * Product entity resource model
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Resource_Product
  extends Mage_Catalog_Model_Resource_Product {

  /**
   * Retrieve attribute's raw value from DB.
   * Original implementation (from parent) is modified to return attribute's
   * value for specified store then there's no value for default store
   *
   * @param int $entityId
   * @param int|string|array $attribute Atrribute's ids or codes
   * @param int|Mage_Core_Model_Store $store
   *
   * @return bool|string|array
   */
  public function getAttributeRawValue($entityId, $attribute, $store) {
    if (!$entityId || empty($attribute))
      return false;

    if (!is_array($attribute))
      $attribute = array($attribute);

    $attributesData = array();
    $staticAttributes = array();
    $typedAttributes = array();
    $staticTable = null;

    $adapter = $this->_getReadAdapter();

    foreach ($attribute as $_attribute) {
      $_attribute = $this->getAttribute($_attribute);

      if (!$_attribute)
        continue;

      $backend = $_attribute->getBackend();

      $attrTable = $backend->getTable();
      $isStatic = $backend->isStatic();

      $attributeCode = $_attribute->getAttributeCode();

      if ($isStatic) {
        $staticAttributes[] = $attributeCode;
        $staticTable = $attrTable;
      } else
        //That structure needed to avoid farther sql joins
        //for getting attribute's code by id
        $typedAttributes[$attrTable][$_attribute->getId()] = $attributeCode;
    }

    //Collecting static attributes

    if ($staticAttributes) {
      $select = $adapter
                  ->select()
                  ->from($staticTable, $staticAttributes)
                  ->where($this->getEntityIdField() . ' = :entity_id');

      $attributesData = $adapter
                          ->fetchRow($select, array('entity_id' => $entityId));
    }

    if ($store instanceof Mage_Core_Model_Store)
      $store = $store->getId();

    $store = (int) $store;

    //Collecting typed attributes, performing separate SQL query
    //for each attribute type table

    if ($typedAttributes) {
      foreach ($typedAttributes as $table => $_attributes) {
        $select = $adapter->select();

        $select
          ->from(array('default_value' => $table), array('attribute_id'))
          ->where('default_value.attribute_id IN (?)', array_keys($_attributes))
          ->where('default_value.entity_type_id = :entity_type_id')
          ->where('default_value.entity_id = :entity_id');

        $bind = array(
          'entity_type_id' => $this->getTypeId(),
          'entity_id' => $entityId
        );

        if ($store != $this->getDefaultStoreId()) {
          $select->where('(default_value.entity_id = :entity_id'
                         . ' OR '
                         . 'store_value.store_id = :store_id)');

          $valueExpr = $adapter->getCheckSql('store_value.value IS NULL',
                                             'default_value.value',
                                             'store_value.value');

          $joinCondition = array(
            $adapter->quoteInto('store_value.attribute_id IN (?)',
                                array_keys($_attributes)),
            'store_value.entity_type_id = :entity_type_id',
            'store_value.entity_id = :entity_id',
            'store_value.store_id = :store_id',
          );

          $select->joinLeft(
            array('store_value' => $table),
            implode(' AND ', $joinCondition),
            array('attr_value' => $valueExpr)
          );

          $bind['store_id'] = $store;
        } else
          $select
            ->where('default_value.store_id = ?', 0)
            ->columns(array('attr_value' => 'value'), 'default_value');

        $result = $adapter->fetchPairs($select, $bind);

        foreach ($result as $attrId => $value) {
          $attrCode = $typedAttributes[$table][$attrId];
          $attributesData[$attrCode] = $value;
        }
      }
    }

    if (sizeof($attributesData) == 1) {
      $_data = each($attributesData);
      $attributesData = $_data[1];
    }

    return $attributesData ? $attributesData : false;
  }
}
