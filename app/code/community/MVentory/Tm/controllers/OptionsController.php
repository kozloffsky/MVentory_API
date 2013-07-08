<?php

/**
 * Options controller
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_OptionsController
  extends Mage_Adminhtml_Controller_Action {

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Export TM options in csv format
   */
  public function exportAction () {
    $website = Mage::app()
                 ->getWebsite($this->getRequest()->getParam('website'));

    $content = $this
                 ->getLayout()
                 ->createBlock('mventory_tm/options')
                 ->setWebsiteId($website->getId())
                 ->getCsvFile();

    $this->_prepareDownloadResponse(
      'tm-options-' . $website->getCode() . '.csv',
      $content
    );
  }
}
