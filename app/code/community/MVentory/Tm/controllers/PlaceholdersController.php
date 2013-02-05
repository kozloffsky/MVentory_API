<?php

/**
 * Placeholders controller
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */

class MVentory_Tm_PlaceholdersController
  extends Mage_Adminhtml_Controller_Action {

  const ERROR = Mage_Core_Model_Message::ERROR;
  const WARNING = Mage_Core_Model_Message::WARNING;
  const NOTICE = Mage_Core_Model_Message::NOTICE;
  const SUCCESS = Mage_Core_Model_Message::SUCCESS;

  protected function _construct() {
    $this->setUsedModuleName('MVentory_Tm');
  }

  /**
   * Upload placeholders to CDN
   *
   * @return null
   */
  public function uploadAction () {
    $website = $this
                 ->getRequest()
                 ->getParam('website');

    if (!$website)
      return $this->_back('No website parameter', self::ERROR, $website);

    $helper = Mage::helper('mventory_tm');

    $path = MVentory_Tm_Model_Observer::XML_PATH_CDN_ACCESS_KEY;
    $accessKey = $helper->getConfig($path, $website);

    $path = MVentory_Tm_Model_Observer::XML_PATH_CDN_SECRET_KEY;
    $secretKey = $helper->getConfig($path, $website);

    $path = MVentory_Tm_Model_Observer::XML_PATH_CDN_BUCKET;
    $bucket = $helper->getConfig($path, $website);

    $path = MVentory_Tm_Model_Observer::XML_PATH_CDN_PREFIX;
    $prefix = $helper->getConfig($path, $website);

    $path = MVentory_Tm_Model_Observer::XML_PATH_CDN_DIMENSIONS;
    $dimensions = $helper->getConfig($path, $website);

    if (!($accessKey && $secretKey && $bucket && $prefix))
      return $this->_back('Can\'t get CDN settings', self::ERROR, $website);

    unset($path);

    $config = Mage::getSingleton('catalog/product_media_config');

    $destSubdirs = array('image', 'small_image', 'thumbnail');

    $placeholders = array();

    $storeId = Mage::app()
                 ->getWebsite($website)
                 ->getDefaultStore()
                 ->getId();

    $appEmulation = Mage::getModel('core/app_emulation');

    $env = $appEmulation->startEnvironmentEmulation($storeId);

    foreach ($destSubdirs as $destSubdir) {
      $placeholder = Mage::getModel('catalog/product_image')
                       ->setDestinationSubdir($destSubdir)
                       ->setBaseFile(null)
                       ->getBaseFile();

      $basename = basename($placeholder);

      $result = copy($placeholder, $config->getMediaPath($basename));

      if ($result !== true)
        return $this
                 ->_back('Error on copy ' . $placeholder . ' to media folder',
                         self::ERROR,
                         $website);

      $placeholders[] = '/' . $basename;
    }

    $appEmulation->stopEnvironmentEmulation($env);

    unset($storeId);
    unset($appEmulation);

    $s3 = new Zend_Service_Amazon_S3($accessKey, $secretKey);

    $cdnPrefix = $bucket . '/' . $prefix . '/';

    $dimensions = str_replace(', ', ',', $dimensions);
    $dimensions = explode(',', $dimensions);

    $meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                    => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

    foreach ($placeholders as $fileName) {
      $cdnPath = $cdnPrefix . 'full' . $fileName;

      $file = $config->getMediaPath($fileName);

      try {
        $s3->putFile($file, $cdnPath, $meta);
      } catch (Exception $e) {
        return $this->_back($e->getMessage(), self::ERROR, $website);
      }

      if (!count($dimensions))
        continue;

      foreach ($dimensions as $dimension) {
        $newCdnPath = $cdnPrefix . $dimension . $fileName;

        $productImage = Mage::getModel('catalog/product_image');

        $destinationSubdir = '';

        foreach ($destSubdirs as $destSubdir) {
          $newFile = $productImage
                       ->setDestinationSubdir($destSubdir)
                       ->setSize($dimension)
                       ->setBaseFile($fileName)
                       ->getNewFile();

          if (file_exists($newFile)) {
            $destinationSubdir = $destSubdir;

            break;
          }
        }

        if ($destinationSubdir == '')
          try {
            $newFile = $productImage
                         ->setDestinationSubdir($destinationSubdir)
                         ->setSize($dimension)
                         ->setBaseFile($fileName)
                         ->resize()
                         ->saveFile()
                         ->getNewFile();
          } catch (Exception $e) {
            return $this->_back($e->getMessage(), self::ERROR, $website);
          }

        try {
          $s3->putFile($newFile, $newCdnPath, $meta);
        } catch (Exception $e) {
          return $this->_back($e->getMessage(), self::ERROR, $website);
        }
      }
    }

    return $this->_back('Successfully uploaded all placeholders',
                        self::SUCCESS,
                        $website);
  }

  protected function _back ($msg, $type, $website) {
    $path = 'adminhtml/system_config/edit';

    $params = array(
      'section' => 'mventory_tm',
      'website' => $website
    );

    $msg = $this->__($msg);

    switch (strtolower($type)) {
      case self::ERROR :
        $message = new Mage_Core_Model_Message_Error($msg);
        break;
      case self::WARNING :
        $message = new Mage_Core_Model_Message_Warning($msg);
        break;
      case self::SUCCESS :
        $message = new Mage_Core_Model_Message_Success($msg);
        break;
      default:
        $message = new Mage_Core_Model_Message_Notice($msg);
        break;
    }

    Mage::getSingleton('adminhtml/session')->addMessage($message);

    $this->_redirect($path, $params);
  }
}
