<?php

/**
 * S3 helper
 *
 * @category   MVentory
 * @package    MVentory_Tm
 * @author     MVentory <???@mventory.com>
 */
class MVentory_Tm_Helper_S3 extends MVentory_Tm_Helper_Data {

  private $_prefix = null;

  /**
   * Downloads image from S3 by its absolute path on FS.
   *
   * @param string $path Absolute path to image
   * @param int|string|Mage_Core_Model_Website Website for settings
   *
   * @return bool
   */
  public function download ($path, $website = null) {
    if (!file_exists(dirname($path)))
      mkdir(dirname($path), 0777, true);

    $result = $this
                ->_getS3($website)
                ->getObjectStream($this->_getObject($path), $path);

    return $result ? true : false;
  }

  /**
   * Uploads image to S3. Uses absolute path to image for S3 object name
   *
   * @param string $from Absolute path to source image
   * @param string $path Absolute path to create S3 object name
   * @param int|string|Mage_Core_Model_Website Website for settings
   *
   * @return bool
   */
  public function upload ($from, $path, $website = null) {
    //Prepare meta data for uploading. All uploaded images are public
    $meta = array(Zend_Service_Amazon_S3::S3_ACL_HEADER
                    => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ);

    return $this
             ->_getS3($website)
             ->putFileStream($from, $this->_getObject($path), $meta);
  }

  /**
   * Returns configured S3 object.
   * Uses passed website or wesbite which current product is asssigned to
   * or current website to get wesbite's prefix on S3
   *
   * @param int|string|Mage_Core_Model_Website Website for settings
   *
   * @return Zend_Service_Amazon_S3
   */
  protected function _getS3 ($website = null) {
    if ($website === null)
      $website = $this->getWebsite();
    else
      $website = Mage::app()->getWebsite($website)

    $accessKeyPath = MVentory_Tm_Model_Observer::XML_PATH_CDN_ACCESS_KEY;
    $secretKeyPath = MVentory_Tm_Model_Observer::XML_PATH_CDN_SECRET_KEY;
    $bucketPath = MVentory_Tm_Model_Observer::XML_PATH_CDN_BUCKET;
    $prefixPath = MVentory_Tm_Model_Observer::XML_PATH_CDN_PREFIX;

    //Get settings for S3
    $accessKey = $this->getConfig($accessKeyPath, $website);
    $secretKey = $this->getConfig($secretKeyPath, $website);
    $bucket = $this->getConfig($bucketPath, $website);
    $prefix = $this->getConfig($prefixPath, $website);

    $this->_prefix = $bucket . '/' . $prefix . '/full';

    return new Zend_Service_Amazon_S3($accessKey, $secretKey);
  }

  /**
   * Build name of S3 object from the absolute path of image
   *
   * @param string $path Absolute path to image
   *
   * $return string Name of S3 object
   */
  protected function _getObject ($path) {
    $baseDir = Mage::getSingleton('catalog/product_media_config')
                 ->getBaseMediaPath();

    return $this->_prefix . str_replace($baseDir, '', $path);
  }
}
