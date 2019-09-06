# org.civicoop.pdfapi
PDF API for CiviCRM to create a PDF file and send it to a specified e-mail address.
This is usefull for automatic generation of letters

The entity for the PDF API is Pdf and the action is Create.
Parameters for the api are specified below:
- contact_id: list of contacts IDs to create the PDF Letter (separated by ",")
- template_id: ID of the message template which will be used in the API. _You have to enter the text in the HTML part of the template and select PDF Page format_
- to_email: e-mail address where the pdf file is send to
- from_email: the e-mail address the PDF will be sent from (default name if empty)
- the e-mail name the PDF will be sent from (default name if empty)
- pdf_format_id: (optional) ID of the PDF format, is not especified the default PDF format is used
- template_email_id: (optional) ID of the message template which will be used to generate the email body.
- template_email_use_subject: (optional) Use the message subject of the template email. 0 is false and 1 or highter is true.
