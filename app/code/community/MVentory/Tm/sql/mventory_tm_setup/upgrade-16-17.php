<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');

$name = 'mv_stock_journal';

$attributeData = array(
  //Global settings
  'type' => 'text',
  'input' => 'textarea',
  'label' => 'Stock Journal',
  'required' => false,

  //Catalogue setting
  'is_configurable' => false
);

$this->addAttribute($entityTypeId, $name, $attributeData);
