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
 * Image helper
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Helper_Image extends MVentory_API_Helper_Product {

  private $_supportedTypes = array(
    IMAGETYPE_GIF => 'gif',
    IMAGETYPE_JPEG => 'jpeg',
    IMAGETYPE_PNG => 'png'
  );

  /**
   * Return list of distinct images of products
   *
   * @param array $products List of products
   *
   * @return array|null
   */
  public function getUniques ($products) {
    $backend = new Varien_Object();
    $backend->setData('attribute', $this->getMediaGalleryAttr());

    foreach ($products as $product)
      foreach ($this->getImages($product, $backend, false) as $image)
        $images[$image['file']] = $image;

    return isset($images) ? $images : null;
  }

  /**
   * Fixies orientation of the image using EXIF info
   *
   * @param strinf $file Path to image file
   * @return boolean|null Returns true if success
   */
  public function fixOrientation ($file) {
    if (!function_exists('exif_read_data'))
      return;

    if (($exif = exif_read_data($file)) === false)
      return;

    if (!isset($exif['FileType']))
      return;

    $type = $exif['FileType'];

    if (!array_key_exists($type, $this->_supportedTypes))
      return;

    if (isset($exif['Orientation']))
      $orientation = $exif['Orientation'];
    elseif (isset($exif['IFD0']['Orientation']))
      $orientation = $exif['IFD0']['Orientation'];
    else
      return;

    switch($orientation) {
      case 3: $angle = 180; break;
      case 6: $angle = -90; break;
      case 8: $angle = 90; break;
      default: return;
    }

    $typeName = $this->_supportedTypes[$type];

    $load = 'imagecreatefrom' . $typeName;
    $save = 'image' . $typeName;

    $save(imagerotate($load($file), $angle, 0), $file, 100);

    return true;
  }
}
