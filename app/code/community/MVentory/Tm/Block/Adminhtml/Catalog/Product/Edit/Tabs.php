<?php

class MVentory_Tm_Block_Adminhtml_Catalog_Product_Edit_Tabs extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs
{
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        
        $this->addTab('tm', array(
            'label'     => Mage::helper('catalog')->__('mVentory'),
            'content'   => $this->getLayout()->createBlock('mventory_tm/adminhtml_catalog_product_edit_tab_tm')->toHtml()
        ));
    }
}