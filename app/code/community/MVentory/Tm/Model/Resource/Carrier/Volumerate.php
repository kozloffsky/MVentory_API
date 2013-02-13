<?php

/**
 * Resource class for the volume based shipping carrier model
 *
 * @category   MVentor
 * @package    MVentory_Tm
 */

class MVentory_Tm_Model_Resource_Carrier_Volumerate
  extends Mage_Shipping_Model_Resource_Carrier_Tablerate {

  protected $_helper = null;

  /**
   * Define main table and id field name
   *
   * @return void
   */
  protected function _construct () {
    $this->_init('mventory_tm/carrier_volumerate', 'pk');
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

    $this->_helper = Mage::helper('mventory_tm');

    $this->_importWebsiteId = (int) Mage::app()
                                      ->getWebsite($scopeId)
                                      ->getId();

    $this->_importUniqueHash = array();
    $this->_importErrors = array();
    $this->_importedRows = 0;

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
    if (count($row) < 5) {
      $msg = 'Invalid Table Rates format in the row #%s';
      $this->_importErrors[] = $this->__($msg, $rowNumber);

      return false;
    }

    //Strip whitespace from the beginning and end of each row
    foreach ($row as $k => $v)
      $row[$k] = trim($v);

    //Validate country
    if (isset($this->_importIso2Countries[$row[0]]))
      $countryId = $this->_importIso2Countries[$row[0]];
    elseif (isset($this->_importIso3Countries[$row[0]]))
      $countryId = $this->_importIso3Countries[$row[0]];
    elseif ($row[0] == '*' || $row[0] == '')
      $countryId = '0';
    else {
      $msg = 'Invalid Country "%s" in the row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[0], $rowNumber);

      return false;
    }

    //Validate region
    if ($countryId != '0'
        && isset($this->_importRegions[$countryId][$row[1]]))
      $regionId = $this->_importRegions[$countryId][$row[1]];
    elseif ($row[1] == '*' || $row[1] == '')
      $regionId = 0;
    else {
      $msg = 'Invalid Country "%s" in the row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[1], $rowNumber);

      return false;
    }

    //Detect zip code
    if ($row[2] == '*' || $row[2] == '')
      $zipCode = '*';
    else
      $zipCode = $row[2];

    //Validate weight value
    $weight = $this->_parseDecimalValue($row[3]);

    //Validate weight value
    $volume = $this->_parseDecimalValue($row[4]);

    if ($weight === false && $volume === false) {
      $msg = 'Invalid Weight ("%s") and Volume ("%s") values in the row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[3], $row[4], $rowNumber);

      return false;
    }

    //Validate price
    $price = $this->_parseDecimalValue($row[5]);

    if ($price === false) {
      $msg = 'Invalid Shipping Price "%s" in the Row #%s.';
      $this->_importErrors[] = $this->__($msg, $row[4], $rowNumber);

      return false;
    }

    //Protect from duplicate
    $hash = sprintf('%s-%d-%s-%s-%s',
                    $countryId,
                    $regionId,
                    $zipCode,
                    $weight,
                    $volume);

    if (isset($this->_importUniqueHash[$hash])) {
      $msg = 'Duplicate Row #%s (Country "%s", Region/State "%s", Zip "%s", '
             . 'Weight "%s" and Volume "%s").';

      $this->_importErrors[] = $this->__($msg,
                                         $rowNumber,
                                         $row[0],
                                         $row[1],
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
      $price
    );
  }

  protected function __() {
    return call_user_func_array(array($this->_helper, '__'), func_get_args());
  }
}
