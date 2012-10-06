<?php

/**
 * TM controller
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_Adminhtml_TmController
  extends Mage_Adminhtml_Controller_Action {

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Return table with TM categories
   *
   * @return null
   */
  public function categoriesAction () {
    $categoryId = (int) $this->getRequest()->getParam('category_id');

    if (!$categoryId) { 
      $productId = (int) $this->getRequest()->getParam('product_id');

      //Get category ID from the product if product ID was sent
      //instead category ID 
      if ($productId) {
        $product = Mage::getModel('catalog/product')->load($productId);

        $categories = $product->getCategoryIds();

        if (count($categories))
          $categoryId = $categories[0];
      }
    }

    $category = $categoryId
                  ? Mage::getModel('catalog/category')->load($categoryId)
                    : null;

    $body = $this
              ->getLayout()
              ->createBlock('mventory_tm/categories')
              //Set category in loaded block for futher using
              ->setCategory($category)
              ->toHtml();

    $this->getResponse()->setBody($body);
  }
}
