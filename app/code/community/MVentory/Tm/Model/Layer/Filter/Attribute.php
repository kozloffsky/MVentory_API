<?php

class MVentory_Tm_Model_Layer_Filter_Attribute extends Mage_Catalog_Model_Layer_Filter_Attribute
{
  private function cmp_items($item_left, $item_right)
    {
        $value_left = $item_left['label'];
        $value_right = $item_right['label'];

        if (is_numeric($value_left) && is_numeric($value_right))
        {
            if (0.0 + $value_left < 0.0 + $value_right)
                return -1;
            elseif (0.0 + $value_left == 0.0 + $value_right)
                return 0;
            else
                return 1;
        }
        elseif (is_numeric($value_left) && !is_numeric($value_right))
        {
            return -1;
        }
        elseif (!is_numeric($value_left) && is_numeric($value_right))
        {
            return 1;
        }
        else
        {
            return strcmp($value_left, $value_right);
        }
    }

  /**
   * Get data array for building attribute filter items
   *
   * @return array
   */
  protected function _getItemsData()
  {
    $data = parent::_getItemsData();
    usort($data, array($this, "cmp_items"));

    return $data;
  }
}
