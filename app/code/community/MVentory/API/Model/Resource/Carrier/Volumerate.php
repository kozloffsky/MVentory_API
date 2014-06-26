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
 * Resource model for the volume based shipping carrier model
 *
 * @package MVentory/API
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_API_Model_Resource_Carrier_Volumerate
  extends Mage_Shipping_Model_Resource_Carrier_Tablerate {

  protected $_helper = null;
  protected $_destionationConditions = array(
    array(
      'dest_country_id = :country_id',
      'dest_region_id = :region_id',
      'dest_zip = :postcode'
    ),
    array(
      'dest_country_id = :country_id',
      'dest_region_id = :region_id',
      'dest_zip = \'\''
    ),

    // Handle asterix in dest_zip field
    array(
      'dest_country_id = :country_id',
      'dest_region_id = :region_id',
      'dest_zip = \'*\''
    ),
    array(
      'dest_country_id = :country_id',
      'dest_region_id = 0',
      'dest_zip = \'*\''
    ),
    array(
      'dest_country_id = \'0\'',
      'dest_region_id = :region_id',
      'dest_zip = \'*\''
    ),
    array(
      'dest_country_id = \'0\'',
      'dest_region_id = 0',
      'dest_zip = \'*\''
    ),

    array(
      'dest_country_id = :country_id',
      'dest_region_id = 0',
      'dest_zip = \'\''
    ),
    array(
      'dest_country_id = :country_id',
      'dest_region_id = 0',
      'dest_zip = :postcode'
    ),
    array(
      'dest_country_id = :country_id',
      'dest_region_id = 0',
      'dest_zip = \'*\''
    )
  );

  /**
   * Define main table and id field name
   *
   * @return void
   */
  protected function _construct () {
    $this->_init('mventory/carrier_volumerate', 'pk');
  }

  /**
   * Return table rate array or false by rate request
   *
   * @param Mage_Shipping_Model_Rate_Request $request
   * @return array|boolean
   */
  public function getRate (Mage_Shipping_Model_Rate_Request $request) {
    $adapter = $this->_getReadAdapter();

    $bind = array(
      ':website_id' => (int) $request->getWebsiteId(),
      ':shipping_type' => (int) $request->getShippingType(),
      ':country_id' => $request->getDestCountryId(),
      ':region_id' => (int) $request->getDestRegionId(),
      ':postcode' => $request->getDestPostcode()
    );

    $order = array(
      'dest_country_id DESC',
      'dest_region_id DESC',
      'dest_zip DESC'
    );

    $where = 'website_id = :website_id AND shipping_type = :shipping_type';

    $select = $adapter
                ->select()
                ->from($this->getMainTable())
                ->where($where)
                ->order($order)
                ->limit(1);

    //Render destination condition
    foreach ($this->_destionationConditions as $conditions)
      $orWhere[] = implode(' AND ', $conditions);

    $select->where('(' . implode(') OR (', $orWhere) . ')');

    $conditionNames = $request->getConditionName();

    // Render condition by condition name
    if (is_array($conditionNames)) {
      $orWhere = array();
      $i = 0;

      foreach ($conditionNames as $conditionName) {
        $name  = sprintf(':condition_name_%d', $i);
        $value = sprintf(':condition_value_%d', $i);

        $orWhere[] = '(condition_name = ' . $name
                     . ' AND '
                     . ' condition_value <= ' . $value . ')';

        $bind[$name] = $conditionName;
        $bind[$value] = $request->getData($conditionName);

        $i++;
      }

      if ($orWhere)
        $select->where(implode(' OR ', $orWhere));
    } else {
      $bind[':condition_name']  = $conditionNames;
      $bind[':condition_value'] = $request->getData($conditionNames);

      $select
        ->where('condition_name = :condition_name')
        ->where('condition_value <= :condition_value');
    }

    $result = $adapter->fetchRow($select, $bind);

    //Normalize destination zip code
    if ($result && $result['dest_zip'] == '*')
      $result['dest_zip'] = '';

    return $result;
  }

  /**
   * Upload table rate file and import data from it
   *
   * @param Varien_Object $object
   * @throws Mage_Core_Exception
   *
   * @return Mage_Shipping_Model_Resource_Carrier_Tablerate
   */
  public function uploadAndImport (Varien_Object $object) {
    $shippingTypes =
      Mage::getModel('mventory/system_config_source_allowedshippingtypes')
        ->toArray();

    if (!$shippingTypes)
      Mage::throwException($this->__('There\'re no available shipping types'));

    $scopeId = $object->getScopeId();
    $groupId = $object->getGroupId();
    $field = $object->getField();

    if (empty($_FILES['groups']
                     ['tmp_name']
                     [$groupId]
                     ['fields']
                     [$field]
                     ['value']))
      return $this;

    $file = $_FILES['groups']['tmp_name'][$groupId]['fields'][$field]['value'];

    $this->_helper = Mage::helper('mventory');

    $this->_importWebsiteId = (int) Mage::app()
                                      ->getWebsite($scopeId)
                                      ->getId();

    $this->_importUniqueHash = array();
    $this->_importErrors = array();
    $this->_importedRows = 0;

    foreach ($shippingTypes as $id => $label)
      $this->_shippingTypeMap[strtolower($label)] = $id;

    unset($shippingTypes);

    $info = pathinfo($file);

    $io = new Varien_Io_File();

    $io->open(array('path' => $info['dirname']));
    $io->streamOpen($info['basename'], 'r');

    //Check and skip headers
    $headers = $io->streamReadCsv();

    if ($headers === false || count($headers) < 5) {
      $io->streamClose();

      Mage::throwException($this
                             ->__('Invalid Volume/Weight Rates File Format'));
    }

    $adapter = $this->_getWriteAdapter();
    $adapter->beginTransaction();

    try {
      $rowNumber  = 1;
      $data = array();

      $this->_loadDirectoryCountries();
      $this->_loadDirectoryRegions();

      //Delete old data by website
      $condition = array(
        'website_id = ?' => $this->_importWebsiteId
      );

      $adapter->delete($this->getMainTable(), $condition);

      while (false !== ($line = $io->streamReadCsv())) {
        $rowNumber ++;

        if (empty($line))
          continue;

        $row = $this->_getImportRow($line, $rowNumber);

        if ($row !== false)
          $data[] = $row;

        if (count($data) == 5000) {
          $this->_saveImportData($importdata);

          $data = array();
        }
      }

      $this->_saveImportData($data);

      $io->streamClose();
    } catch (Mage_Core_Exception $e) {
      $adapter->rollback();
      $io->streamClose();

      Mage::throwException($e->getMessage());
    } catch (Exception $e) {
      $adapter->rollback();
      $io->streamClose();

      Mage::logException($e);

      $msg = 'An error occurred while import volume/weight rates.';
      Mage::throwException($this->__($msg));
    }

    $adapter->commit();

    if ($this->_importErrors) {
      $msg = 'File has not been imported. See the following list of errors: %s';
      $msg = $this->__($msg, implode(" \n", $this->_importErrors));

      Mage::throwException($msg);
    }

    return $this;
  }

  /**
   * Validate row for import and return volume/weight rate array or false
   * Error will be add to _importErrors array
   *
   * @param array $row
   * @param int $rowNumber
   *
   * @return array|false
   */
  protected function _getImportRow ($row, $rowNumber = 0) {

    //Validate row
    if (count($row) < 8) {
      $msg = 'Invalid Table Rates format in the row #%s';
      $this->_importErrors[] = $this->__($msg, $rowNumber);

      return false;
    }

    //Strip whitespace from the beginning and end of each row
    foreach ($row as $k => $v)
      $row[$k] = trim($v);

    $shippingType = strtolower($row[0]);

    if (!isset($this->_shippingTypeMap[$shippingType])) {
      $msg = 'Invalid shipping type ("%s") in the row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[0], $rowNumber);

      return false;
    }

    $shippingType = $this->_shippingTypeMap[$shippingType];

    //Validate country
    if (isset($this->_importIso2Countries[$row[1]]))
      $countryId = $this->_importIso2Countries[$row[1]];
    elseif (isset($this->_importIso3Countries[$row[1]]))
      $countryId = $this->_importIso3Countries[$row[1]];
    elseif ($row[1] == '*' || $row[1] == '')
      $countryId = '0';
    else {
      $msg = 'Invalid Country "%s" in the row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[1], $rowNumber);

      return false;
    }

    //Validate region
    if ($countryId != '0'
        && isset($this->_importRegions[$countryId][$row[2]]))
      $regionId = $this->_importRegions[$countryId][$row[2]];
    elseif ($row[2] == '*' || $row[2] == '')
      $regionId = 0;
    else {
      $msg = 'Invalid Country "%s" in the row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[2], $rowNumber);

      return false;
    }

    //Detect zip code
    if ($row[3] == '*' || $row[3] == '')
      $zipCode = '*';
    else
      $zipCode = $row[3];

    //Validate weight value
    $weight = $this->_parseDecimalValue($row[4]);

    //Validate weight value
    $volume = $this->_parseDecimalValue($row[5]);

    if ($weight === false && $volume === false) {
      $msg = 'Invalid Weight ("%s") and Volume ("%s") values in the row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[4], $row[5], $rowNumber);

      return false;
    }

    //Validate price
    $price = $this->_parseDecimalValue($row[6]);

    if ($price === false) {
      $msg = 'Invalid Shipping Price "%s" in the Row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[6], $rowNumber);

      return false;
    }

    //Validate minimal rate
    $minRate = $this->_parseDecimalValue($row[7]);

    if ($minRate === false) {
      $msg = 'Invalid Minimal Charge "%s" in the Row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[7], $rowNumber);

      return false;
    }

    //Protect from duplicate
    $hash = sprintf('%d-%s-%d-%s-%s-%s',
                    $shippingType,
                    $countryId,
                    $regionId,
                    $zipCode,
                    $weight,
                    $volume);

    if (isset($this->_importUniqueHash[$hash])) {
      $msg = 'Duplicate Row #%s (Shipping Type "%s", Country "%s", '
             .'Region/State "%s", Zip "%s", Weight "%s" and Volume "%s").';

      $this->_importErrors[] = $this->__($msg,
                                         $rowNumber,
                                         $row[0],
                                         $row[1],
                                         $row[2],
                                         $zipCode,
                                         $weight,
                                         $volume);
      return false;
    }

    $this->_importUniqueHash[$hash] = true;

    $conditionName = 'volume';
    $conditionValue = $volume;

    if ($weight !== false) {
      $conditionName = 'weight';
      $conditionValue = $weight;
    }

    return array(
      //website_id
      $this->_importWebsiteId,

      //shipping_type
      $shippingType,

      //dest_country_id
      $countryId,

      //dest_region_id,
      $regionId,

      //dest_zip
      $zipCode,

      //condition_name
      $conditionName,

      //condition_value
      $conditionValue,

      //price
      $price,

      //min_rate
      $minRate
    );
  }

  /**
   * Save import data batch
   *
   * @param array $data
   * @return MVentory_API_Model_Resource_Carrier_Volumerate
   */
  protected function _saveImportData (array $data) {
    if (empty($data))
      return $this;

    $columns = array(
      'website_id',
      'shipping_type',
      'dest_country_id',
      'dest_region_id',
      'dest_zip',
      'condition_name',
      'condition_value',
      'price',
      'min_rate'
    );

    $this
      ->_getWriteAdapter()
      ->insertArray($this->getMainTable(), $columns, $data);

    $this->_importedRows += count($data);

    return $this;
  }

  protected function __() {
    return call_user_func_array(array($this->_helper, '__'), func_get_args());
  }
}
