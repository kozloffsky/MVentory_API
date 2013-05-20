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

  /**
   * Outputs options of attributes with select or multiselect input type as
   * links to filters in category layered navaigation
   *
   * $excludeAttr is optional array of attribute codes to
   * exclude them from additional data array
   *
   * @param array $excludeAttr
   * @param bool $html Enable or disable links to category filters
   * @return array
   */
  public function getAdditionalData (array $exclude = array(), $html = true) {
    $helper = Mage::helper('mventory_tm/product');

    $data = array();

    $product = $this->getProduct();

    if ($html)
      if (!$product->getData('mv_created_date'))
        $product->setData('mv_created_date', $product->getData('created_at'));

    $category = $helper->getCategory($product);
    $attributes = $product->getAttributes();

    $queryParams = $category
                     ->getUrlInstance()
                     ->getQueryParams();

    $title = $helper->__('View more of this type');

    foreach ($attributes as $attribute) {
      $code = $attribute->getAttributeCode();

      if (!$attribute->getIsVisibleOnFront() || in_array($code, $exclude))
        continue;

      $label = trim($attribute->getStoreLabel());

      if ($label == '~')
        continue;

      $values = $product->getData($code);

      if ($values === null || $values === '' || strpos($values, '~') === 0)
        continue;

      $input = $attribute->getFrontendInput();
      $isSelect = $input == 'select' || $input == 'multiselect';

      if ($isSelect) {
        $values = explode(',', $values);

        $source = $attribute->getSource();

        foreach ($values as $value)
          $_values[$value] = $source->getOptionText($value);

        $values = $_values;

        unset($_values);
      } else
        $values = (array) $attribute
                            ->getFrontend()
                            ->getValue($product);

      foreach ($values as $i => $value) {
        $_value = strtolower(str_replace(' ', '', $value));

        if (!$_value || $_value == 'n/a' || $_value == 'n\a' || $_value == '~')
          unset($values[$i]);
      }

      if (!count($values))
        continue;

      if ($attribute->getIsHtmlAllowedOnFront() && $isSelect && $html) {
        foreach ($values as $i => &$value) {
          $params = $queryParams;
          $params[$code] = $i;

          $category
            ->unsetData('url')
            ->getUrlInstance()
            ->unsetData('query_params')
            ->setQueryParams($params);

          $value = '<a href="' . $category->getUrl() . '"'
                    . 'title="' . $title . '">'
                    . $value
                    . '</a>';
        }

        unset($value);
      }

      if ($input == 'price') {
        foreach ($values as $i => &$value)
          $value = Mage::app()->getStore()->convertPrice($value, true);

        unset($value);
      }

      $data[$code] = array(
        'label' => $label,
        'value' => implode(', ', $values),
        'code'  => $code
      );
    }

    $category
      ->unsetData('url')
      ->getUrlInstance()
      ->unsetData('query_params')
      ->setQueryParams($queryParams);

    $data[] = array(
      'label' => 'Product ID',
      'value' => $this->getProduct()->getId(),
      'code' => 'id'
    );

    //Round value of weight attribute
    if (isset($data['weight']) && is_numeric($data['weight']['value']))
      $data['weight']['value'] = round($data['weight']['value'], 2);

    if ($html) {
      if (isset($data['sku'])) {
        $url = $this->getUrl('', array('sku' => $data['sku']['value']));

        

        $qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl='
                 . urlencode(substr($url, 0, -1)); 

        $data['sku']['value']
          = '<a href="' . $qrUrl . '">' . $data['sku']['value'] . '</a>';
      }
    }

    return $data;
  }
}
