<?php

/**
 * Pdf.Createmulti API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_pdf_Createmulti_spec(&$spec) {
  $spec['contact_id'] = array(
    'name' => 'contact_id',
    'title' => 'contact ID',
    'description' => 'ID of the CiviCRM contact',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
  );
  $spec['to_email'] = array(
    'name' => 'to_email',
    'title' => 'to email address',
    'description' => 'the e-mail address the PDF will be sent to',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_STRING,
  );
  $spec['from_email'] = array(
    'name' => 'from_email',
    'title' => 'from email address',
    'description' => 'the e-mail address the PDF will be sent from',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_STRING,
  );
  $spec['from_name'] = array(
    'name' => 'from_name',
    'title' => 'from email name',
    'description' => 'the e-mail name the PDF will be sent from',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_STRING,
  );
  $spec['body_template_id'] = array(
    'name' => 'body_template_id',
    'title' => 'template ID email body',
    'description' => 'ID of the template that will be used for the email body',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_INT,
  );
  $spec['email_subject'] = array(
    'name' => 'email_subject',
    'title' => 'Email subject',
    'description' => 'Subject of the email that sends the PDF',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_STRING,
  );
  $spec['pdf_activity'] = array(
    'name' => 'pdf_activity',
    'title' => 'Print PDF activity?',
    'description' => 'Log Print PDF activity for contact?',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_BOOLEAN,
  );
  $spec['email_activity'] = array(
    'name' => 'email_activity',
    'title' => 'Email activity?',
    'description' => 'Log Email activity for contact?',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_BOOLEAN,
  );
  $spec['combine'] = array(
    'name' => 'combine',
    'title' => 'Combine PDFs into one?',
    'description' => 'Combine the pdfs into one file?',
    'api.required' => 0,
    'type' => CRM_Utils_Type::T_BOOLEAN,
  );

  $spec['pdf_files'] = array(
    'name' => 'pdfs',
    'title' => 'PDFs',
    'description' => 'array/json data containing the following: [{contact_id, template_id, case_id}]',
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_TEXT,
  );
}

/**
 * Pdf.Createmulti API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 */
function civicrm_api3_pdf_Createmulti($params) {
  $pdf_files = $params['pdf_files'];
  if (is_string($pdf_files)) {
    $pdf_files = json_decode($pdf_files, JSON_OBJECT_AS_ARRAY);
  }
  $pdf = new CRM_Pdfapi_Pdf($params);
  foreach($pdf_files as $pdf_file) {
    $pdf->create($pdf_file);
  }
  $combine = isset($params['combine']) && $params['combine'] ? true : false;
  $pdf->processPdf($combine);
  $returnValues = array();
  return civicrm_api3_create_success($returnValues, $params, 'Pdf', 'Create');
}

