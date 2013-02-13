<?php

/**
 * Carriers controller
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_CarriersController
  extends Mage_Adminhtml_Controller_Action {

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Export shipping rates in csv format
   */
  public function exportAction () {
    $websiteId = Mage::app()
                   ->getWebsite($this->getRequest()->getParam('website'))
                   ->getId();

    $content = $this
                 ->getLayout()
                 ->createBlock('mventory_tm/carrier_volumerate_grid')
                 ->setWebsiteId($websiteId)
                 ->getCsvFile();

    $this->_prepareDownloadResponse('shippingrates.csv', $content);
  }
}
