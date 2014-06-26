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
 * Dataflow API
 *
 * @package MVentory/API
 */
class MVentory_API_Model_Dataflow_Api extends Mage_Api_Model_Resource_Abstract {

  const PROFILE_ERR_INVALID = 0;
  const PROFILE_ERR_FAILED = 1;
  const PROFILE_ERR_EMAIL_ERROR = 2;
  const PROFILE_ERR_REPORT_TOO_BIG = 3;
  const PROFILE_ERR_SUCCESS = 4;

  private function getCurrentTimestamp()
  {
    list($usec, $sec) = explode(" ", microtime());
    return  $sec . ($usec * 1000000);
  }

  private function getProfileErrorMessage($errorConstant, $profile, $user, $additionalInfo = null, $exception = null)
  {
    $profileName = null;
    $profileID = 0;
    if (!is_int($profile))
    {
      $profileName = substr($profile['name'], 1);
      $profileID = $profile->getID();
    }
    else
    {
      $profileID = $profile;
    }

    $userEmail = $user->getEmail();
    $userID = $user->getID();

    $logMessage = "";

    if ($errorConstant == self::PROFILE_ERR_SUCCESS)
    {
      $logMessage = "Profile execution finished (success), ";
    }
    else
    {
      $logMessage = "Profile execution finished (failure), ";
    }

    $logMessage .= "userID = " . $userID . ", ";
    $logMessage .= "profileID = " . $profileID . ", ";

    if ($additionalInfo != null)
    {
      $logMessage .= "additionalInfo = " . $additionalInfo . ", ";
    }

    if ($exception != null && $exception->getMessage() != null)
    {
      $logMessage .= "exception->getMessage() = " . $exception->getMessage() . ", ";
    }

    Mage::log($logMessage);

    switch($errorConstant)
    {
    case self::PROFILE_ERR_INVALID:
      return "Invalid profile. Refresh the list.";
    case self::PROFILE_ERR_FAILED:
      return "Report \"" . $profileName . "\" failed. Contact support.";
    case self::PROFILE_ERR_EMAIL_ERROR:
      return "Unable to send email with the report. Contact support.";
    case self::PROFILE_ERR_REPORT_TOO_BIG:
      return "Report is too big. Contact support.";
    case self::PROFILE_ERR_SUCCESS:
      return "Report \"" . $profileName . "\" was sent to " . $userEmail;
    }
  }

  public function executeProfile($id)
  {
    $session = $this->_getSession();
    $user = $session->getUser();
    $userEmail = $user->getEmail();

    $logMessage = "Executing a profile, ";
    $logMessage .= "userID = " . $user->getID() . ", ";
    $logMessage .= "profileID = " . $id . ", ";
    Mage::log($logMessage);

    $profile = Mage::getModel("dataflow/profile")->load($id);

    if (is_null($profile->getId()))
    {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_INVALID, $id, $user, "No such profile in the database");
    }

    if (strpos($profile['name'], '_') !== 0)
    {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_INVALID, $id, $user, "Profile name doesn't start with underscore");
    }

    $profileName = substr($profile['name'], 1);

    $xml = '<convert version="1.0"><profile name="default">' . $profile->getActionsXml()
      . '</profile></convert>';

    $convertProfile = null;
    try {
      $convertProfile = Mage::getModel('core/convert')
       ->importXml($xml)
       ->getProfile('default');
    }
    catch (Exception $e) {
    	return $this->getProfileErrorMessage(self::PROFILE_ERR_FAILED, $profile, $user, "Unable to convert the profile.", $e);
    }

    $actions = $convertProfile->getActions();
    $actionsCount = count($actions);

    if ($actionsCount == 0)
    {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_FAILED, $profile, $user, "No actions found in the profile");
    }

    $lastAction = $actions[$actionsCount-1];
    $lastActionParams = $lastAction->getParams();
    $lastContainer = $lastAction->getContainer();

    if (strcmp(get_class($lastContainer), "Mage_Dataflow_Model_Convert_Adapter_Io") != 0)
    {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_FAILED, $profile, $user, "Last action is not an IO action");
    }

    if (strcmp($lastActionParams['method'], "save") != 0)
    {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_FAILED, $profile, $user, "Last action is an IO action but does not save data into a file.");
    }

    $currentStoreID = Mage::helper('mventory')->getCurrentStoreId();

    foreach($actions as $action)
    {
      $container = $action->getContainer();

      if ($container->getVar("store", $currentStoreID) != $currentStoreID)
      {
        $container->setVar("store", $currentStoreID);
      }
    }

    $outputFileName = $profile['name'] . "_" . $this->getCurrentTimestamp();
    $outputFileExtension = ".csv";
    $outputZippedFileExtension = ".zip";

    $outputDirName = "var/export";

    $outputFilePath = $outputDirName . "/" . $outputFileName . $outputFileExtension;
    $outputZippedPath = $outputDirName . "/" . $outputFileName . $outputZippedFileExtension;

    $lastContainer->setVar("filename", $outputFileName . $outputFileExtension);
    $lastContainer->setVar("path", $outputDirName);

    try {
      $batch = Mage::getSingleton('dataflow/batch')
        ->setProfileId($profile->getId())
        ->setStoreId($profile->getStoreId())
        ->save();
      $profile->setBatchId($batch->getId());
        	
      $convertProfile->setDataflowProfile($profile->getData());
      $convertProfile->run();
    }
    catch (Exception $e) {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_FAILED, $profile, $user, "Unable to run profile", $e);
    }

    $profile->setExceptions($convertProfile->getExceptions());

    $filter = new Zend_Filter_Compress(array(
      'adapter' => 'Zend_Filter_Compress_Zip',
      'options' => array(
      'archive' => $outputZippedPath
    ),
    ));

    $compressed = null;
    try {
      $compressed = $filter->filter($outputFilePath);
    }
    catch (Exception $e) {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_FAILED, $profile, $user, "Unable to zip the profile", $e);
    }

    if (!$compressed)
    {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_FAILED, $profile, $user, "Unable to zip the profile (no exceptions thrown)");
    }

    if (filesize($outputZippedPath) > 7 * 1024 * 1024)
    {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_REPORT_TOO_BIG, $profile, $user, "Report too big");
    }

    $at = new Zend_Mime_Part(file_get_contents($outputZippedPath));
    $at->type = Zend_Mime::TYPE_OCTETSTREAM;
    $at->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
    $at->encoding    = Zend_Mime::ENCODING_BASE64;
    $at->filename    = "report.zip";

    $mail = Mage::getModel("core/email_template");

    $mail->setSenderName(Mage::getConfig()->getNode('default/trans_email/ident_general/name'));
    $mail->setSenderEmail(Mage::getConfig()->getNode('default/trans_email/ident_general/email'));
    $mail->setTemplateSubject($profileName);

    $mail->getMail()->addAttachment($at);

    try {
      if ($mail->send($userEmail) == false)
      {
      	throw new Exception("email sending error");
      }
    }
    catch (Exception $e) {
      return $this->getProfileErrorMessage(self::PROFILE_ERR_EMAIL_ERROR, $profile, $user, "Unable to send email", $e);
    }

    return $this->getProfileErrorMessage(self::PROFILE_ERR_SUCCESS, $profile, $user);
  }

  public function getProfilesList ()
  {
    $result = array();

    $collection = Mage::getResourceModel('dataflow/profile_collection');
    $collection->load();

    foreach($collection as $item)
    {
      if (strpos($item['name'], '_') === 0)
      {
        $resultItem = array();
        $resultItem['profile_id'] = $item['profile_id'];
        $resultItem['name'] = substr($item['name'], 1);

        $result[] = $resultItem;
      }
    }

    return $result;
  }
}
