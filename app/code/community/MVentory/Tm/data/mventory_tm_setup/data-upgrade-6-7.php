<?php

$entityTypeId = $this->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY);

$this->updateAttribute($entityTypeId, 'tm_relist', 'frontend_input', 'select');
$this->updateAttribute($entityTypeId, 'tm_relist', 'is_visible', true);
$this->updateAttribute($entityTypeId,
                       'tm_relist',
                       'source_model',
                       'eav/entity_attribute_source_boolean');
