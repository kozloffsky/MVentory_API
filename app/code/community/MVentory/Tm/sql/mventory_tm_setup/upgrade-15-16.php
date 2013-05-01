<?php

$entityTypeId = $this->getEntityTypeId('catalog_product');

$name = 'mv_condition_';

$attributeData = array(
  //Global settings
  'type' => 'int',
  'input' => 'select',
  'source' => 'eav/entity_attribute_source_table',
  'label' => 'Condition',
  'required' => false,
  'user_defined' => true,

  //Catalogue setting
  'filterable' => 1,
  'visible_on_front' => true,
  'is_html_allowed_on_front' => true
);

$this->addAttribute($entityTypeId, $name, $attributeData);
