<?php
class MVentory_Tm_QrController extends Mage_Core_Controller_Front_Action
{

  public function scanAction()
  {
    $sku = $this->getRequest()->getParam('sku');

    if ($sku) {
      $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);

      if ($product && $product->getId()) {
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: '. Mage::getBaseUrl() . $product->getUrlPath());
        exit;
      }
    }

    $this->_forward('noRoute');
  }


  public function labelsAction()
  {
    $size = Mage::getStoreConfig('mventory_tm/qr/size');
    $rows = Mage::getStoreConfig('mventory_tm/qr/rows');
    $cols = Mage::getStoreConfig('mventory_tm/qr/columns');
    $pages = Mage::getStoreConfig('mventory_tm/qr/pages');
    $css = Mage::getStoreConfig('mventory_tm/qr/css');
    $copies = Mage::getStoreConfig('mventory_tm/qr/copies');
    $baseUrl = Mage::getStoreConfig('mventory_tm/qr/base_url') ? Mage::getStoreConfig('mventory_tm/qr/base_url') : Mage::getBaseUrl();

    if (preg_match("#https?://#", $baseUrl) === 0) {
      $baseUrl = 'http://' . $baseUrl;
    }

    if (strrpos($baseUrl, '/') + 1 != strlen($baseUrl)) {
      $baseUrl .= '/';
    }

    $html = '';

    if ($css) {
      $html .= '<style type="text/css">' . $css . '</style>';
    }

    if ($copies == 1) {
      for ($p = 1; $p <= $pages; $p++) {
        if ($p > 1) {
          $html .= '<p class="pagebreak" style="page-break-before: always">';
        }

        $html .= '<table class="table">';

        for ($r = 1; $r <= $rows; $r++) {
          $html .= '<tr class="row">';

          for ($c = 1; $c <= $cols; $c++) {
            $sku = $this->_generateSku();

            $html .= '<td class="cell">';
            $html .= '<img class="image" src="https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . '&chl=' . urlencode($baseUrl . 'sku/' . $sku) . '" />';
            $html .= '<div class="sku">' . $sku . '</div>';
            $html .= '</td>';
          }

          $html .= '</tr>';
        }

        $html .= '</table>';
      }
    } else {
      $counter = 1;
      $sku = $this->_generateSku();

      for ($c = 1; $c <= $copies; $c++) {
        for ($p = 1; $p <= $pages; $p++) {
          if ($p > 1) {
            $html .= '<p class="pagebreak" style="page-break-before: always">';
          }

          $html .= '<table class="table">';

          for ($r = 1; $r <= $rows; $r++) {
            $html .= '<tr class="row">';

            for ($c = 1; $c <= $cols; $c++) {
              if ($counter > $copies) {
                $sku = $this->_generateSku();
                $counter = 1;
              }

              $html .= '<td class="cell">';
              $html .= '<img class="image" src="https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . '&chl=' . urlencode($baseUrl . 'sku/' . $sku) . '" />';
              $html .= '<div class="sku">' . $sku . '</div>';
              $html .= '</td>';

              $counter++;
            }

            $html .= '</tr>';
          }

          $html .= '</table>';
        }
      }
    }

    echo $html;
  }


  private function _generateSku()
  {
    list($a, $b) = explode(' ', microtime());
    return 'M' . $b . str_replace('0.', '', substr($a, 0, strlen($a) - 2));
  }
}
