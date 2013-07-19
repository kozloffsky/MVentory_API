<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');

$name = 'mv_attributes_hash';

$attributeData = array(
  //Global settings
  'type' => 'varchar',
  'input' => 'hidden',
  'label' => 'Attributes hash',
  'required' => false,

  //Catalogue settings
  'visible' => false,
  'is_configurable' => false
);

$this->addAttribute($entityTypeId, $name, $attributeData);
