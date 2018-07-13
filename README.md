# CiviMail for Drupal 8

Integration with CiviCRM CiviMail.

### Features

Send a node from a CiviCRM Contact to CiviCRM Groups.

**CiviMail Digest submodule**

Send to selected groups a digest at
a chosen interval of nodes that were previously sent via CiviMail,
with an option to include updates.

### Configuration

- Per content type configuration: enable via the CiviMail tab. 
Set then the view mode for the mail, and the groups
that are eligible to send and receive the mail.
- The CiviMail feature is then available as a node local task 
(while viewing or editing a node from a CiviMail enabled content type). 

**CiviMail Digest submodule**

- Configure via _/admin/config/civimail_digest/settings_
- Preview and prepare digests via _/civimail_digest/digests_.

### Dependencies

- [Drupal 8](https://github.com/drupal/drupal)
- [CiviCRM Core](https://github.com/civicrm/civicrm-core)
- [CiviCRM Drupal](https://github.com/civicrm/civicrm-drupal)
- [CiviCRM Tools](https://drupal.org/project/civicrm_tools)

## Roadmap

- Send test mails via CiviCRM.
- Write documentation for Layout integration.
- Improve documentation and admin UI of CiviMail Digest.
- Automation of the digest creation via cron.
