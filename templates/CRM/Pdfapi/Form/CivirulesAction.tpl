<h3>{$ruleActionHeader}</h3>
<div class="crm-block crm-form-block crm-civirule-rule_action-block-pdf-create">
  <div class="help-block" id="help">
    {ts}<p>This is the form where you can set what is going to happen with the PDF.</p>
      <p>Overall the Send PDF action will send an email with a PDF as an attachment to a single e-mailaddress or to the e-mailaddresses of the contacts involved (usually one).</p>
      <p>The <strong>From e-mail address</strong> and <strong>From e-mail name</strong> are optional. They allow you to override the default email address (<em>{$default_from_email}</em>) and email name (<em>{$default_from_name}</em>)</p>

      <p>The <strong>To e-mail address</strong> is optional. It allows you to specify a single e-mail address where the PDF will be sent to. This makes sense if you ALWAYS want to send the PDF to a specific e-mailaddress, for example the e-mailaddress for the printer. Leave this empty if you want to send the PDF to the e-mailaddress of the contact(s) that are touched by your rule (for example send a PDF by e-mail to the new individual that has just been created).</p>
      <p>The <strong>Message template for the PDF</strong> is mandatory. Here you select the template that will be used for the PDF.</p>
      <p>The <strong>Message template for the e-mail that sends the PDF</strong> is optional. It allows you to select a template that will be used for the e-mail message with which the PDF will be sent (as an attachment)</p>
      <p>The <strong>Subject for the e-mail that will send the PDF</strong> is optional. It allows you to type the subject text for the e-mail with which the PDF will be sent (as an attachment).</p>
      <p>The <strong>Print PDF activity for each contact?</strong> is optional. If you tick the box, a Print PDF activity is either stored on the case if relevant and ticked or stored against each of the involved contacts.</p>
      <p>The <strong>Email activity for each contact?</strong> is optional. If you tick the box, an Email activity is either stored on the case if relevant and ticked or stored against each of the involved contacts. The Email activity will have a link to the attached PDF file.</p>
      <p>Finally, if your trigger has something to do with a Case, you will see an option <strong>File activity on case</strong>. If you tick this option the activity (Email, Print PDF or both) will be stored on the case rather than on the contact. If you do NOT tick this option, or it does not appear because it does not make any sense in the context of your rule the activity or activities will be stored on the contact.</p>
    {/ts}
  </div>

  <div class="crm-section">
    <div class="label">{$form.from_email.label}</div>
    <div class="content">{$form.from_email.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.from_name.label}</div>
    <div class="content">{$form.from_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.to_email.label}</div>
    <div class="content">{$form.to_email.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.template_id.label}</div>
    <div class="content">{$form.template_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.body_template_id.label}</div>
    <div class="content">{$form.body_template_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.email_subject.label}</div>
    <div class="content">{$form.email_subject.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.pdf_activity.label}</div>
    <div class="content">{$form.pdf_activity.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.email_activity.label}</div>
    <div class="content">{$form.email_activity.html}</div>
    <div class="clear"></div>
  </div>
  {if $hasCase}
    <div class="crm-section">
      <div class="label">{$form.file_on_case.label}</div>
      <div class="content">{$form.file_on_case.html}</div>
      <div class="clear"></div>
    </div>
  {/if}
</div>
<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
