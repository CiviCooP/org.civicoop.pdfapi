<?php
/**
 * Class for CiviRule Action pdf create
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_Pdfapi_CivirulesAction extends CRM_CivirulesActions_Generic_Api {

  /**
   * Method to get the api entity to process in this CiviRule action
   *
   * @access protected
   * @abstract
   */
  protected function getApiEntity() {
    return 'Pdf';
  }

  /**
   * Method to get the api action to process in this CiviRule action
   *
   * @access protected
   * @abstract
   */
  protected function getApiAction() {
    return 'create';
  }

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $parameters
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return array
   * @access protected
   */
  protected function alterApiParameters($parameters, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $parameters['contact_id'] = $triggerData->getContactId();
    // todo check if the file_on_case is yes, if so, try to set the case_id
    return $parameters;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return bool|string
   * $access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return CRM_Utils_System::url('civicrm/civirules/actions/pdfapi', 'rule_action_id=' . $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $templateTitle = $this->getTemplateTitle($params['template_id']);
    $prettyTxt = ts('Send PDF to e-mail address (printer or mailbox) "%1" with template "%2"', array(
      1 => $params['to_email'],
      2 => $templateTitle,
    ));
    if(isset($params['from_email']) && !empty ($params['from_email'])) {
      $prettyTxt .= ts(' , email is sent from email address "' ) . $params['from_email'] . '"';
    }
    if(isset($params['from_name']) && !empty ($params['from_name'])) {
      $prettyTxt .= ts(' , email sender name is "' ) . $params['from_name'] . '"';
    }
    if (isset($params['body_template_id']) && !empty($params['body_template_id'])) {
      $bodyTemplateTitle = $this->getTemplateTitle($params['body_template_id']);
      $prettyTxt .= ts(' , template for e-mail body "') . $bodyTemplateTitle . '"';
    }
    if (isset($params['email_subject']) && !empty($params['email_subject'])) {
      $prettyTxt .= ts(' and subject of the email: "') . $params['email_subject'] . '"';
    }
    if (isset($params['pdf_activity']) && $params['pdf_activity'] == TRUE) {
      $prettyTxt .= ts(', record Print Pdf activity for each contact');
    }
    if (isset($params['email_activity']) && $params['email_activity'] == TRUE) {
      $prettyTxt .= ts(', record Email activity for each contact');
    }
    if (isset($params['file_on_case']) && $params['file_on_case'] == TRUE) {
      $prettyTxt .= ts(', file on case');
    }
    return $prettyTxt;
  }

  /**
   * Method to get the title of a template
   *
   * @param $templateId
   * @return string
   */
  protected function getTemplateTitle($templateId) {
    $templateTitle = 'unknown template';
    // Compatibility with CiviCRM > 4.3
    $version = CRM_Core_BAO_Domain::version();
    if($version >= 4.4) {
      $messageTemplates = new CRM_Core_DAO_MessageTemplate();
    } else {
      $messageTemplates = new CRM_Core_DAO_MessageTemplates();
    }
    $messageTemplates->id = $templateId;
    $messageTemplates->is_active = true;
    if ($messageTemplates->find(TRUE)) {
      $templateTitle = $messageTemplates->msg_title;
    }
    return $templateTitle;
  }

}
