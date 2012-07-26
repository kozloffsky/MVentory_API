<?php
class MVentory_Tm_Block_Catalog_Product_List extends Mage_Catalog_Block_Product_List
{
    public function getAddToCartUrl($product, $additional = array())
    {
        $_product = Mage::getModel('catalog/product')->load($product->getId());
        
        if ($_product->getMventoryTmId()) {
            return 'http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $_product->getMventoryTmId();
        } elseif ($product->getTypeInstance(true)->hasRequiredOptions($product)) {
            if (!isset($additional['_escape'])) {
                $additional['_escape'] = true;
            }
            if (!isset($additional['_query'])) {
                $additional['_query'] = array();
            }
            $additional['_query']['options'] = 'cart';

            return $this->getProductUrl($product, $additional);
        }
        return $this->helper('checkout/cart')->getAddUrl($product, $additional);
    }
}
