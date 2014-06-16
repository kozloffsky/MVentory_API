-- This script inserts ~ into some or all frm_ attributes to hide them from one or more stores.

set @attribute_set = 'audio';
set @store = 'toolbox';
set @store_id = (select store_id from core_store where code = @store);
delete from l using eav_attribute_label as l, eav_attribute as a where l.attribute_id = a.attribute_id and a.attribute_code like "frm_%" and l.store_id = @store_id;
insert into eav_attribute_label (attribute_id, store_id, value) select a.attribute_id, @store_id, '~' from eav_attribute as a where a.attribute_code like "frm_%" and a.attribute_code != CONCAT('frm_', @attribute_set);
