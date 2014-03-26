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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * Image controller for the app
 *
 * @package MVentory/TM
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
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

    $path = $media->getMediaPath($fileName);

    if (!file_exists($path))
      Mage::helper('mventory_tm/s3')
        ->download($path);

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

      $path = $image->getNewFile();
    }

    $this
      ->getResponse()
      ->setHeader('Pragma', '', true)
      ->setHeader('Expires', '', true)
      ->setHeader('Content-Type', 'image/' . $type , true)
      ->setHeader('Content-Length', filesize($path), true)
      ->setBody(file_get_contents($path));
  }
}
