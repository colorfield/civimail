# CiviMail for Drupal 8

Integration with CiviCRM CiviMail.

### Features

Send a node from a CiviCRM Contact to CiviCRM Groups.

### Configuration

- Per content type configuration: enable via the CiviMail tab. 
Set then the view mode for the mail, and the groups
that are eligible to receive the mail.
- The CiviMail feature is available as a node local task 
(while viewing or editing a node from a CiviMail enabled content type). 


### Dependencies

[CiviCRM Core](https://github.com/civicrm/civicrm-core),
[Drupal 8](https://github.com/drupal/drupal),
[CiviCRM Drupal](https://github.com/civicrm/civicrm-drupal),
[CiviCRM Entity](https://drupal.org/project/civicrm_entity)

## Roadmap

- **Send test mails via CiviCRM.** 
Currently test mails are not sent via CiviMail but via Drupal mail.
So it needs extra configuration with MimeMail while sending HTML.
There is no CiviCRM API for that, but it really makes sense to test
through CiviMail.
- **Send immediately.** CiviMail campaigns are scheduled before 
being actually sent (waiting for the scheduled jobs to be executed).
Add an option to send immediately.
- **CiviMail Digest submodule.** Send to selected groups a digest at
a chosen interval of all nodes that were sent, with an option to include
updates. 
