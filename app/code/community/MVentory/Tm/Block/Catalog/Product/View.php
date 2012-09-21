<?php
class MVentory_Tm_Block_Catalog_Product_View extends Mage_Catalog_Block_Product_View
{
    public function getAddToCartUrl($product, $additional = array())
    {
        if ($product->getMventoryTmId()) {
            return 'http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $product->getMventoryTmId();
        }
        
        if ($this->hasCustomAddToCartUrl()) {
            return $this->getCustomAddToCartUrl();
        }

        if ($this->getRequest()->getParam('wishlist_next')){
            $additional['wishlist_next'] = 1;
        }

        $addUrlKey = Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED;
        $addUrlValue = Mage::getUrl('*/*/*', array('_use_rewrite' => true, '_current' => false));
        $additional[$addUrlKey] = Mage::helper('core')->urlEncode($addUrlValue);

        return $this->helper('checkout/cart')->getAddUrl($product, $additional);
    }
}
