<?php
/**
 * Rewrite admin dashboard Grids block to add new tab Stock Info
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Block_Adminhtml_Dashboard_Grids extends Mage_Adminhtml_Block_Dashboard_Grids
{

    /**
     * Add tab with stock info
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        // load tab statically
        $this->addTab('stock_info', array(
            'label'     => $this->__('Stock Info'),
            'content'   => $this->getLayout()->createBlock('mventory_tm/adminhtml_dashboard_tab_stock')->toHtml(),
            'active'    => false
        ));

        return $this;
    }
}
