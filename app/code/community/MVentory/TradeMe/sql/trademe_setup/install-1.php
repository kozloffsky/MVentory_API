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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */


$tableName = 'trademe/matching_rules';

$attrs = array(
  'tm_listing_id' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'Previous listing ID',
    'required' => false,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'visible' => false,
    'is_configurable' => false
  ),

  'tm_current_listing_id' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'hidden',
    'label' => 'Listing ID',
    'required' => false,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'used_in_product_listing' => true,
    'is_configurable' => false
  ),

  'tm_account_id' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'input' => 'select',
    'label' => 'Previous account ID',
    'required' => false,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'visible' => false,
    'is_configurable' => false
  ),

  'tm_current_account_id' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'input' => 'hidden',
    'label' => 'Account ID',
    'source' => 'trademe/attribute_source_accounts',
    'required' => false,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'is_configurable' => false
  ),

  'tm_relist' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'select',
    'label' => 'Allow to list',
    'source' => 'eav/entity_attribute_source_boolean',
    'required' => false,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'is_configurable' => false
  ),

  'tm_avoid_withdrawal' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'select',
    'label' => 'Avoid withdrawal',
    'source' => 'trademe/attribute_source_boolean',
    'required' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'is_configurable' => false
  ),

  'tm_shipping_type' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'select',
    'label' => 'Use free shipping',
    'source' => 'trademe/attribute_source_freeshipping',
    'required' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'visible' => true,
    'is_configurable' => false
  ),

  'tm_allow_buy_now' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'select',
    'label' => 'Allow Buy Now',
    'source' => 'trademe/attribute_source_boolean',
    'required' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'visible' => true,
    'is_configurable' => false
  ),

  'tm_add_fees' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'select',
    'label' => 'Add Fees',
    'source' => 'trademe/attribute_source_boolean',
    'required' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'visible' => true,
    'is_configurable' => false
  ),

  'tm_pickup' => array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'select',
    'label' => 'Pickup',
    'source' => 'trademe/attribute_source_pickup',
    'required' => false,
    'default' => -1,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'visible' => true,
    'is_configurable' => false
  )
);

$this->startSetup();

$this->addAttributes($attrs);

$connection = $this->getConnection();

$table = $connection
  ->newTable($this->getTable($tableName))
  ->addColumn(
      'id',
      Varien_Db_Ddl_Table::TYPE_INTEGER,
      null,
      array(
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
      ),
      'Primary key'
    )
  ->addColumn(
      'attribute_set_id',
      Varien_Db_Ddl_Table::TYPE_SMALLINT,
      null,
      array(
        'unsigned' => true,
        'nullable' => false,
        'default' => '0'
      ),
      'Attribute Set ID'
    )
  ->addColumn(
      'rules',
      Varien_Db_Ddl_Table::TYPE_TEXT,
      null,
      array(
        'nullable' => true,
      ),
      'Matching rule data in JSON format'
    )
  ->addIndex(
      $this->getIdxName($tableName, array('attribute_set_id')),
      array('attribute_set_id')
    )
  ->addForeignKey(
      $this->getFkName(
        $tableName,
        'attribute_set_id',
        'eav/attribute_set',
        'attribute_set_id'
      ),
      'attribute_set_id',
      $this->getTable('eav/attribute_set'),
      'attribute_set_id',
      Varien_Db_Ddl_Table::ACTION_CASCADE,
      Varien_Db_Ddl_Table::ACTION_CASCADE
    )
  ->setComment('TradeMe matching rules');

$connection->createTable($table);

$this->endSetup();
