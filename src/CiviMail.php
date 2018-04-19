<?php

namespace Drupal\civimail;

use Drupal\civicrm_entity\CiviCrmApiInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Class CiviMail.
 */
class CiviMail implements CiviMailInterface {

  /**
   * Drupal\civicrm_entity\CiviCrmApiInterface definition.
   *
   * @var \Drupal\civicrm_entity\CiviCrmApiInterface
   */
  protected $civicrmEntityApi;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Language\LanguageManagerInterface definition.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new CiviMail object.
   */
  public function __construct(CiviCrmApiInterface $civicrm_entity_api, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, MessengerInterface $messenger) {
    $this->civicrmEntityApi = $civicrm_entity_api;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityMailingParams($from_cid, ContentEntityInterface $entity, array $groups) {
    // Contact entity does not return an email without relationships
    // $this->civiMail->getContactEntity(4);.
    $fromContactDetails = $this->getContact(['contact_id' => 4]);
    $result = [
      'subject' => $entity->label(),
      'body_text' => $this->getMailingBodyText($entity),
      'body_html' => $this->getMailingBodyHtml($entity),
    // @todo mailing name in CiviCRM, must be max. 128 chars
      'name' => $entity->label(),
      'created_id' => $fromContactDetails['contact_id'],
      // 'contact_id' => $fromContactDetails['contact_id'],.
      'from_name'  => $fromContactDetails['display_name'],
      'from_email' => $fromContactDetails['email'],
      'replyto_email'  => $fromContactDetails['email'],
      // CiviMail removes duplicate contacts among groups.
      'groups' => [
        'include' => $groups,
        'exclude' => [],
      ],
      'api.mailing_job.create' => 1,
      'api.MailingRecipients.get' => [
        'mailing_id' => '$value.id',
        'api.contact.getvalue' => [
          'return' => 'display_name',
        ],
        'api.email.getvalue' => [
          'return' => 'email',
        ],
      ],
    ];
    return $result;
  }

  /**
   * Returns the markup for the mailing body.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity used for the body.
   *
   * @return string
   *   Markup of the entity view mode.
   */
  private function getMailingBodyHtml(ContentEntityInterface $entity) {
    $viewBuilder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    // @todo view mode from bundle config
    $view = $viewBuilder->view($entity, 'default');
    return \Drupal::service('renderer')->renderRoot($view);
  }

  /**
   * Returns the markup for the mailing body.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity used for the body.
   *
   * @return string
   *   Markup of the entity view mode.
   */
  private function getMailingBodyText(ContentEntityInterface $entity) {
    // @todo implement
    return 'Text not implemented yet';
  }

  /**
   * {@inheritdoc}
   */
  public function sendMailing($params) {
    $result = FALSE;
    $mailingResult = $this->civicrmEntityApi->save('Mailing', $params);
    // @todo review casting
    if ($mailingResult['is_error'] == 0) {
      $result = TRUE;
      // $message = t('CiviMail mailing for @subject scheduled.',
      // ['@subject' => $result['values'][$result['id']]['subject'],]);.
      $message = t('CiviMail mailing for <em>@subject</em> scheduled.', ['@subject' => $params['subject']]);
      $this->messenger->addStatus($message);
      // @todo execute process_mailing job
    }
    else {
      // @todo get exception
      $this->messenger->addError(t('Error while sending the mailing.'));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function sendTestMail($from_cid, ContentEntityInterface $entity, $to_mail) {
    $result = FALSE;
    // @todo implement
    $this->messenger->addError(t('sendTestMail() method is not implemented yet.'));
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRoute($entity_type_id) {
    $entity = NULL;
    $entityId = \Drupal::routeMatch()->getParameter($entity_type_id);
    try {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entityId);
    }
    catch (InvalidPluginDefinitionException $exception) {
      $this->messenger->addError($exception->getMessage());
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getContact(array $filter) {
    $result = [];
    $contacts = $this->civicrmEntityApi->get('Contact', $filter);
    // @todo getting the first contact found for the match
    // improve by letting know the user that there is probably
    // a contact mismatch because civicrm api returns default one if not found.
    if (!empty($contacts)) {
      reset($contacts);
      $result = $contacts[key($contacts)];
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupEntitiesLabel() {
    $result = [];
    try {
      $civicrmStorage = \Drupal::entityTypeManager()->getStorage('civicrm_group');
      $groups = $civicrmStorage->loadByProperties(['id' => 'civicrm_group']);
      foreach ($groups as $id => $group) {
        $result[$id] = $group->label();
      }
    }
    catch (InvalidPluginDefinitionException $exception) {
      $this->messenger->addError($exception->getMessage());
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEntitiesLabel() {
    $result = [];
    try {
      $civicrmStorage = \Drupal::entityTypeManager()->getStorage('civicrm_contact');
      $contacts = $civicrmStorage->loadByProperties(['id' => 'civicrm_contact']);
      foreach ($contacts as $id => $contact) {
        // @todo cid
        $result[$id] = $contact->label();
      }
    }
    catch (InvalidPluginDefinitionException $exception) {
      $this->messenger->addError($exception->getMessage());
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactEntity($cid) {
    $result = NULL;
    try {
      // @todo there must be another way to get a contact by its cid
      $civicrmStorage = \Drupal::entityTypeManager()->getStorage('civicrm_contact');
      $contacts = $civicrmStorage->loadByProperties(['id' => 'civicrm_contact']);
      foreach ($contacts as $id => $contact) {
        if ($id == $cid) {
          $result = $contact;
        }
      }
    }
    catch (InvalidPluginDefinitionException $exception) {
      $this->messenger->addError($exception->getMessage());
    }
    return $result;
  }

}
