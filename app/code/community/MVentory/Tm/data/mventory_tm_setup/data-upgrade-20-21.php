<?php

$q = '
update
  ' . $this->getTable('mventory_tm/additional_skus') . ' as s,
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