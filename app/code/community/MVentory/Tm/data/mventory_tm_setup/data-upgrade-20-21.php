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

$q = '
update
  ' . $this->getTable('mventory/additional_skus') . ' as s,
  ' . $this->getTable('catalog/product_website') . ' as p
set
  s.website_id = p.website_id
where
  p.product_id=s.product_id
';

$this->startSetup();

$this
  ->getConnection()
  ->query($q);

$this->endSetup();