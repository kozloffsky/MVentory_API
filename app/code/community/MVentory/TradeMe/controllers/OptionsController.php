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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Controller for exporting options of TradeMe accounts
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_OptionsController
  extends Mage_Adminhtml_Controller_Action
{
  protected function _construct() {
    $this->setUsedModuleName('MVentory_TradeMe');
  }

  /**
   * Export options in csv format
   */
  public function exportAction () {
    $website = Mage::app()
                 ->getWebsite($this->getRequest()->getParam('website'));

    $content = $this
                 ->getLayout()
                 ->createBlock('trademe/options')
                 ->setWebsiteId($website->getId())
                 ->getCsvFile();

    $this->_prepareDownloadResponse(
      'trademe-options-' . $website->getCode() . '.csv',
      $content
    );
  }
}
