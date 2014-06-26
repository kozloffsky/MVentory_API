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

$role = Mage::getModel('api/roles');
$resources = array();

foreach ($role->getResourcesList2D() as $resource)
  if (strpos($resource, 'mventory') === 0)
    $resources[] = $resource;

$role
  ->setName('mVentory')
  ->setPid(false)
  ->setRoleType('G')
  ->save();

Mage::getModel('api/rules')
  ->setRoleId($role->getId())
  ->setResources($resources)
  ->saveRel();

$this->endSetup();