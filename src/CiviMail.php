<?php

namespace Drupal\civimail;

use Drupal\civicrm_entity\CiviCrmApiInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;

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
    // Contact (Drupal) entity does not return an email without relationships,
    // so get the contact from the CiviCRM API.
    $fromContactDetails = $this->getContact(['contact_id' => $from_cid]);
    $result = [
      'subject' => $entity->label(),
      // @todo get header and footer / get template from the bundle config
      'header_id' => '',
      'footer_id' => '',
      'body_text' => $this->getMailingTemplateText($entity),
      'body_html' => $this->getMailingTemplateHtml($entity),
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
   * Returns the markup for the mailing body wrapped in a mail template.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity used for the body.
   *
   * @return string
   *   Markup of the mail template.
   */
  private function getMailingTemplateHtml(ContentEntityInterface $entity) {
    $link = Link::fromTextAndUrl(t('View it online'), $this->getAbsoluteEntityUrl($entity));
    $link = $link->toRenderable();
    $build = [
      '#theme' => 'civimail_html',
      '#entity' => $entity,
      '#body' => $this->getMailingBodyHtml($entity),
      '#absolute_link' => \Drupal::service('renderer')->renderRoot($link),
    // @todo
      '#translation_links' => NULL,
    // @todo
      '#civicrm_header' => NULL,
    // @todo
      '#civicrm_footer' => NULL,
      '#civicrm_unsubscribe_url' => '{action.unsubscribeUrl}',
    ];
    return \Drupal::service('renderer')->renderRoot($build);
  }

  /**
   * Returns the the mailing body as plain text wrapped in a mail template.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity used for the body.
   *
   * @return string
   *   Text of the mail template.
   */
  private function getMailingTemplateText(ContentEntityInterface $entity) {
    $build = [
      '#theme' => 'civimail_text',
      '#entity' => $entity,
      '#body' => $this->getMailingBodyText($entity),
      '#absolute_url' => $this->getAbsoluteEntityUrl($entity)->toString(),
    // @todo
      '#translation_urls' => NULL,
    // @todo
      '#civicrm_header' => NULL,
    // @todo
      '#civicrm_footer' => NULL,
      '#civicrm_unsubscribe_url' => '{action.unsubscribeUrl}',
    ];
    return \Drupal::service('renderer')->renderRoot($build);
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
    $viewMode = civimail_get_entity_bundle_settings('view_mode', $entity->getEntityTypeId(), $entity->bundle());
    $view = $viewBuilder->view($entity, $viewMode);
    $renderedView = \Drupal::service('renderer')->renderRoot($view);
    // Absolutize first then shorten urls.
    $absoluteUrls = $this->absolutizeUrls($renderedView);
    $shortenUrls = $this->shortenUrls($absoluteUrls);
    $build = [
      '#type' => 'markup',
      '#markup' => $shortenUrls,
    ];
    return \Drupal::service('renderer')->renderRoot($build);
  }

  /**
   * Returns the mailing body as plain text.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity used for the body.
   *
   * @return string
   *   Markup of the entity view mode.
   */
  private function getMailingBodyText(ContentEntityInterface $entity) {
    // @todo implement, review Mime Mail helpers
    // Absolutize first then shorten.
    // $result = $this->absolutizeUrls($result);
    // $result = $this->shortenUrls($result);
    return 'Plain text mail not implemented yet';
  }

  /**
   * Replaces a text with relative urls by absolute ones.
   *
   * The match is done with urls starting with a slash.
   *
   * @param string $text
   *   Text that contains relative urls.
   *
   * @return string
   *   Text replaced with absolute urls.
   */
  private function absolutizeUrls($text) {
    // @todo review possible security issue.
    $baseUrl = \Drupal::request()->getSchemeAndHttpHost();
    // Cover multi-site or public files configuration override.
    // @todo review private files and other possible cases.
    if ($wrapper = \Drupal::service('stream_wrapper_manager')->getViaUri('public://')) {
      $publicDirectory = $wrapper->getDirectoryPath();
      $publicFilesBaseUrl = $wrapper->getExternalUrl();
    }
    $result = str_replace(
      ['href="/', 'src="/' . $publicDirectory],
      ['href="' . $baseUrl . '/', 'src="' . $publicFilesBaseUrl . '/'],
      $text
    );
    return $result;
  }

  /**
   * Shortens urls to comply with 128 chars CiviCRM database limit.
   *
   * This is necessary while using the click tracking, enabled by default.
   *
   * @param string $text
   *   Text that contains absolute urls.
   *
   * @return string
   *   Text replaced with shortened urls.
   */
  private function shortenUrls($text) {
    // @todo implement
    return $text;
  }

  /**
   * Returns an absolute Url to an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to get the Url from.
   *
   * @return \Drupal\Core\Url
   *   The absolute Url to the entity.
   */
  private function getAbsoluteEntityUrl(ContentEntityInterface $entity) {
    // @todo cover other entity types.
    $result = NULL;
    switch ($entity->getEntityTypeId()) {
      case 'node':
        $result = Url::fromRoute('entity.node.canonical', ['node' => $entity->id()])->setAbsolute();
        break;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function sendMailing(array $params, ContentEntityInterface $entity) {
    $result = FALSE;
    $mailingResult = $this->civicrmEntityApi->save('Mailing', $params);
    if ($mailingResult['is_error'] === 0) {
      $result = TRUE;
      // $message = t('CiviMail mailing for @subject scheduled.',
      // ['@subject' => $result['values'][$result['id']]['subject'],]);.
      $message = t('CiviMail mailing for <em>@subject</em> scheduled.', ['@subject' => $params['subject']]);
      $this->messenger->addStatus($message);
      // @todo review submit
      // $result = civicrm_api3('Mailing', 'submit', $params);
      // @todo optionally execute process_mailing job via bundle configuration
      // civicrm_api3_job_process_mailing($params); // in API v3 Job.php
      $this->logMailing($mailingResult, $entity, $params['groups']['include']);
    }
    else {
      // @todo get exception result
      $this->messenger->addError(t('Error while sending the mailing.'));
    }
    return $result;
  }

  /**
   * Logs the relation between the CiviCRM mailing, groups and the entity.
   *
   * @param array $mailing_result
   *   The CiviCRM mailing result.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity that is the subject of the mailing.
   * @param array $groups
   *   List of CiviCRM group ids for the mailing.
   */
  private function logMailing(array $mailing_result, ContentEntityInterface $entity, array $groups) {
    $user = \Drupal::currentUser();
    $fields = [
      'entity_id' => (int) $entity->id(),
      'entity_type_id' => (string) $entity->getEntityTypeId(),
      'entity_bundle' => (string) $entity->bundle(),
      'langcode' => (string) $entity->language()->getId(),
      'uid' => (int) $user->id(),
      'civicrm_mailing_id' => (int) $mailing_result['id'],
      'timestamp' => \Drupal::time()->getRequestTime(),
    ];
    try {
      $insert = \Drupal::database()->insert('civimail_entity_mailing');
      $insert->fields($fields);
      $insert->execute();

      foreach ($groups as $groupId) {
        $insert = \Drupal::database()->insert('civimail_entity_mailing__group');
        $fields = [
          'civicrm_mailing_id' => (int) $mailing_result['id'],
          'civicrm_group_id' => (int) $groupId,
        ];
        $insert->fields($fields);
        $insert->execute();
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('civimail')->error($e->getMessage());
      \Drupal::messenger()->addError($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityMailingHistory(ContentEntityInterface $entity) {
    $result = [];
    // @todo optimization is necessary here.
    $query = \Drupal::database()->select('civimail_entity_mailing', 'logs');
    $query->fields('logs', ['civicrm_mailing_id'])
      ->condition('logs.entity_id', $entity->id())
      ->condition('logs.entity_type_id', $entity->getEntityTypeId())
      ->condition('logs.entity_bundle', $entity->bundle())
      ->condition('logs.langcode', $entity->language()->getId());
    $query->orderBy('logs.civicrm_mailing_id', 'DESC');
    $logsResult = $query->execute()->fetchAll();
    foreach ($logsResult as $row) {
      // Get the details of the mailing.
      $civiCrmMailing = $this->civicrmEntityApi->get('Mailing', ['id' => $row->civicrm_mailing_id]);
      // There does not seem to be any api that gets mailing groups,
      // an issue could be opened for that.
      // A Drupal table currently stores the results.
      $query = \Drupal::database()->select('civimail_entity_mailing__group', 'mailing_group');
      $query->fields('mailing_group', ['civicrm_group_id'])
        ->condition('mailing_group.civicrm_mailing_id', $row->civicrm_mailing_id);
      $groupsResult = $query->execute()->fetchAll();
      $rowResult = [];
      $rowResult['mailing'] = $civiCrmMailing[$row->civicrm_mailing_id];
      $rowResult['groups'] = [];
      foreach ($groupsResult as $groupRow) {
        $rowResult['groups'][] = $groupRow->civicrm_group_id;
      }
      // Wrap all together.
      $result[$row->civicrm_mailing_id] = $rowResult;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function sendTestMail($from_cid, ContentEntityInterface $entity, $to_mail) {
    // This should be available from the CiviCRM API,
    // currently delegating it to Drupal mail.
    if (!\Drupal::moduleHandler()->moduleExists('mimemail')) {
      // And ideally tests should not be done by Mime Mail
      // but straight from CiviMail. CiviCRM tokens will not be available.
      $this->messenger->addWarning(t('You can improve HTML mail tests by installing the Mime Mail module.'));
    }
    // The subject in an email can't be with HTML, so strip it.
    $params['subject'] = t('[ TEST ] @subject', ['@subject' => $entity->label()]);
    // @todo get header and footer / get template from the bundle config
    $params['body'] = $this->getMailingTemplateHtml($entity);
    // Pass the message entity along to hook_drupal_mail().
    $params['entity'] = $entity;

    // Pass the relevant from contact data to hook_drupal_mail().
    // Fallback to system defaults.
    $fromContact = $this->getContact(['contact_id' => $from_cid]);
    $systemConfig = \Drupal::configFactory()->get('system.site');
    $params['from_mail'] = empty($fromContact['email']) ? $systemConfig->get('mail') : $fromContact['email'];
    $params['from_name'] = empty($fromContact['display_name']) ? $systemConfig->get('name') : $fromContact['display_name'];

    $result = \Drupal::service('plugin.manager.mail')->mail(
      'civimail',
      'entity_test_mail',
      $to_mail,
      $entity->language()->getId(),
      $params
    );
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
        // Name seems to be used, getting title
        // $result[$id] = $group->label();
        $result[$id] = $group->get('title')->value;
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
  public function hasCiviCrmRequirements() {
    $result = TRUE;
    // Check if CiviCRM Entity module is installed.
    if (\Drupal::moduleHandler()->moduleExists('civicrm_entity')) {
      // Check if the CiviCRM Group and Contact entities are enabled.
      $civicrmEnabledEntities = array_filter(
        $this->entityTypeManager->getDefinitions(),
        function (EntityTypeInterface $type) {
          return $type->getProvider() == 'civicrm_entity' && $type->get('civicrm_entity_ui_exposed');
        }
      );
      if (!array_key_exists('civicrm_group', $civicrmEnabledEntities)) {
        $result = FALSE;
      }
      if (!array_key_exists('civicrm_contact', $civicrmEnabledEntities)) {
        $result = FALSE;
      }
      if (!$result) {
        $civicrmEntityUrl = Url::fromRoute('civicrm_entity.settings', [], [
          'query' => ['destination' => \Drupal::request()->getRequestUri()],
        ]);
        $civicrmEntityLink = Link::fromTextAndUrl(t('CiviCRM Group and Contact entities'), $civicrmEntityUrl);
        $civicrmEntityLink = $civicrmEntityLink->toRenderable();
        $this->messenger->addError(t('@civicrm_entity_link must be enabled.', [
          '@civicrm_entity_link' => render($civicrmEntityLink),
          '@group_types_link' => render($groupTypesLink),
        ]));
      }
    }
    else {
      $result = FALSE;
      $this->messenger->addError(t('CiviCRM Entity module is not installed.'));
    }
    return $result;
  }

}
