<?php

class MVentory_Tm_Model_Dataflow_Api extends Mage_Catalog_Model_Api_Resource {

  public function getProfilesList () {
  	
  	$result = array();
  	
    $collectionStandard = Mage::getResourceModel('dataflow/profile_collection')
      ->addFieldToFilter('entity_type', array('notnull'=>''));
            
    $collectionAdvanced = Mage::getResourceModel('dataflow/profile_collection')
      ->addFieldToFilter('entity_type', array('null'=>''));
            
    $collectionStandard->load();

    foreach($collectionStandard as $item)
    {
      if (strpos($item['name'], '_') === 0)
      {
        $resultItem = array();
        $resultItem['profile_id'] = $item['profile_id'];
        $resultItem['name'] = substr($item['name'], 1); 
		
        $result[] = $resultItem;
      }
    }
    
    $collectionAdvanced->load();
    
    foreach($collectionAdvanced as $item)
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
