<?php

class MVentory_Tm_Model_Tm {
  const SANDBOX_PATH = 'mventory_tm/settings/sandbox';

  //Pickup options
  //const PICKUP_NONE = 0; //None
  const PICKUP_ALLOW = 1;  //Buyer can pickup
  const PICKUP_DEMAND = 2; //Buyer must pickup
  const PICKUP_FORBID = 3; //No pickups
}
