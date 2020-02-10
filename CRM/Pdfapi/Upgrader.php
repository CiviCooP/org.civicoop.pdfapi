<?php
use CRM_Pdfapi_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Pdfapi_Upgrader extends CRM_Pdfapi_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Add CiviRule Action when installing
   */
  public function install() {
    // if CiviRules installed
    try {
      $extensions = civicrm_api3('Extension', 'getcount', ['key' => 'org.civicoop.civirules', 'status' => 'installed']);
      if ($extensions > 0) {
        $this->executeSqlFile('sql/insertSendPDFAction.sql');
      }
    } catch (CiviCRM_API3_Exception $ex) {
    }
  }

  /**
   * remove managed entity
   */
  public function upgrade_1001() {
    $this->ctx->log->info('Applying update 1001 (remove managed entity');
    // if CiviRules installed
    try {
      $extensions = civicrm_api3('Extension', 'getcount', ['key' => 'org.civicoop.civirules', 'status' => 'installed']);
      if ($extensions > 0) {
        if (CRM_Core_DAO::checkTableExists('civicrm_managed')) {
          $query = 'DELETE FROM civicrm_managed WHERE module = %1 AND entity_type = %2';
          CRM_Core_DAO::executeQuery($query, array(
            1 => array('org.civicoop.pdfapi', 'String'),
            2 => array('CiviRuleAction', 'String'),
          ));
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
    }
    return TRUE;
  }

  /**
   * update action params in existing usage for body_template_id and email_subject
   */
  public function upgrade_1002() {
    $this->ctx->log->info('Applying update 1002 (introduce email body template and email subject');
    // if CiviRules installed
    try {
      $extensions = civicrm_api3('Extension', 'getcount', ['key' => 'org.civicoop.civirules', 'status' => 'installed']);
      if ($extensions > 0) {
        $query = "SELECT id FROM civirule_action WHERE name = %1 AND class_name = %2";
        $actionId = CRM_Core_DAO::singleValueQuery($query, array(
          1 => array('pdfapi_send', 'String'),
          2 => array('CRM_Pdfapi_CivirulesAction', 'String'),
        ));
        if ($actionId) {
          // update params in rule action if any present
          $query = "SELECT id, action_params FROM civirule_rule_action WHERE action_id = %1";
          $dao = CRM_Core_DAO::executeQuery($query, array(1 => array((int) $actionId, 'Integer')));
          while ($dao->fetch()) {
            $actionParams = unserialize($dao->action_params);
            $updateRuleAction = FALSE;
            if (!isset($actionParams['body_template_id'])) {
              $actionParams['body_template_id'] = "";
              $updateRuleAction = TRUE;
            }
            if (!isset($actionParams['email_subject'])) {
              $actionParams['email_subject'] = "";
              $updateRuleAction = TRUE;
            }
            if ($updateRuleAction) {
              $ruleAction = new CRM_Civirules_BAO_RuleAction();
              $ruleAction->id = $dao->id;
              $ruleAction->action_params = serialize($actionParams);
              $ruleAction->save();
            }
          }
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
    }
    return TRUE;
  }

  /**
   * update action params in existing usage for body_template_id and email_subject
   */
  public function upgrade_1003() {
    $this->ctx->log->info('Applying update 1002 (introduce email and/or pdf activity)');
    // if CiviRules installed
    try {
      $extensions = civicrm_api3('Extension', 'getcount', ['key' => 'org.civicoop.civirules', 'status' => 'installed']);
      if ($extensions > 0) {
        $query = "SELECT id FROM civirule_action WHERE name = %1 AND class_name = %2";
        $actionId = CRM_Core_DAO::singleValueQuery($query, array(
          1 => array('pdfapi_send', 'String'),
          2 => array('CRM_Pdfapi_CivirulesAction', 'String'),
        ));
        if ($actionId) {
          // update params in rule action if any present
          $query = "SELECT id, action_params FROM civirule_rule_action WHERE action_id = %1";
          $dao = CRM_Core_DAO::executeQuery($query, array(1 => array((int) $actionId, 'Integer')));
          while ($dao->fetch()) {
            $actionParams = unserialize($dao->action_params);
            $updateRuleAction = FALSE;
            if (!isset($actionParams['pdf_activity'])) {
              $actionParams['pdf_activity'] = 0;
              $updateRuleAction = TRUE;
            }
            if (!isset($actionParams['email_activity'])) {
              $actionParams['email_activity'] = "";
              $updateRuleAction = TRUE;
            }
            if ($updateRuleAction) {
              $ruleAction = new CRM_Civirules_BAO_RuleAction();
              $ruleAction->id = $dao->id;
              $ruleAction->action_params = serialize($actionParams);
              $ruleAction->save();
            }

          }
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {
    }
    return TRUE;
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   *
  public function postInstall() {
    $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'return' => array("id"),
      'name' => "customFieldCreatedViaManagedHook",
    ));
    civicrm_api3('Setting', 'create', array(
      'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
    ));
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   *
  public function enable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is disabled.
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = E::ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
