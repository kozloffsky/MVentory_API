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
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

$this->startSetup();

$name = 'mv_shipping_';
$entityTypeId = $this->getEntityTypeId('catalog_product');

$this->addAttribute(
  $entityTypeId,
  $name,
  array(
    //Fields from Mage_Eav_Model_Entity_Setup
    'type' => 'int',
    'input' => 'select',
    'label' => 'Shipping',
    'source' => 'eav/entity_attribute_source_table',
    'required' => false,
    'user_defined' => true,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,

    //Fields from Mage_Catalog_Model_Resource_Setup
    'is_configurable' => false,

    'option' => array(
      'values' => array(
        'Small Envelope',
        'Large Envelope',
        'Parcel',
        'Courier',
        'Freight'
      )
    )
  )
);

$setId = $this->getDefaultAttributeSetId($entityTypeId);
$groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

$this->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);

$this->endSetup();
