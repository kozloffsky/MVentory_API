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
 */

/**
 * TradeMe config model
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_Model_Config
{
  const SANDBOX = 'trademe/settings/sandbox';
  const CRON_INTERVAL = 'trademe/settings/cron';
  const MAPPING_STORE = 'trademe/settings/mapping_store';
  const ENABLE_LISTING = 'trademe/settings/enable_listing';
  const LIST_AS_NEW = 'trademe/settings/list_as_new';

  const TITLE_MAX_LENGTH = 50;
  const DESCRIPTION_MAX_LENGTH = 2048;

  //TradeMe shipping types
  //const SHIPPING_UNKNOWN = 0;
  //const SHIPPING_NONE = 0;
  const SHIPPING_UNDECIDED = 1;
  //const SHIPPING_PICKUP = 2;
  const SHIPPING_FREE = 3;
  const SHIPPING_CUSTOM = 4;

  //Pickup options
  //const PICKUP_NONE = 0; //None
  const PICKUP_ALLOW = 1;  //Buyer can pickup
  const PICKUP_DEMAND = 2; //Buyer must pickup
  const PICKUP_FORBID = 3; //No pickups

  const CACHE_TYPE = 'trademe';
  const CACHE_TAG = 'TRADEME';
}
