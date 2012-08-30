<?php

class MVentory_Tm_Adminhtml_IndexController extends Mage_Adminhtml_Controller_Action
{
    public function submitAction()
    {
        $id = $this->_request->getParam('id');
        $categoryId = $this->_request->getParam('tm_category_id');

        $product = Mage::getModel('catalog/product')->load($id);
        $result = 'Error';
        
        $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        if ($stock->getManageStock() && $stock->getQty() == 0 && !$product->getTmListingId()) {
            Mage::getSingleton('adminhtml/session')->addError('Item is not available in inventory');
        } else {    
            if ($product->getId()) {
                $connector = Mage::getModel('mventory_tm/connector');
                $result = $connector->send($product, $categoryId);
            }
            
            if (is_int($result)) {
                $link = '<a href="http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $result . '">http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $result . '</a>';

                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('catalog')->__('Send to ') . $link);

                $product->setTmListingId($result);
                $product->save();
            } else {
                Mage::getSingleton('adminhtml/session')->addError($result);
            }
        }
        
        $this->_redirect('adminhtml/catalog_product/edit/id/' . $id);
    }
    
    
    public function removeAction()
    {
        $id = $this->_request->getParam('id');
        $product = Mage::getModel('catalog/product')->load($id);
        $result = 'Error';
        
        if ($product->getId()) {
            $connector = Mage::getModel('mventory_tm/connector');
            $result = $connector->remove($product);
        }
        
        if ($result === true) {
            $link = '<a href="http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $product->getTmListingId() . '">http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $product->getTmListingId() . '</a>';
            
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('catalog')->__('Removed from ') . $link);
            
            $product->setTmListingId(0);
            $product->save();
        } else {
            Mage::getSingleton('adminhtml/session')->addError($result);
        }
        
        $this->_redirect('adminhtml/catalog_product/edit/id/' . $id);
    }
    
    
    public function checkAction()
    {        
        $id = $this->_request->getParam('id');
        $product = Mage::getModel('catalog/product')->load($id);
        
        if ($product->getId()) {
            $connector = Mage::getModel('mventory_tm/connector');
            $result = $connector->check($product);
            
            $link = '<a href="http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $product->getTmListingId() . '">http://www.' . (Mage::getStoreConfig('mventory_tm/settings/sandbox') ? 'tmsandbox' : 'trademe') . '.co.nz/Browse/Listing.aspx?id=' . $product->getTmListingId() . '</a>';
            
            switch ($result) {
                case 1:
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('catalog')->__('Did\'t sell ') . $link);
                    
                    $product->setTmListingId(0);
                    $product->save();
                    break;
                case 2:
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('catalog')->__('Successfully sold ') . $link);
                    
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        
                    if ($stock->getManageStock() && $stock->getQty()) {
                        $stockData = $stock->getData();
                        $stockData['qty'] -= 1;
                        $product->setStockData($stockData);
                    }
                    
                    $product->setTmListingId(0);
                    $product->save();
                    break;
                case 3:
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('catalog')->__('The auction is active ') . $link);
                    break;
                default:
                    Mage::getSingleton('adminhtml/session')->addError('Listing doesn\'t exist');
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Error');
        }
        
        $this->_redirect('adminhtml/catalog_product/edit/id/' . $id);
    }
}
