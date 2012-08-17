<?php
class MVentory_Tm_Block_Adminhtml_Catalog_Category_Edit_Form extends Mage_Adminhtml_Block_Catalog_Category_Edit_Form
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('mventory/category/edit/form.phtml');
    }
}
