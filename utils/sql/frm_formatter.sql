-- This script disables all but one attribute sets for a single store by inserting ~ as the label in frm_ attributes

set @attribute = 'frm_book'; -- name of the formatting attribute to enable
set @store = 'welovebooks'; -- the store code for the store in question
set @store_id = (select store_id from core_store where code = @store); -- get store id for the store code

-- delete all labels from all frm_ attributes for the store in question
delete from l using eav_attribute_label as l, eav_attribute as a 
	where l.attribute_id = a.attribute_id 
		and a.attribute_code like "frm_%" 
		and l.store_id = @store_id;

-- re-insert ~ into all frm_ attributes for this store, except for the one that should remain enabled
insert into eav_attribute_label (attribute_id, store_id, value) 
	select a.attribute_id, @store_id, '~' from eav_attribute as a 
		where a.attribute_code like "frm_%" 
			and a.attribute_code != @attribute;
