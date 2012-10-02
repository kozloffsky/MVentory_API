<?php

class MVentory_Tm_ImageController
  extends Mage_Core_Controller_Front_Action {

  public function getAction () {
    $request = $this->getRequest();

    if (!$request->has('file'))
        return;

    $fileName = $request->get('file');

    $dispretionPath
                  = Mage_Core_Model_File_Uploader::getDispretionPath($fileName);

    $fileName = $dispretionPath . DS . $fileName;

    $media = Mage::getModel('catalog/product_media_config');

    if (!file_exists($media->getMediaPath($fileName)))
      return;

    $tokens = explode('.', $fileName);

    $type = $tokens[count($tokens) - 1];

    if ($type == 'jpg')
      $type = 'jpeg';

    $width = $request->has('width') && is_numeric($request->get('width'))
               ? (int) $request->get('width') : null;

    $height = $request->has('height') && is_numeric($request->get('height'))
                  ? (int) $request->get('height') : null;

    if ($width || $height) {
      $image = Mage::getModel('catalog/product_image');

      $image
        ->setBaseFile($fileName)
        ->setKeepFrame(false)
        ->setWidth($width)
        ->setHeight($height)
        ->resize()
        ->saveFile();

      $fileName = $image->getNewFile();
    }

    $this
      ->getResponse()
      ->setHeader('Pragma', '', true)
      ->setHeader('Expires', '', true)
      ->setHeader('Content-Type', 'image/' . $type , true)
      ->setHeader('Content-Length', filesize($fileName), true)
      ->setBody(file_get_contents($fileName));
  }
}
