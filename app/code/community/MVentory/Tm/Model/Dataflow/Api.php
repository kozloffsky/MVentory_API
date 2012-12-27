<?php

class MVentory_Tm_Model_Dataflow_Api extends Mage_Catalog_Model_Api_Resource {

  public function getProfilesList () {
  	
  	$result = array();
  	
    $collection = Mage::getResourceModel('dataflow/profile_collection');
    $collection->load();

    foreach($collection as $item)
    {
      if (strpos($item['name'], '_') === 0)
      {
        $resultItem = array();
        $resultItem['profile_id'] = $item['profile_id'];
        $resultItem['name'] = substr($item['name'], 1); 
		
        $result[] = $resultItem;
      }
    }
    
    return $result;
  }
  
}
