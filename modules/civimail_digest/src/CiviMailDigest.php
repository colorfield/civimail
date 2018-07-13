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
    return !empty($this->prepareDigestContent());
  }

  /**
   * Get the content entity ids and CiviMail mailing ids for a digest.
   *
   * These candidates are evaluated from CiviMail mailings that were
   * previously sent and from the configured limitations.
   *
   * @return array
   *   Content entity ids grouped by entity type ids.
   */
  private function prepareDigestContent() {
    $result = [];
    if ($this->isActive()) {
      $civiMailMailings = $this->selectDigestMailings();
      $result = [];
      foreach ($civiMailMailings as $row) {
        if (empty($result[$row->entity_type_id])) {
          $result[$row->entity_type_id] = [];
        }
        $result[$row->entity_type_id][] = $row->entity_id;
      }
    }
    return $result;
  }

  /**
   * Selects the CiviMail mailings to be included in a digest.
   *
   * @return array
   *   List of CiviMail mailing ids.
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
   * Retrieves the digest content that has been prepared.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return array
   *   List of entity ids for a digest.
   */
  private function getDigestContent($digest_id) {
    $result = [];
    // @todo implement.
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function previewDigest() {
    $entityIds = $this->prepareDigestContent();
    $digest = [];
    if (!empty($entityIds)) {
      $entities = $this->getDigestEntities($entityIds);
      $digest = $this->buildDigest($entities);
    }
    return $this->getDigestAsResponse($digest);
  }

  /**
   * {@inheritdoc}
   */
  public function viewDigest($digest_id) {
    $entityIds = $this->getDigestContent($digest_id);
    $digest = [];
    if (!empty($entityIds)) {
      $entities = $this->getDigestEntities($entityIds);
      $digest = $this->buildDigest($entities);
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
  public function createDigest() {
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
  public function prepareDigest() {
    $content = $this->prepareDigestContent();
    if (!empty($content)) {
      $digestId = $this->createDigest();
      if (NULL !== $digestId) {
        // Store each mailing id with a digest reference.
        // @todo implement

        // Set then the digest status to 1.
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDigests() {
    // TODO: Implement getDigests() method.
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
    // TODO: Implement sendDigest() method.
    if ($this->isActive()) {
      /** @var \Drupal\civimail\CiviMailInterface $civiMail */
      $civiMail = \Drupal::service('civimail');
      // If success set the civimail id in the civimail digest table
      // and set the status to 2.
    }
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
