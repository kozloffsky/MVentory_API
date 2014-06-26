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
