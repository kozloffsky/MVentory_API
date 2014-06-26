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
 * QR codes controller
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_QrController extends Mage_Core_Controller_Front_Action
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


  protected function _generateLabels ()
  {
    $size = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_SIZE);
    $rows = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_ROWS);
    $cols = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_COLUMNS);
    $pages = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_PAGES);
    $css = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_CSS);
    $copies = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_COPIES);
    $baseUrl = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_URL)
                 ? Mage::getStoreConfig(MVentory_API_Model_Config::_QR_URL)
                   : Mage::getBaseUrl();

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

  public function generateAction () {
    $request = $this->getRequest();

    if ($request->getParam('labels')) {
      $this->_generateLabels();

      return;
    }

    $links = (bool) $request->getParam('links');
    $images = (bool) $request->getParam('images');
    $codes = (bool) $request->getParam('codes');

    if (!($links || $images || $codes))
      return;

    $content = $this->_generateData($links, $images, $codes);

    $this->_prepareDownloadResponse('images-link-and-codes.csv', $content);
  }

  protected function _generateData ($links = false,
                                    $images = false,
                                    $codes = false) {

    $size = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_SIZE);
    $pages = (int) Mage::getStoreConfig(MVentory_API_Model_Config::_QR_PAGES);
    $rows = (int) Mage::getStoreConfig(MVentory_API_Model_Config::_QR_ROWS);
    $columns = (int) Mage::getStoreConfig(
      MVentory_API_Model_Config::_QR_COLUMNS
    );
    $baseUrl = Mage::getStoreConfig(MVentory_API_Model_Config::_QR_URL)
                 ? Mage::getStoreConfig(MVentory_API_Model_Config::_QR_URL)
                   : Mage::getBaseUrl();

    if (preg_match("#https?://#", $baseUrl) === 0)
      $baseUrl = 'http://' . $baseUrl;

    if (strrpos($baseUrl, '/') + 1 != strlen($baseUrl))
      $baseUrl .= '/';

    $baseUrl .= 'sku/';

    $n = $pages * $rows * $columns;

    $imageUrl = 'https://chart.googleapis.com/chart?cht=qr&chs='
                . $size
                . 'x'
                . $size
                . '&chl=';

    $commaAfterImage = ($links || $codes) ? ',' : '';
    $commaAfterLink = $codes ? ',' : '';

    $content = '';

    for ($i = 0; $i < $n; $i++) {
      $code = $this->_generateSku();
      $url = $baseUrl . $code;

      if ($images)
        $content .= $imageUrl . urlencode($url) . $commaAfterImage;

      if ($links)
        $content .= $url . $commaAfterLink;

      if ($codes)
        $content .= $code;

      $content .= "\r\n";
    }

    return $content;
  }

  private function _generateSku()
  {
    list($a, $b) = explode(' ', microtime());
    return 'M' . $b . str_replace('0.', '', substr($a, 0, strlen($a) - 2));
  }
}
