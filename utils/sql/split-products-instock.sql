# This SQL script finds in-stock products which have same name
# but are not assigned to same configurable product because of different hashes

# PARAMETERS

# Set website to search in
SET @`website` = 'website_code';

SET @`website_id` = (
  SELECT
    `website_id`
  FROM
    `core_website`
  WHERE
    `code` = @`website`
  LIMIT 1
);

SET @`name_attr_id` = (
  SELECT
    `attribute_id`
  FROM
    `eav_attribute`
  WHERE
    `entity_type_id` = 4
    AND `attribute_code` = 'name'
  LIMIT 1
);

SET @`hash_attr_id` = (
  SELECT
    `attribute_id`
  FROM
    `eav_attribute`
  WHERE
    `entity_type_id` = 4
    AND `attribute_code` = 'mv_attributes_hash'
  LIMIT 1
);

CREATE TEMPORARY TABLE IF NOT EXISTS nash (
  `id` INT(10) UNSIGNED,
  `name` VARCHAR(255),
  `hash` VARCHAR(255)
);

TRUNCATE TABLE nash;

INSERT INTO
  `nash`
SELECT
  `names`.`entity_id` AS `id`,
  `names`.`value` AS `name`,
  `hashes`.`value` AS `hash`
FROM
  `catalog_product_entity_varchar` AS `names`,
  `catalog_product_entity_varchar` AS `hashes`,
  `catalog_product_website` AS `website`,
  `cataloginventory_stock_status` AS `status`
WHERE
  `names`.`entity_id` = `hashes`.`entity_id`
  AND `names`.`attribute_id` = @`name_attr_id`
  AND `hashes`.`attribute_id` = @`hash_attr_id`
  AND `website`.`website_id` = @`website_id`
  AND `names`.`entity_id` = `website`.`product_id`
  AND `status`.`website_id` = @`website_id`
  AND `status`.`product_id` = `names`.`entity_id`
  AND `status`.`stock_status` = 1
GROUP BY
  `hash`
;

SELECT
  `name`,
  count(*)
FROM
  `nash`
GROUP BY
  `name`
HAVING
  count(*) > 1
;

DROP TABLE `nash`;