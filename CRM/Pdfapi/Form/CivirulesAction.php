<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Pdfapi_Form_CivirulesAction extends CRM_Core_Form {

  protected $_civiRuleRuleActionId = FALSE;
  protected $_civiRuleRuleAction;
  protected $_civiRuleAction;
  protected $_civiRuleRule;
  protected $_civiRuleTriggerClass;
  protected $_hasCase = FALSE;

  /**
   * Overridden parent method to do pre-form building processing
   *
   * @throws Exception when action or rule action not found
   * @access public
   */
  public function preProcess() {
    $this->_civiRuleRuleActionId = CRM_Utils_Request::retrieve('rule_action_id', 'Integer');
    $this->_civiRuleRuleAction = new CRM_Civirules_BAO_RuleAction();
    $this->_civiRuleAction = new CRM_Civirules_BAO_Action();
    $this->_civiRuleRule = new CRM_Civirules_BAO_Rule();
    $this->_civiRuleRuleAction->id = $this->_civiRuleRuleActionId;
    if ($this->_civiRuleRuleAction->find(TRUE)) {
      $this->_civiRuleAction->id = $this->_civiRuleRuleAction->action_id;
      if (!$this->_civiRuleAction->find(TRUE)) {
        throw new Exception('CiviRules Could not find action with id ' .
          $this->_civiRuleRuleAction->action_id);
      }
    }
    else {
      throw new Exception('CiviRules Could not find rule action with id ' . $this->_civiRuleRuleActionId);
    }
    $this->_civiRuleRule->id = $this->_civiRuleRuleAction->rule_id;
    if (!$this->_civiRuleRule->find(true)) {
      throw new Exception('Civirules could not find rule');
    }
    $this->_civiRuleTriggerClass = CRM_Civirules_BAO_Trigger::getTriggerObjectByTriggerId($this->_civiRuleRule->trigger_id, TRUE);
    $this->_civiRuleTriggerClass->setTriggerId($this->_civiRuleRule->trigger_id);
    $providedEntities = $this->_civiRuleTriggerClass->getProvidedEntities();
    if (isset($providedEntities['Case'])) {
      $this->_hasCase = TRUE;
    }
    parent::preProcess();
  }

  /**
   * Method to get message templates
   *
   * @return array
   * @access protected
   */
  protected function getMessageTemplates() {
    $return = array('' => ts('-- please select --'));
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_msg_template` WHERE `is_active` = 1 AND `workflow_id` IS NULL ORDER BY msg_title");
    while($dao->fetch()) {
      $return[$dao->id] = $dao->msg_title;
    }
    return $return;
  }

  /**
   * Overridden parent method to build the form
   */
  function buildQuickForm() {
    $this->setFormTitle();
    $this->add('hidden', 'rule_action_id');
    $this->add('text', 'from_email', ts('From e-mail address'), array(),FALSE);
    $this->addRule('from_email', ts('Email is not valid.'), 'email');
    $this->add('text', 'from_name', ts('From e-mail name'), array(),FALSE);
    $this->add('text', 'to_email', ts('To e-mail address'), array(),FALSE);
    $this->addRule('to_email', ts('Email is not valid.'), 'email');
    $this->add('select', 'template_id', ts('Message template for the PDF'), $this->getMessageTemplates(), TRUE);
    $this->add('select', 'body_template_id', ts('Message template for the e-mail that sends the PDF'), $this->getMessageTemplates(), FALSE);
    $this->add('text', 'email_subject', ts('Subject for the e-mail that will send the PDF'), array(), FALSE);
    $this->add('checkbox','pdf_activity', ts('Print PDF activity for each contact?'));
    $this->add('checkbox','email_activity', ts('Email activity for each contact?'));
    if ($this->_hasCase) {
      $this->add('checkbox','file_on_case', ts('File activity on case'));
    }
    $this->assign('hasCase', $this->_hasCase);
    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $data = array();
    $defaultValues = array();
    $defaultValues['rule_action_id'] = $this->_civiRuleRuleActionId;
    if (!empty($this->_civiRuleRuleAction->action_params)) {
      $data = unserialize($this->_civiRuleRuleAction->action_params);
    }
    if (!empty($data['from_email'])) {
      $defaultValues['from_email'] = $data['from_email'];
    }
    if (!empty($data['from_name'])) {
      $defaultValues['from_name'] = $data['from_name'];
    }
    if (!empty($data['to_email'])) {
      $defaultValues['to_email'] = $data['to_email'];
    }
    if (!empty($data['to_email'])) {
      $defaultValues['to_email'] = $data['to_email'];
    }
    if (!empty($data['template_id'])) {
      $defaultValues['template_id'] = $data['template_id'];
    }
    if (!empty($data['body_template_id'])) {
      $defaultValues['body_template_id'] = $data['body_template_id'];
    }
    if (!empty($data['email_subject'])) {
      $defaultValues['email_subject'] = $data['email_subject'];
    }
    if (!empty($data['pdf_activity'])) {
      $defaultValues['pdf_activity'] = $data['pdf_activity'];
    }
    if (!empty($data['email_activity'])) {
      $defaultValues['email_activity'] = $data['email_activity'];
    }
    if (!empty($data['file_on_case'])) {
      $defaultValues['file_on_case'] = $data['file_on_case'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['from_email'] = $this->_submitValues['from_email'];
    $data['from_name'] = $this->_submitValues['from_name'];
    $data['to_email'] = $this->_submitValues['to_email'];
    $data['template_id'] = $this->_submitValues['template_id'];
    $data['body_template_id'] = $this->_submitValues['body_template_id'];
    $data['email_subject'] = $this->_submitValues['email_subject'];
    if (isset($this->_submitValues['pdf_activity']) && $this->_submitValues['pdf_activity'] == TRUE) {
      $data['pdf_activity'] = TRUE;
    }
    else {
      $data['pdf_activity'] = FALSE;
    }
    if (isset($this->_submitValues['email_activity']) && $this->_submitValues['email_activity'] == TRUE) {
      $data['email_activity'] = TRUE;
    }
    else {
      $data['email_activity'] = FALSE;
    }
    if (isset($this->_submitValues['file_on_case']) && $this->_submitValues['file_on_case'] == TRUE) {
      $data['file_on_case'] = TRUE;
    }
    else {
      $data['file_on_case'] = FALSE;
    }
    $ruleAction = new CRM_Civirules_BAO_RuleAction();
    $ruleAction->id = $this->_civiRuleRuleActionId;
    $ruleAction->action_params = serialize($data);
    $ruleAction->save();
    $session = CRM_Core_Session::singleton();
    $session->setStatus('Action ' . $this->_civiRuleAction->label . ' parameters updated to CiviRule '
      . CRM_Civirules_BAO_Rule::getRuleLabelWithId($this->_civiRuleRuleAction->rule_id),
      'Action parameters updated', 'success');
    $redirectUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id='
      . $this->_civiRuleRuleAction->rule_id, TRUE);
    CRM_Utils_System::redirect($redirectUrl);
  }

  /**
   * Method to set the form title
   *
   * @access protected
   */
  protected function setFormTitle() {
    $title = 'CiviRules Edit Action parameters';
    $this->assign('ruleActionHeader', 'Edit action ' . $this->_civiRuleAction->label . ' of CiviRule '
      . CRM_Civirules_BAO_Rule::getRuleLabelWithId($this->_civiRuleRuleAction->rule_id));
    CRM_Utils_System::setTitle($title);
  }

}
