<?php

class MVentory_Tm_Catalog_ProductController
  extends Mage_Adminhtml_Controller_Action {

  public function massNameRebuildAction () {
    $request = $this->getRequest();

    $productIds = (array) $request->getParam('product');
    $storeId = (int) $request->getParam('store', 0);

    try {
      $numberOfRenamed = Mage::getSingleton('mventory_tm/product_action')
                           ->rebuildNames($productIds, $storeId);

      $m = '%d of %d record(s) have been updated.';

      $this
        ->_getSession()
        ->addSuccess($this->__($m, $numberOfRenamed, count($productIds)));
    }
    catch (Mage_Core_Model_Exception $e) {
      $this
        ->_getSession()
        ->addError($e->getMessage());
    } catch (Mage_Core_Exception $e) {
      $this
        ->_getSession()
        ->addError($e->getMessage());
    } catch (Exception $e) {
      $m = $this->__('An error occurred while updating the product(s) status.');

      $this
        ->_getSession()
        ->addException($e, $m);
    }

    $this->_redirect('adminhtml/*', array('store'=> $storeId));
  }
}

?>
