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
 */

/**
 * Different constants and values
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Config
{
  //Config paths
  const _FETCH_LIMIT = 'mventory/api/products-number-to-fetch';
  const _TAX_CLASS = 'mventory/api/tax_class';
  const _ITEM_LIFETIME = 'mventory/api/cart-item-lifetime';
  const _LINK_LIFETIME = 'mventory/api/app_profile_link_lifetime';
  const _LOST_CATEGORY = 'mventory/api/lost_category';
  const _API_VISIBILITY = 'mventory/api/product_visiblity';
  const _ROOT_WEBSITE = 'mventory/api/root_website';
  const _DEFAULT_ROLE = 'mventory/api/default_role';
  const _APPLY_RULES = 'mventory/api/apply_rules';
  const _QR_ROWS = 'mventory/qr/rows';
  const _QR_COLUMNS = 'mventory/qr/columns';
  const _QR_SIZE = 'mventory/qr/size';
  const _QR_PAGES = 'mventory/qr/pages';
  const _QR_CSS = 'mventory/qr/css';
  const _QR_URL = 'mventory/qr/base_url';
  const _QR_COPIES = 'mventory/qr/copies';
}
