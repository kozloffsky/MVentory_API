<?php

/**
 * Product description block
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class MVentory_Tm_Block_Product_View_Attributes
 extends Mage_Catalog_Block_Product_View_Attributes {
    protected $_product = null;

  /**
   * $excludeAttr is optional array of attribute codes to
   * exclude them from additional data array
   *
   * @param array $excludeAttr
   * @param bool $html Enable or disable links to category filters
   * @return array
   */
  public function getAdditionalData (array $exclude = array(), $html = true) {
    $data = $html
              ? $this->getAdditionalDataHtml($exclude)
                : parent::getAdditionalData($exclude);

    $data[] = array(
      'label' => 'Product ID',
      'value' => $this->getProduct()->getId(),
      'code' => 'id'
    );

    //Round value of weight attribute
    if (isset($data['weight']) && is_numeric($data['weight']['value']))
      $data['weight']['value'] = round($data['weight']['value'], 2);

    return $data;
  }

  /**
   * Outputs options of attributes with select or multiselect input type as
   * links to filters in category layered navaigation
   *
   * $excludeAttr is optional array of attribute codes to
   * exclude them from additional data array
   *
   * @param array $excludeAttr
   * @return array
   */
  public function getAdditionalDataHtml (array $exclude = array()) {
    $helper = Mage::helper('mventory_tm/product');

    $data = array();

    $product = $this->getProduct();
    $category = $helper->getCategory($product);
    $attributes = $product->getAttributes();

    $queryParams = $category
                     ->getUrlInstance()
                     ->getQueryParams();

    $title = $helper->__('View more of this type');

    foreach ($attributes as $attribute) {
      $code = $attribute->getAttributeCode();

      if (!$product->hasData($code))
        continue;

      if (!$attribute->getIsVisibleOnFront() || in_array($code, $exclude))
        continue;

      $input = $attribute->getFrontendInput();

      if ($attribute->getIsHtmlAllowedOnFront()
          && ($input == 'select' || $input == 'multiselect')) {

        $source = $attribute->getSource();

        $links = array();

        $_values = explode(',', $product->getData($code));

        foreach ($_values as $_value) {
          $params = $queryParams;
          $params[$code] = $_value;

          $category->unsetData('url');

          $category
            ->getUrlInstance()
            ->unsetData('query_params')
            ->setQueryParams($params);

          $links[] = '<a href="' . $category->getUrl() . '"'
                      . 'title="' . $title . '">'
                    . $source->getOptionText($_value)
                    . '</a>';
        }

        $value = implode(', ', $links);
      } else {
        $value = $attribute
                   ->getFrontend()
                   ->getValue($product);

        if ((string) $value == '')
          $value = Mage::helper('catalog')->__('No');
        elseif ($input == 'price' && is_string($value))
          $value = Mage::app()->getStore()->convertPrice($value, true);
      }

      if (is_string($value) && strlen($value))
        $data[$code] = array(
          'label' => $attribute->getStoreLabel(),
          'value' => $value,
          'code'  => $code
        );
    }

    $category
      ->unsetData('url')
      ->getUrlInstance()
      ->unsetData('query_params')
      ->setQueryParams($queryParams);

    return $data;
  }
}
