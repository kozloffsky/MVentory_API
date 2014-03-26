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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

$entityTypeId = $this->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY);

$this->updateAttribute($entityTypeId, 'tm_relist', 'frontend_input', 'select');
$this->updateAttribute($entityTypeId, 'tm_relist', 'is_visible', true);
$this->updateAttribute($entityTypeId,
                       'tm_relist',
                       'source_model',
                       'eav/entity_attribute_source_boolean');
