<?php

class MVentory_Tm_Model_Resource_Entity_Attribute
  extends Mage_Eav_Model_Resource_Entity_Attribute {

  /**
    * Retrieve attribute id by attribute label and store id
    *
    * @param string $label
    * @param int $storeId
    * @return int
    */
  public function getIdByLabel ($label, $storeId) {
    $adapter = $this->_getReadAdapter();

    $bind = array(':attribute_label' => $label);

    $select = $adapter
                ->select()
                ->from(
                    array('a' => $this->getTable('eav/attribute')),
                    array('a.attribute_id')
                  );

    if ($storeId) {
      $select
        ->joinLeft(
            array('l' => $this->getTable('eav/attribute_label')),
            'a.attribute_id = l.attribute_id AND l.store_id = :store_id',
            array('store_label'
                       => $adapter->getIfNullSql('l.value', 'a.frontend_label'))
          )
        ->having('store_label = :attribute_label');

      $bind[':store_id'] = $storeId;
    } else
      $select
        ->where('a.frontend_label = :attribute_label');

    return $adapter->fetchOne($select, $bind);
  }

  /**
    * Retrieve default value by attribute label and store id
    *
    * @param string $label
    * @param int $storeId
    * @return string
    */
  public function getDefaultValueByLabel ($label, $storeId) {
    $adapter = $this->_getReadAdapter();

    $bind = array(':attribute_label' => $label);

    $select = $adapter
                ->select()
                ->from(
                    array('a' => $this->getTable('eav/attribute')),
                    array('a.default_value')
                  );

    if ($storeId) {
      $select
        ->joinLeft(
            array('l' => $this->getTable('eav/attribute_label')),
            'a.attribute_id = l.attribute_id AND l.store_id = :store_id',
            array('store_label'
                       => $adapter->getIfNullSql('l.value', 'a.frontend_label'))
          )
        ->having('store_label = :attribute_label');

      $bind[':store_id'] = $storeId;
    } else
      $select
        ->where('a.frontend_label = :attribute_label');

    return $adapter->fetchOne($select, $bind);
  }
}

?>
