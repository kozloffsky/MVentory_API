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
 * Rewrite admin dashboard Grids block to add new tab Stock Info
 *
 * @package MVentory/API
 */
class MVentory_API_Block_Adminhtml_Dashboard_Grids extends Mage_Adminhtml_Block_Dashboard_Grids
{

  /**
   * Add tab with stock info
   */
  protected function _prepareLayout()
  {
    parent::_prepareLayout();

    // load tab statically
    $this->addTab('stock_info', array(
      'label' => $this->__('Stock Info'),
      'content' => $this->getLayout()->createBlock('mventory/adminhtml_dashboard_tab_stock')->toHtml(),
      'active' => false
    ));

    return $this;
  }
}
