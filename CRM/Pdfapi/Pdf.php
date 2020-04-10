<?php
/**
 * Class to create the PDF
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 3 Oct 2018
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Pdfapi_ExtensionUtil as E;

class CRM_Pdfapi_Pdf {
  private $_apiParams = array();
  private $_templateEmailId = NULL;
  private $_emailSubject = NULL;
  private $_htmlMessageEmail = NULL;
  private $_messageTemplatesEmail = NULL;
  private $_tokensEmail = NULL;
  private $_htmlMessage = NULL;
  private $_subject = NULL;
  private $_pdfsToBeGenerated = array();
  private $_createdPdfs = array();
  private $_createdFileIds = array();
  private $_domain = NULL;
  private $_version = NULL;
  private $_toEmail = NULL;
  private $_fromEmail = NULL;
  private $_fromName  = NULL;
  private $_contactIds = array();
  private $_toContactIds = array();
  private $_processedCaseIds = array();

  public function __construct($params ) {
    $this->_apiParams = $params;

    $this->_domain  = CRM_Core_BAO_Domain::getDomain();
    $this->_version = CRM_Core_BAO_Domain::version();
    list($fromName, $fromEmail) = CRM_Core_BAO_Domain::getNameAndEmail();
    $this->_fromName = isset($this->_apiParams['from_name']) && !empty($this->_apiParams['from_name']) ? $this->_apiParams['from_name'] : $fromName;
    $this->_fromEmail = isset($this->_apiParams['from_email']) && !empty($this->_apiParams['from_email']) ? $this->_apiParams['from_email'] : $fromEmail;

    if (isset($this->_apiParams['to_email']) && !empty($this->_apiParams['to_email'])) {
      $this->_toEmail = $this->_apiParams['to_email'];
    }
    if (isset($this->_apiParams['contact_id'])) {
      $this->_toContactIds = explode(",", $this->_apiParams['contact_id']);
    }

    try {
      $messageTemplate = $this->getMessageTemplates();
    } catch (\Exception $e) {
      $messageTemplate = null;
    }

    // Optional template_email_id, if not default 0
    $this->_templateEmailId = CRM_Utils_Array::value('body_template_id', $this->_apiParams, 0);

    if ($this->_templateEmailId) {
      $this->_messageTemplatesEmail = $this->getMessageTemplatesEmail($this->_templateEmailId);
      $this->_htmlMessageEmail = $this->_messageTemplatesEmail->msg_html;
      if (isset($this->_apiParams['email_subject']) && !empty($this->_apiParams['email_subject'])) {
        $this->_emailSubject = $this->_apiParams['email_subject'];
      }
      else {
        $this->_emailSubject = $this->_messageTemplatesEmail->msg_subject;
      }
      $this->_tokensEmail = array_merge_recursive(CRM_Utils_Token::getTokens($this->_htmlMessageEmail),
        CRM_Utils_Token::getTokens($this->_emailSubject));
    } else {
      $this->_htmlMessageEmail = E::ts("CiviCRM has generated a PDF letter");
      if ($messageTemplate) {
        $this->_emailSubject = E::ts('PDF Letter from Civicrm - %1', [1 => $messageTemplate->msg_title]);
      } else {
        $this->_emailSubject = E::ts('PDF Letter from Civicrm');
      }
    }
  }

  /**
   * Method to create the email with the pdf
   *
   * @param $overrideParams
   * @throws API_Exception
   * @throws CRM_Core_Exception
   */
  public function create($overrideParams=[]) {
    foreach($overrideParams as $key => $value) {
      $this->_apiParams[$key] = $value;
    }
    $this->validateCaseId();
    $html    = array();
    if (!preg_match('/[0-9]+(,[0-9]+)*/i', $this->_apiParams['contact_id'])) {
      throw new API_Exception('Parameter contact_id must be a unique id or a list of ids separated by comma');
    }
    $this->_contactIds = explode(",", $this->_apiParams['contact_id']);
    $messageTemplate = $this->getMessageTemplates();
    $this->_subject = $messageTemplate->msg_subject;
    $htmlTemplate = $this->formatMessage($messageTemplate);
    $messageTokens = CRM_Utils_Token::getTokens($htmlTemplate);

    // get replacement text for these tokens
    $returnProperties = $this->getReturnProperties($messageTokens);
    foreach($this->_contactIds as $contactId) {
      try {
        $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contactId]);
      }
      catch (CiviCRM_API3_Exception $ex) {
        throw new API_Exception(ts('Could not find contact data with contact getsingle for contact id ') . $contactId . ' in '
          . __METHOD__ . ts(', error message from API Contact getsingle: ') .$ex->getMessage());
      }
      $this->_htmlMessage = $htmlTemplate;
      list($details) = CRM_Utils_Token::getTokenDetails(array($contactId), $returnProperties, false, false, null, $messageTokens);
      CRM_Utils_Hook::tokenValues($details, $contactId, NULL, $messageTokens);
      $contact = reset( $details );
      // add case_id if present
      if (isset($this->_apiParams['case_id']) && !empty($this->_apiParams['case_id'])) {
        $contact['case_id'] = $this->_apiParams['case_id'];
      }
      if (isset($contact['do_not_mail']) && $contact['do_not_mail'] == TRUE) {
        if(count($this->_contactIds) == 1)
          throw new API_Exception('Suppressed creating pdf letter for: '.$contact['display_name'].' because DO NOT MAIL is set');
        else
          continue;
      }
      if (isset($contact['is_deceased']) && $contact['is_deceased'] == TRUE) {
        if(count($this->_contactIds) == 1)
          throw new API_Exception('Suppressed creating pdf letter for: '.$contact['display_name'].' because contact is deceased');
        else
          continue;
      }
      if (isset($contact['on_hold']) && $contact['on_hold'] == TRUE) {
        if(count($this->_contactIds) == 1)
          throw new API_Exception('Suppressed creating pdf letter for: '.$contact['display_name'].' because contact is on hold');
        else
          continue;
      }
      // call token hook
      $hookTokens = array();
      CRM_Utils_Hook::tokens($hookTokens);
      $categories = array_keys($hookTokens);

      $this->_htmlMessage = CRM_Utils_Token::replaceDomainTokens($this->_htmlMessage, $this->_domain, TRUE, $messageTokens, TRUE);
      $this->_htmlMessage = CRM_Utils_Token::replaceContactTokens($this->_htmlMessage, $contact, FALSE, $messageTokens, FALSE, TRUE);
      $this->_htmlMessage = CRM_Utils_Token::replaceComponentTokens($this->_htmlMessage, $contact, $messageTokens, TRUE);
      if (isset($this->_apiParams['case_id']) && !empty($this->_apiParams['case_id'])) {
        $this->_htmlMessage = CRM_Utils_Token::replaceCaseTokens($this->_apiParams['case_id'], $this->_htmlMessage, $messageTokens);
      }
      $this->_htmlMessage = CRM_Utils_Token::replaceHookTokens($this->_htmlMessage, $contact , $categories, TRUE);
      CRM_Utils_Token::replaceGreetingTokens($this->_htmlMessage, NULL, $contact['contact_id']);
      if (defined('CIVICRM_MAIL_SMARTY') && CIVICRM_MAIL_SMARTY) {
        $smarty = CRM_Core_Smarty::singleton();
        // also add the contact tokens to the template
        $smarty->assign_by_ref('contact', $contact);
        $this->_htmlMessage = $smarty->fetch("string:$this->_htmlMessage");
      }

      $html[] = $this->_htmlMessage;

      //create PDF activities if required
      if (isset($this->_apiParams['pdf_activity']) && $this->_apiParams['pdf_activity'] == TRUE) {
        $this->createPdfActivities($contactId);
      }
    }

    $this->_pdfsToBeGenerated[] = [
      'title' => $messageTemplate->msg_title,
      'pdf_format_id' => $messageTemplate->pdf_format_id,
      'html' => $html,
    ];

    if (isset($this->_apiParams['case_id']) && !empty($this->_apiParams['case_id'])) {
      $this->_processedCaseIds[] = $this->_apiParams['case_id'];
    }
  }

  /**
   * Method to send the actual pdf as an email attachment
   *
   * @param $email
   * @param $contact_id
   */
  private function sendPdf($email, $contact_id=null) {
    $caseId = null;
    if ($this->_processedCaseIds && count($this->_processedCaseIds) > 0) {
      $caseId = reset($this->_processedCaseIds);
    }
    if ($contact_id) {
      $messageTokens = CRM_Utils_Token::getTokens($this->_htmlMessageEmail);

      // get replacement text for these tokens
      $returnProperties = $this->getReturnProperties($messageTokens);
      list($details) = CRM_Utils_Token::getTokenDetails([$contact_id], $returnProperties, FALSE, FALSE, NULL, $messageTokens);
      if (isset($this->_tokensEmail)) {
        CRM_Utils_Hook::tokenValues($details, $contact_id, NULL, $this->_tokensEmail);
      }
      $contact = reset($details);

      $this->_htmlMessageEmail = CRM_Utils_Token::replaceDomainTokens($this->_htmlMessageEmail, $this->_domain, TRUE, $this->_tokensEmail, TRUE);
      $this->_htmlMessageEmail = CRM_Utils_Token::replaceContactTokens($this->_htmlMessageEmail, $details, FALSE, $this->_tokensEmail, FALSE, TRUE);
      $this->_htmlMessageEmail = CRM_Utils_Token::replaceComponentTokens($this->_htmlMessageEmail, $contact, $this->_tokensEmail, TRUE);
      if ($caseId) {
        $this->_htmlMessageEmail = CRM_Utils_Token::replaceCaseTokens($caseId, $this->_htmlMessageEmail, $this->_tokensEmail);
      }
      $this->_htmlMessageEmail = CRM_Utils_Token::replaceHookTokens($this->_htmlMessageEmail, $contact, $categories, TRUE);
      CRM_Utils_Token::replaceGreetingTokens($this->_htmlMessageEmail, NULL, $contact['contact_id']);
      if (defined('CIVICRM_MAIL_SMARTY') && CIVICRM_MAIL_SMARTY) {
        $smarty = CRM_Core_Smarty::singleton();
        // also add the contact tokens to the template
        $smarty->assign_by_ref('contact', $contact);
        $this->_htmlMessageEmail = $smarty->fetch("string:$this->_htmlMessageEmail");
      }

      $this->_emailSubject = CRM_Utils_Token::replaceDomainTokens($this->_emailSubject, $this->_domain, TRUE, $this->_tokensEmail, TRUE);
      $this->_emailSubject = CRM_Utils_Token::replaceContactTokens($this->_emailSubject, $contact, FALSE, $this->_tokensEmail, FALSE, TRUE);
      $this->_emailSubject = CRM_Utils_Token::replaceComponentTokens($this->_emailSubject, $contact, $this->_tokensEmail, TRUE);
      if ($caseId) {
        $this->_emailSubject = CRM_Utils_Token::replaceCaseTokens($caseId, $this->_emailSubject, $this->_tokensEmail);
      }
      $this->_emailSubject = CRM_Utils_Token::replaceHookTokens($this->_emailSubject, $contact, $categories, TRUE);
    }

    $mailParams = array(
      'groupName' => 'PDF Letter API',
      'from' => $this->_fromName . ' <' . $this->_fromEmail . '>',
      'fromName' => $this->_fromName,
      'toEmail' => $email,
      'subject' => $this->_emailSubject,
      'html' => $this->_htmlMessageEmail,
      'attachments' => $this->_createdPdfs,
    );
    $result = CRM_Utils_Mail::send($mailParams);
    if (!$result) {
      $message = ts('Could not send Email with PDF file as attachment in ' . __METHOD__);
      if ($this->_version >= 4.7) {
        Civi::log()->error($message);
      }
      else {
        CRM_Core_Error::debug_log_message($message);
      }
    }
  }

  /**
   * Method to send the actual pdf, either to all involved contacts or to specific email address
   *
   * @param $combine
   * @throws \Exception
   */
  public function processPdf($combine=false) {
    if (!$combine) {
      foreach ($this->_pdfsToBeGenerated as $pdfToBeGenerated) {
        $hash = md5(uniqid($pdfToBeGenerated['title'], TRUE));
        $_fileName = CRM_Utils_String::munge($pdfToBeGenerated['title']) . '_' . $hash . '.pdf';
        $_cleanName = CRM_Utils_String::munge($pdfToBeGenerated['title']) . '.pdf';
        $pdf = CRM_Utils_PDF_Utils::html2pdf($pdfToBeGenerated['html'], $_fileName, TRUE, $pdfToBeGenerated['pdf_format_id']);
        // if no email_activity, use temp folder otherwise use customFileUploadDir
        if (isset($this->_apiParams['email_activity']) && $this->_apiParams['email_activity'] == TRUE) {
          $config = CRM_Core_Config::singleton();
          $_fullPathName = $config->customFileUploadDir . $_fileName;
          $this->_createdFileIds[] = $this->createFileForPDF($_fileName);
        }
        else {
          $_fullPathName = CRM_Utils_File::tempnam();
        }
        file_put_contents($_fullPathName, $pdf);
        unset($pdf); //we don't need the temp file in memory
        $this->_createdPdfs[] = [
          'fullPath' => $_fullPathName,
          'mime_type' => 'application/pdf',
          'cleanName' => $_cleanName,
        ];
      }
    } else {
      $html = [];
      foreach ($this->_pdfsToBeGenerated as $pdfToBeGenerated) {
        $hash = md5(uniqid($pdfToBeGenerated['title'], TRUE));
        $_fileName = CRM_Utils_String::munge($pdfToBeGenerated['title']) . '_' . $hash . '.pdf';
        $_cleanName = CRM_Utils_String::munge($pdfToBeGenerated['title']) . '.pdf';
        $html = array_merge($html, $pdfToBeGenerated['html']);
        $_pdf_format_id = $pdfToBeGenerated['pdf_format_id'];
      }
      if (count($html)) {
        $pdf = CRM_Utils_PDF_Utils::html2pdf($html, $_fileName, TRUE, $_pdf_format_id);
        // if no email_activity, use temp folder otherwise use customFileUploadDir
        if (isset($this->_apiParams['email_activity']) && $this->_apiParams['email_activity'] == TRUE) {
          $config = CRM_Core_Config::singleton();
          $_fullPathName = $config->customFileUploadDir . $_fileName;
          $this->_createdFileIds[] = $this->createFileForPDF($_fileName);
        }
        else {
          $_fullPathName = CRM_Utils_File::tempnam();
        }
        file_put_contents($_fullPathName, $pdf);
        unset($pdf); //we don't need the temp file in memory
        $this->_createdPdfs[] = [
          'fullPath' => $_fullPathName,
          'mime_type' => 'application/pdf',
          'cleanName' => $_cleanName,
        ];
      }
    }

    if ($this->_toEmail) {
      $this->sendPdf($this->_toEmail);
    }
    else {
      foreach ($this->_toContactIds as $contactId) {
        $email = $this->getPrimaryEmail($contactId);
        if ($email) {
          $this->sendPdf($email, $contactId);
        }
        else {
          $message = ts('Email with attached PDF not sent to contact ID ') . $contactId
            . ts(' as no primary email could be found for the contact, all emails for the contact are on hold, the contact opted out of mailing or the contact is deceased in ') . __METHOD__;
          if ($this->_version >= 4.7) {
            Civi::log()->warning($message);
          }
          else {
            CRM_Core_Error::debug_log_message($message);
          }
        }
      }
    }

    // set up the parameters for CRM_Utils_Mail::send
    // create Email activities for each contact if required
    if (isset($this->_apiParams['email_activity']) && $this->_apiParams['email_activity'] == TRUE) {
      if ($this->_createdFileIds) {
        foreach ($this->_toContactIds as $contactId) {
          if (count($this->_processedCaseIds)) {
            foreach ($this->_processedCaseIds as $case_id) {
              $this->createEmailActivity($contactId, $this->_createdFileIds, $case_id);
            }
          }
        }
      }
    }
  }

  /**
   * Method to get primary email of contact
   *
   * @param $contactId
   * @return bool|string
   */
  private function getPrimaryEmail($contactId) {
    // return false if contact opted out of all mails
    try {
      $contact = civicrm_api3('Contact', 'getsingle', array(
        'id' => $contactId,
        'return' => array("do_not_email", "do_not_trade", "is_deceased"),
      ));
      if ($contact['do_not_email'] == 1 || $contact['do_not_trade'] == 1 || $contact['is_deceased'] == 1) {
        return FALSE;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }

    try {
      return (string) civicrm_api3('Email', 'getvalue', array(
        'contact_id' => $contactId,
        'is_primary' => 1,
        'on_hold' => 0,
        'return' => 'email',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      try {
        return (string) civicrm_api3('Email', 'getvalue', array(
          'contact_id' => $contactId,
          'on_hold' => 0,
          'options' => array('limit' => 1),
          'return' => 'email',
        ));
      }
      catch (CiviCRM_API3_Exception $ex) {
        return FALSE;
      }
    }
  }

  /**
   * Method to save the PDF as a file in the customFileUploadDir
   * @param $filename
   */
  private function createFileForPDF($filename) {
    try {
      $file = civicrm_api3('File', 'create', array(
        'mime_type' => 'application/pdf',
        'uri' => $filename,
      ));
      return $file['id'];
    }
    // if no joy, log error but continue processing
    catch (CiviCRM_API3_Exception $ex) {
      $message = ts('Could not save the created PFD as a File in civicrm in ' . __METHOD__
        . ', error message from API File create: ' . $ex->getMessage());
      if ($this->_version >= 4.7) {
        Civi::log()->error($message);
      }
      else {
        CRM_Core_Error::debug_log_message($message);
      }
      return FALSE;
    }
  }

  /**
   * Method to create email activity for contact with PDF as attachment
   *
   * @param int $contactId
   * @param array $fileIds
   * @param $case_id
   */
  private function createEmailActivity($contactId, $fileIds, $case_id=null) {
    $activityTypeId = $this->getActivityTypeId('email');
    if ($activityTypeId) {
      // first create activity
      $activityParams = array(
        'source_contact_id' => $contactId,
        'activity_type_id' => $activityTypeId,
        'activity_date_time' => date('YmdHis'),
        'details' => $this->_htmlMessageEmail,
        'subject' => $this->_emailSubject,
      );

      $activity = CRM_Activity_BAO_Activity::create($activityParams);
      if ($activity) {
        if($this->_version >= 4.4){
          $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
          $targetId = CRM_Utils_Array::key('Activity Targets', $activityContacts);
          $activityTargetParams = array(
            'activity_id' => $activity->id,
            'contact_id' => $contactId,
            'record_type_id' => $targetId,
          );
          CRM_Activity_BAO_ActivityContact::create($activityTargetParams);
        }
        else {
          $activityTargetParams = array(
            'activity_id' => $activity->id,
            'target_contact_id' => $contactId,
          );
          CRM_Activity_BAO_Activity::createActivityTarget($activityTargetParams);
        }
        // add to case if required
        if ($case_id) {
          $this->processCaseActivity($activity->id, $case_id);
        }
        // add record to civicrm_entity_file to add attachment to activity
        foreach($fileIds as $fileId) {
          $this->createEntityFileForPDF($fileId, $activity->id);
        }
      }
    }
  }

  /**
   * Method to create an entity file record for the PDF and activity
   *
   * @param int $fileId
   * @param int $activityId
   */
  private function createEntityFileForPdf($fileId, $activityId) {
    $params = array(
      1 => array('civicrm_activity', 'String'),
      2 => array($activityId, 'Integer'),
      3 => array($fileId, 'Integer')
    );
    // first check if we already have the record (should never happen but to be sure to be sure)
    $query = "SELECT COUNT(*) FROM civicrm_entity_file WHERE entity_table = %1 AND entity_id = %2 AND file_id = %3";
    $count = CRM_Core_DAO::singleValueQuery($query, $params);
    if ($count > 0) {
      $message = ts('Already found an attachment for activity_id ') . $activityId . ts(' and file_id ')
        . $fileId . ts(' in ') . __METHOD__;
      if ($this->_version >= 4.7) {
        Civi::log()->warning($message);
      }
      else {
        CRM_Core_Error::debug_log_message($message);
      }
    }
    else {
      $insert = "INSERT INTO civicrm_entity_file (entity_table, entity_id, file_id) VALUES(%1, %2, %3)";
      CRM_Core_DAO::executeQuery($insert, $params);
    }
  }

  /**
   * Method to add the Create PDF activity to the contact
   *
   * @param $contactId
   * @throws API_Exception
   * @throws CRM_Core_Exception
   */
  private function createPdfActivities($contactId) {
    $activityTypeId = $this->getActivityTypeId('pdf');
    if ($activityTypeId) {
      $activityParams = array(
        'source_contact_id' => $contactId,
        'activity_type_id' => $activityTypeId,
        'activity_date_time' => date('YmdHis'),
        'details' => $this->_htmlMessage,
        'subject' => $this->_subject,
      );
      $activity = CRM_Activity_BAO_Activity::create($activityParams);
      if ($activity) {
        // Compatibility with CiviCRM >= 4.4
        if ($this->_version >= 4.4) {
          $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
          $targetId = CRM_Utils_Array::key('Activity Targets', $activityContacts);
          $activityTargetParams = array(
            'activity_id' => $activity->id,
            'contact_id' => $contactId,
            'record_type_id' => $targetId,
          );
          CRM_Activity_BAO_ActivityContact::create($activityTargetParams);
        }
        else {
          $activityTargetParams = array(
            'activity_id' => $activity->id,
            'target_contact_id' => $contactId,
          );
          CRM_Activity_BAO_Activity::createActivityTarget($activityTargetParams);
        }
        // add to case if required
        if (isset($this->_apiParams['case_id']) && !empty($this->_apiParams['case_id'])) {
          $this->processCaseActivity($activity->id, $this->_apiParams['case_id']);
        }
      }
    }
  }

  /**
   * Method to format the message
   *
   * @param $messageTemplate
   * @return string
   */
  private function formatMessage($messageTemplate){
    $this->_htmlMessage = $messageTemplate->msg_html;

    //time being hack to strip '&nbsp;'
    //from particular letter line, CRM-6798
    $newLineOperators = array(
      'p' => array(
        'oper' => '<p>',
        'pattern' => '/<(\s+)?p(\s+)?>/m',
      ),
      'br' => array(
        'oper' => '<br />',
        'pattern' => '/<(\s+)?br(\s+)?\/>/m',
      ),
    );
    $htmlMsg = preg_split($newLineOperators['p']['pattern'], $this->_htmlMessage);
    foreach ($htmlMsg as $k => & $m) {
      $messages = preg_split($newLineOperators['br']['pattern'], $m);
      foreach ($messages as $key => & $msg) {
        $msg = trim($msg);
        $matches = array();
        if (preg_match('/^(&nbsp;)+/', $msg, $matches)) {
          $spaceLen = strlen($matches[0]) / 6;
          $trimMsg = ltrim($msg, '&nbsp; ');
          $charLen = strlen($trimMsg);
          $totalLen = $charLen + $spaceLen;
          if ($totalLen > 100) {
            $spacesCount = 10;
            if ($spaceLen > 50) {
              $spacesCount = 20;
            }
            if ($charLen > 100) {
              $spacesCount = 1;
            }
            $msg = str_repeat('&nbsp;', $spacesCount) . $trimMsg;
          }
        }
      }
      $m = implode($newLineOperators['br']['oper'], $messages);
    }
    $this->_htmlMessage = implode($newLineOperators['p']['oper'], $htmlMsg);
    return $this->_htmlMessage;
  }

  /**
   * Method to get the message templates depending on the version
   *
   * @return CRM_Core_DAO_MessageTemplate|CRM_Core_DAO_MessageTemplates
   * @throws
   */
  private function getMessageTemplates() {
    // Compatibility with CiviCRM > 4.3
    if ($this->_version >= 4.4) {
      $messageTemplate =  new CRM_Core_DAO_MessageTemplate();
    } else {
      $messageTemplate = new CRM_Core_DAO_MessageTemplates();
    }
    $messageTemplate->id = $this->_apiParams['template_id'];
    if (!$messageTemplate->find(TRUE)) {
      throw new API_Exception('Could not find template with ID: ' . $this->_apiParams['template_id']);
    }
    // Optional pdf_format_id, if not default 0
    if (isset($this->_apiParams['pdf_format_id'])) {
      $messageTemplate->pdf_format_id = CRM_Utils_Array::value('pdf_format_id', $this->_apiParams, 0);
    }
    return $messageTemplate;
  }

  /**
   * Method to get the message template email
   *
   * @param $templateEmailId
   * @return CRM_Core_DAO_MessageTemplate|CRM_Core_DAO_MessageTemplates
   * @throws API_Exception
   */
  private function getMessageTemplatesEmail($templateEmailId) {
    if($this->_version >= 4.4) {
      $this->_messageTemplatesEmail = new CRM_Core_DAO_MessageTemplate();
    } else {
      $this->_messageTemplatesEmail = new CRM_Core_DAO_MessageTemplates();
    }
    $this->_messageTemplatesEmail->id = $templateEmailId;
    if (!$this->_messageTemplatesEmail->find(TRUE)) {
      throw new API_Exception('Could not find template with ID: ' . $templateEmailId);
    }
    return $this->_messageTemplatesEmail;
  }

  /**
   * Method to get the return properties for tokens
   *
   * @param $messageTokens
   * @return array
   */
  private function getReturnProperties($messageTokens) {
    $returnProperties = array(
      'sort_name' => 1,
      'email' => 1,
      'address' => 1,
      'do_not_email' => 1,
      'is_deceased' => 1,
      'on_hold' => 1,
      'display_name' => 1,
    );
    if (isset($messageTokens['contact'])) {
      foreach ($messageTokens['contact'] as $key => $value) {
        $returnProperties[$value] = 1;
      }
    }
    return $returnProperties;
  }

  /**
   * Method to get the activity type id based on the incoming type
   *
   * @param string $type
   * @return int
   * @throws
   */
  private function getActivityTypeId($type) {
    try {
      switch ($type) {
        case 'pdf':
          return (int) civicrm_api3('OptionValue', 'getvalue', array(
            'option_group_id' => 'activity_type',
            'name' => 'Print PDF Letter',
            'return' => 'value',
          ));
          break;
        case 'email':
          return (int) civicrm_api3('OptionValue', 'getvalue', array(
            'option_group_id' => 'activity_type',
            'name' => 'Email',
            'return' => 'value',
          ));
          break;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      throw new API_Exception(ts('Could not find an activity type for ' . $type . ' in ')
        . __METHOD__ . ts(', error from API OptionValue getvalue: ') . $ex->getMessage());
    }
  }

  /**
   * Method to add the case activities
   *
   * @param int $activityId
   * @param int $caseId
   */
  private function processCaseActivity($activityId, $caseId) {
    $caseActivityDAO = new CRM_Case_DAO_CaseActivity();
    $caseActivityDAO->activity_id = $activityId;
    $caseActivityDAO->case_id = $caseId;
    $caseActivityDAO->find(TRUE);
    $caseActivityDAO->save();
  }

  /**
   * Method to check if case_id exists if passed and remove parameter case_id if not
   */
  private function validateCaseId() {
    if (isset($this->_apiParams['case_id'])) {
      // if empty, log warning and remove
      if (empty($this->_apiParams['case_id'])) {
        unset($this->_apiParams['case_id']);
        $message = ts('Empty parameter case_id passed to API Create PDF, case_id ignored in ') . __METHOD__;
        if ($this->_version>= 4.7) {
          Civi::log()->warning($message);
        }
        else {
          CRM_Core_Error::debug_log_message($message);
        }
      }
      else {
        // check if case_id exists and if not, unset and warning
        $query = 'SELECT COUNT(*) FROM civicrm_case WHERE id = %1';
        $count = CRM_Core_DAO::singleValueQuery($query, array(1 => array($this->_apiParams['case_id'], 'Integer')));
        if ($count == 0) {
          $message = ts('Could not find a case with case ID ') . $this->_apiParams['case_id'] . ts(' in ')
            . __METHOD__ . ts(', activity will be logged to contact(s) instead, fix manually.');
          if ($this->_version>= 4.7) {
            Civi::log()->warning($message);
          }
          else {
            CRM_Core_Error::debug_log_message($message);
          }
          unset($this->_apiParams['case_id']);
        }
      }
    }
  }

}
