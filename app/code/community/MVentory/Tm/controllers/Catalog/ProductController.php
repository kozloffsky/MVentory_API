<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/API
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Controller for product massactions
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Catalog_ProductController
  extends Mage_Adminhtml_Controller_Action {

  public function massNameRebuildAction () {
    $request = $this->getRequest();

    $productIds = (array) $request->getParam('product');
    $storeId = (int) $request->getParam('store', 0);

    try {
      $numberOfRenamed = Mage::getSingleton('mventory/product_action')
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

  /**
   *  Populate product attributes when selected "Populate product attributes" action
   */
  public function massAttributesPopulateAction () {
    $request = $this->getRequest();

    // Get selected products ids
    $productIds = (array) $request->getParam('product');

    $storeId = (int) $request->getParam('store', 0);

    try {
      // Populate product attributes
      $numberOfPopulate = Mage::getSingleton('mventory/product_action')
                           ->populateAttributes($productIds, $storeId);

      $m = '%d of %d record(s) have been updated.';

      $this
        ->_getSession()
        ->addSuccess($this->__($m, $numberOfPopulate, count($productIds)));
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
      $m = $this->__('An error occurred while updating the product(s).');

      $this
        ->_getSession()
        ->addException($e, $m);
    }

    $this->_redirect('adminhtml/*', array('store'=> $storeId));
  }

  public function massCategoryMatchAction () {
    $request = $this->getRequest();

    $productIds = (array) $request->getParam('product');
    $storeId = (int) $request->getParam('store', 0);

    try {
      $number = Mage::getSingleton('mventory/product_action')
                  ->matchCategories($productIds);

      $m = '%d of %d record(s) have been updated.';

      $this
        ->_getSession()
        ->addSuccess($this->__($m, $number, count($productIds)));
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
