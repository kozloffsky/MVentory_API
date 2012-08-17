<?php

class MVentory_Tm_Block_Adminhtml_Catalog_Product_Edit_Tab_Tm extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $host = Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe';
        
        $product = Mage::registry('current_product');
        
        $form = new Varien_Data_Form();
        $this->setForm($form);	  
	
        $fieldset = $form->addFieldset('tm', array('legend' => Mage::helper('catalog')->__('mVentory'), 'class' => 'fieldset-wide'));
        
        if ($product->getMventoryTmId()) {
            $fieldset->addField('id', 'link', array(
                'href' => 'http://www.' . $host . '.co.nz/Browse/Listing.aspx?id=' . $product->getMventoryTmId(),
                'value' => $product->getMventoryTmId(),
                'label' => 'Listing id'
            ));
            
            $fieldset->addField('link', 'link', array(
                'href' => 'http://www.' . $host . '.co.nz/Browse/Listing.aspx?id=' . $product->getMventoryTmId(),
                'value' => 'http://www.' . $host . '.co.nz/Browse/Listing.aspx?id=' . $product->getMventoryTmId(),
                'label' => 'Listing url'
            ));
        }
         
        $fieldset->addField('product', 'link', array(
            'href' => Mage::getBaseUrl() . $product->getUrlPath(),
            'value' => Mage::getBaseUrl() . $product->getUrlPath(),
            'label' => 'Product url'
        ));
            
        if ($product->getMventoryTmId()) {
            $fieldset->addField('remove', 'link', array(
                'href' => $this->getUrl('mventory_tm/adminhtml_index/remove/id/' . $product->getEntityId()),
                'value' => 'Remove'
            ));
        } else {
            $fieldset->addField('submit', 'link', array(
                'href' => $this->getUrl('mventory_tm/adminhtml_index/submit/id/' . $product->getEntityId()),
                'value' => 'Submit'
            ));
        }
        
        $fieldset->addField('check', 'link', array(
            'href' => $this->getUrl('mventory_tm/adminhtml_index/check/id/' . $product->getEntityId()),
            'value' => 'Check status'
        ));

        $categories = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('mventory_tm_category')
                ->addFieldToFilter('entity_id', array('in' => $product->getCategoryIds()));

        $categoryId = null;

        foreach ($categories as $category) {
            if ($category->getMventoryTmCategory()) {
                $categoryId = $category->getMventoryTmCategory();
                break;
            }
        }


        $fieldset->addType('mventory_category','MVentory_Tm_Block_Form_Element_Category');

        $fieldset->addField('mventory_category', 'mventory_category', array(
            'label'         => 'My Custom Element Label',
            'name'          => 'mventory_category',
            'required'      => false,
            'value'     => $categoryId,
            'bold'      =>  true,
            'label_style'   =>  'font-weight: bold;color:red;',
        ));
    }
}