# org.civicoop.pdfapi
PDF API for CiviCRM to create a PDF file and send it to a specified e-mail address.

This extension contains two api's:
* __Pdf.Create__ - create a pdf
* __Pdf.Createmulti__ - create multiple pdfs at once.


## Pdf.Create

Parameters for the api are specified below:
- contact_id: list of contacts IDs to create the PDF Letter (separated by ",")
- template_id: ID of the message template which will be used in the API. _You have to enter the text in the HTML part of the template and select PDF Page format_
- to_email: e-mail address where the pdf file is send to
- from_email: the e-mail address the PDF will be sent from (default name if empty)
- from_name: the e-mail name the PDF will be sent from (default name if empty)
- pdf_format_id: (optional) ID of the PDF format, is not especified the default PDF format is used
- body_template_id: (optional) ID of the message template which will be used to generate the email body.
- email_subject: (optional) Provide a custom e-mail subject
- pdf_activity: create a pdf activity
- email_activity: create an e-mail activity
- case_id: Pdf is linked to a case

## Pdf.Createmulti

Parameters for the api are specified below:
- contact_id: list of contacts IDs to create the PDF Letter (separated by ",")
- to_email: e-mail address where the pdf file is send to
- from_email: the e-mail address the PDF will be sent from (default name if empty)
- from_name: the e-mail name the PDF will be sent from (default name if empty)
- body_template_id: (optional) ID of the message template which will be used to generate the email body.
- email_subject: (optional) Provide a custom e-mail subject
- pdf_activity: create a pdf activity
- email_activity: create an e-mail activity
- pdf_files: specification of all the pdf files to be generated.
  The next example creates two pdf's for two cases and two contacts and each file is generated using a separate template:
  `[
      {"case_id":2,"contact_id":10,"template_id":69},
      {"case_id":1,"contact_id":192,"template_id":71}
  ]`
  This parameter could be either JSON format or a PHP array.
- combine: yes/no create one large PDF.

