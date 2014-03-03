<?php

class MVentory_Tm_Block_Html_Footer extends Mage_Page_Block_Html_Footer {
  public function getCopyright() {
    return parent::getCopyright()
      . '<a id="mv-powered-by" href="//mventory.com">Powered by mVentory</a>';
  }
}

?>
