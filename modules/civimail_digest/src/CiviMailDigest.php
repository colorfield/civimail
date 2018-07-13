<?php

namespace Drupal\civimail_digest;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\civicrm_tools\CiviCrmApiInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CiviMailDigest.
 */
class CiviMailDigest implements CiviMailDigestInterface {

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\civicrm_tools\CiviCrmApiInterface definition.
   *
   * @var \Drupal\civicrm_tools\CiviCrmApiInterface
   */
  protected $civicrmToolsApi;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $digestConfig;

  /**
   * Constructs a new CiviMailDigest object.
   */
  public function __construct(Connection $database, CiviCrmApiInterface $civicrm_tools_api, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->civicrmToolsApi = $civicrm_tools_api;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->digestConfig = $this->configFactory->get('civimail_digest.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    $result = FALSE;
    if (!$this->digestConfig->get('is_active')) {
      \Drupal::messenger()->addWarning(t('The digest feature is not enabled.'));
    }
    else {
      $result = TRUE;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function hasNextDigestContent() {
    return !empty($this->prepareDigestContent()['entities']);
  }

  /**
   * Get the content entity ids from CiviMail mailings for a digest.
   *
   * Keeping this structure as private as it is not really convenient
   * to manipulate but avoids running too many queries to get both
   * mailing ids and entity ids.
   *
   * @return array
   *   Entities (content entity ids grouped by entity type ids) and mailings.
   */
  private function prepareDigestContent() {
    $result = [];
    if ($this->isActive()) {
      $civiMailMailings = $this->selectDigestMailings();
      $result = [
        'entities' => [],
        'mailings' => [],
      ];
      foreach ($civiMailMailings as $row) {
        if (empty($result['entities'][$row->entity_type_id])) {
          $result['entities'][$row->entity_type_id] = [];
        }
        $result['entities'][$row->entity_type_id][] = $row->entity_id;
        $result['mailings'][] = $row->civicrm_mailing_id;
      }
    }
    return $result;
  }

  /**
   * Selects the CiviMail mailings to be included in a digest.
   *
   * These candidates are evaluated from CiviMail mailings that were
   * previously sent and from the configured limitations.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   CiviMail mailings and their entity references.
   */
  private function selectDigestMailings() {
    $quantityLimit = $this->digestConfig->get('quantity_limit');
    $language = $this->digestConfig->get('language');
    $includeUpdate = $this->digestConfig->get('include_update');

    $configuredBundles = $this->digestConfig->get('bundles');
    $bundles = [];
    // Get rid of the keys, take only values if they are the same.
    foreach ($configuredBundles as $key => $configuredBundle) {
      if ($configuredBundle === $key) {
        $bundles[] = $configuredBundle;
      }
    }

    $maxDays = $this->digestConfig->get('age_in_days');
    // @todo get from system settings
    $timeZone = new \DateTimeZone('Europe/Brussels');
    $contentAge = new \DateTime('now -' . $maxDays . ' day', $timeZone);

    // Get the mailings to be excluded.
    $sentMailings = $this->selectSentDigestMailings();

    // Get all the CiviMail mailings for entities that are matching
    // the configuration limitations.
    $query = $this->database->select('civimail_entity_mailing', 'cem')
      ->fields('cem', [
        'entity_id',
        'entity_type_id',
        'entity_bundle',
        'langcode',
        'civicrm_mailing_id',
        'timestamp',
      ]
      );
    $query->condition('cem.timestamp', $contentAge->getTimestamp(), '>');
    // @todo extend to other entity types
    $query->condition('cem.entity_type_id', 'node');
    $query->condition('cem.entity_bundle', $bundles, 'IN');
    if (!empty($sentMailings)) {
      $query->condition('cem.civicrm_mailing_id', $sentMailings, 'NOT IN');
    }
    $query->condition('cem.langcode', $language);
    $query->orderBy('cem.timestamp', 'DESC');
    $query->range(0, $quantityLimit);
    $result = $query->execute()->fetchAll();
    // @todo compare sent entity ids + if updates must be included
    return $result;
  }

  /**
   * Selects all the mailing that have already been included in a digest.
   *
   * This must not be confused with the mailing id of the Digest itself.
   * These ones are the mailing that were sent via CiviMail that have
   * been part of a digest.
   *
   * @todo review implementation because the name implies a query result.
   *
   * @return array
   *   List of mailing ids.
   */
  private function selectSentDigestMailings() {
    $result = [];
    $query = $this->database->select('civimail_digest__mailing', 'cdm');
    $queryResult = $query->fields('cdm', ['civicrm_mailing_id'])->execute();
    foreach ($queryResult as $row) {
      $result[] = $row->civicrm_mailing_id;
    }
    return $result;
  }

  /**
   * Select digest content entities.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   Digest content entities.
   */
  private function selectDigestEntities($digest_id) {
    $query = $this->database->select('civimail_digest__mailing', 'cdm');
    $query->condition('cdm.digest_id', $digest_id);
    $query->fields('cdm', ['digest_id']);
    $query->join(
      'civimail_entity_mailing',
      'cem',
      'cem.civicrm_mailing_id = cdm.civicrm_mailing_id');
    $query->fields('cem', [
      'entity_type_id',
      'entity_id',
    ]);
    $result = $query->execute();
    return $result;
  }

  /**
   * Selects the digests and their status.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   Digest related data for the digest list.
   */
  private function selectDigests() {
    $query = $this->database->select('civimail_digest', 'cd');
    $query->fields('cd', ['id', 'status', 'timestamp']);
    // leftJoin as groups couldn't be defined yet if
    // the digest status is not 'sent'.
    $query->leftJoin(
      'civimail_digest__group',
      'cg',
      'cg.digest_id = cd.id');
    $query->fields('cg', [
      'civicrm_group_id',
    ]);
    $query->orderBy('cd.id', 'DESC');
    $result = $query->execute();
    return $result;
  }

  /**
   * Retrieves the digest content that has been prepared.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return array
   *   List of entity ids groupe by entity type id.
   */
  private function getDigestContent($digest_id) {
    $result = [];
    $queryResult = $this->selectDigestEntities($digest_id);
    foreach ($queryResult as $row) {
      if (empty($result[$row->entity_type_id])) {
        $result[$row->entity_type_id] = [];
      }
      $result[$row->entity_type_id][] = $row->entity_id;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function previewDigest() {
    $digestContent = $this->prepareDigestContent();
    $digest = [];
    if (!empty($digestContent['entities'])) {
      $entities = $this->getDigestEntities($digestContent['entities']);
      $digest = $this->buildDigest($entities);
    }
    return $this->getDigestAsResponse($digest);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDigest() {
    $result = NULL;
    $digestContent = $this->prepareDigestContent();
    if (!empty($digestContent['entities'])) {
      // Get a new digest id.
      $digestId = $this->createDigest();
      if (NULL !== $digestId) {
        // Store each mailing id with a reference to the digest id.
        try {
          // @todo insert all values in one query.
          foreach ($digestContent['mailings'] as $mailingId) {
            $fields = [
              'digest_id' => $digestId,
              'civicrm_mailing_id' => $mailingId,
              'timestamp' => \Drupal::time()->getRequestTime(),
            ];
            $this->database->insert('civimail_digest__mailing')
              ->fields($fields)
              ->execute();
          }
          // Set then the digest status to prepared.
          $this->database->update('civimail_digest')
            ->fields(['status' => CiviMailDigestInterface::STATUS_PREPARED])
            ->condition('id', $digestId)
            ->execute();
          $result = $digestId;
        }
        catch (\Exception $exception) {
          \Drupal::logger('civimail_digest')->error($exception->getMessage());
          \Drupal::messenger()->addError($exception->getMessage());
        }
      }
    }

    if ($result) {
      \Drupal::messenger()->addStatus(t('The digest @id has been prepared.', ['@id' => $digestId]));
    }
    else {
      \Drupal::messenger()->addError(t('An error occured while preparing the digest.'));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function viewDigest($digest_id) {
    $entityIds = $this->getDigestContent($digest_id);
    $digest = [];
    if (!empty($entityIds)) {
      $entities = $this->getDigestEntities($entityIds);
      $digest = $this->buildDigest($entities, $digest_id);
    }
    return $this->getDigestAsResponse($digest);
  }

  /**
   * Renders a digest and wrap it into a Response.
   *
   * @param array $digest
   *   Digest render array.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Digest response.
   */
  private function getDigestAsResponse(array $digest) {
    // @todo dependency injection
    /** @var \Drupal\Core\Render\Renderer $renderer */
    $renderer = \Drupal::service('renderer');
    if (!empty($digest)) {
      $output = $renderer->renderRoot($digest);
    }
    else {
      $noResults = [
        '#markup' => t('No content for the digest.'),
      ];
      $output = $renderer->renderRoot($noResults);
    }
    return new Response($output);
  }

  /**
   * Loads the entities and prepares the view modes for the digest content.
   *
   * @param array $content
   *   List of entities grouped by entity types.
   *
   * @return array
   *   List of rendered entities.
   */
  private function getDigestEntities(array $content) {
    $result = [];
    // @todo assert defined
    $digestViewMode = $this->digestConfig->get('view_mode');
    foreach ($content as $entityTypeId => $entityIds) {
      try {
        $entities = $this->entityTypeManager->getStorage($entityTypeId)->loadMultiple($entityIds);
        foreach ($entities as $entity) {
          $viewBuilder = $this->entityTypeManager->getViewBuilder($entityTypeId);
          $view = $viewBuilder->view($entity, $digestViewMode);
          $renderedView = \Drupal::service('renderer')->renderRoot($view);
          $result[] = $renderedView;
        }
      }
      catch (InvalidPluginDefinitionException $exception) {
        \Drupal::messenger()->addError($exception->getMessage());
      }
    }
    return $result;
  }

  /**
   * Builds the rendered array for a digest.
   *
   * @param array $entities
   *   List of rendered entities.
   * @param int $digest_id
   *   Digest id.
   *
   * @return array
   *   Render array of the digest.
   */
  private function buildDigest(array $entities, $digest_id = NULL) {
    // @todo add text
    // @todo refactor CiviMail service
    $currentDigestId = $digest_id;
    if (is_null($digest_id)) {
      // @todo get it by incrementing the last digest id.
      $currentDigestId = 0;
    }
    return [
      '#theme' => 'civimail_digest_html',
      '#entities' => $entities,
      '#digest_title' => $this->getDigestTitle(),
      '#digest_id' => $currentDigestId,
      // Use CiviCRM token.
      '#civicrm_unsubscribe_url' => '{action.unsubscribeUrl}',
      // Allows template overrides to load assets provided by the current theme
      // with {{ base_path ~ directory }}.
      '#base_path' => \Drupal::request()->getSchemeAndHttpHost() . '/',
      '#absolute_link' => $this->getAbsoluteDigestLink($currentDigestId),
      '#absolute_url' => $this->getAbsoluteDigestUrl($currentDigestId),
    ];
  }

  /**
   * Returns the digest title.
   *
   * @return string
   *   Digest title.
   */
  private function getDigestTitle() {
    return $this->digestConfig->get('digest_title');
  }

  /**
   * Returns the absolute digest url.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return \Drupal\Core\Url
   *   Digest url.
   */
  private function getAbsoluteDigestUrl($digest_id) {
    return Url::fromRoute('civimail_digest.view', ['digest_id' => $digest_id])->setAbsolute();
  }

  /**
   * Returns an absolute link to a digest view.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return array|\Drupal\Core\Link
   *   Absolute link to the digest.
   */
  private function getAbsoluteDigestLink($digest_id) {
    $link = Link::fromTextAndUrl(t('View it online'), $this->getAbsoluteDigestUrl($digest_id));
    $link = $link->toRenderable();
    return $link;
  }

  /**
   * Creates a new digest id in the digest table and returns it.
   *
   * @return int
   *   The digest id.
   */
  private function createDigest() {
    $result = NULL;
    try {
      $fields = [
        'status' => CiviMailDigestInterface::STATUS_CREATED,
        'timestamp' => \Drupal::time()->getRequestTime(),
      ];
      // Returns the serial id of the digest.
      $result = $this->database->insert('civimail_digest')
        ->fields($fields)
        ->execute();
    }
    catch (\Exception $exception) {
      \Drupal::logger('civimail_digest')->error($exception->getMessage());
      \Drupal::messenger()->addError($exception->getMessage());
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getDigests() {
    $result = [];
    $queryResult = $this->selectDigests();
    /** @var \Drupal\civicrm_tools\CiviCrmGroupInterface $civiCrmGroupTools */
    $civiCrmGroupTools = \Drupal::service('civicrm_tools.group');
    foreach ($queryResult as $row) {
      $result[$row->id] = [
        'id' => (int) $row->id,
        'status_id' => (int) $row->status,
        'status_label' => $this->getDigestStatusLabel($row->status),
        'timestamp' => (int) $row->timestamp,
      ];
      // Aggregate groups.
      if (NULL != $row->civicrm_group_id) {
        if (empty($result[$row->id]['groups'])) {
          $result[$row->id]['groups'] = [];
        }
        $group = $civiCrmGroupTools->getGroup($row->civicrm_group_id);
        $result[$row->id]['groups'][] = $group['name'];
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function notifyValidators() {
    // TODO: Implement notifyValidators() method.
  }

  /**
   * {@inheritdoc}
   */
  public function sendTestDigest($digest_id) {
    // TODO: Implement sendTestDigest() method.
  }

  /**
   * {@inheritdoc}
   */
  public function sendDigest($digest_id) {
    // Check if the digest feature is active.
    if (!$this->isActive()) {
      // @todo add hints for configuration.
      \Drupal::messenger()->addError(t('CiviMail digest is currently inactive.'));
      return;
    }
    // Check the digest status before sending.
    if (!$this->canSend($digest_id)) {
      \Drupal::messenger()->addError(t('This digest cannot be sent.'));
      return;
    }

    // TODO: Implement sendDigest() method.
    /** @var \Drupal\civimail\CiviMailInterface $civiMail */
    $civiMail = \Drupal::service('civimail');
    \Drupal::messenger()->addWarning(t('Send operation not implemented yet.'));

    // If success set the civimail id in the civimail digest table
    // and set the status to 2.
  }

  /**
   * Checks if a digest can be sent.
   *
   * Verifies if the digest has content and if it has not been sent yet.
   * Only the prepared and failed status are allowing a send operation.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return bool
   *   Can the digest be sent.
   */
  private function canSend($digest_id) {
    $query = $this->database->select('civimail_digest', 'cd');
    $query->condition('cd.id', $digest_id);
    $query->fields('cd', ['status']);
    $status = (int) $query->execute()->fetchField();
    return $status === CiviMailDigestInterface::STATUS_PREPARED ||  $status === CiviMailDigestInterface::STATUS_FAILED;
  }

  /**
   * {@inheritdoc}
   */
  public function getDigestStatusLabel($status_id) {
    $result = t('Unknown status');
    switch ($status_id) {
      case CiviMailDigestInterface::STATUS_CREATED:
        $result = t('Created');
        break;

      case CiviMailDigestInterface::STATUS_PREPARED:
        $result = t('Prepared');
        break;

      case CiviMailDigestInterface::STATUS_SENT:
        $result = t('Sent');
        break;

      case CiviMailDigestInterface::STATUS_FAILED:
        $result = t('Failed');
        break;
    }
    return $result;
  }

}
