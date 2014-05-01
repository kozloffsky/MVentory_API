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
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 */

/**
 * TradeMe сategories controller
 *
 * @package MVentory/TradeMe
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_TradeMe_CategoriesController
  extends Mage_Adminhtml_Controller_Action
{

  protected function _construct() {
    $this->setUsedModuleName('MVentory_TradeMe');
  }

  /**
   * Return table with TradeMe categories
   *
   * @return null
   */
  public function indexAction () {
    $request = $this->getRequest();

    if (!is_array($ids = $request->getParam('selected_categories')))
      $ids = array();

    if (!$type = $request->getParam('type'))
      $type = MVentory_TradeMe_Block_Categories::TYPE_CHECKBOX;

    $body = $this
      ->getLayout()
      ->createBlock('trademe/categories')
      ->setTemplate('trademe/categories.phtml')
      //Set selected categories
      ->setSelectedCategories($ids)
      ->setInputType($type)
      ->toHtml();

    $this
      ->getResponse()
      ->setBody($body);
  }
}
