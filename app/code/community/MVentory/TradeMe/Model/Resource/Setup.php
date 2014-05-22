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

/**
 * Resource serup model
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Resource_Setup
  extends Mage_Catalog_Model_Resource_Setup
{
  public function addAttributes ($attrs) {
    $entityTypeId = $this->getEntityTypeId('catalog_product');
    $setId = $this->getDefaultAttributeSetId($entityTypeId);
    $groupId = $this->getDefaultAttributeGroupId($entityTypeId, $setId);

    foreach ($attrs as $name => $attr)
      $this
        ->addAttribute($entityTypeId, $name, $attr)
        ->addAttributeToGroup($entityTypeId, $setId, $groupId, $name);
  }
}
