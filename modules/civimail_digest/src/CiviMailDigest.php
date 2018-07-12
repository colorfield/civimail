<?php

namespace Drupal\civimail_digest;

use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\civicrm_tools\CiviCrmApiInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
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
   * Constructs a new CiviMailDigest object.
   */
  public function __construct(Connection $database, CiviCrmApiInterface $civicrm_tools_api, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->civicrmToolsApi = $civicrm_tools_api;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Get the content entities keys that are candidates for a digest.
   *
   * These candidates are evaluated from CiviMail mailings that were
   * previously sent and the configured limitations.
   *
   * @return array
   *   Content entities result from the {civimail_entity_mailing} table.
   */
  private function getDigestContent() {
    $result = [];
    $config = $this->configFactory->get('civimail_digest.settings');
    if ($config->get('is_active')) {
      // @todo assert all the values and send to configuration if not valid.
      $quantityLimit = $config->get('quantity_limit');
      $language = $config->get('language');
      $includeUpdate = $config->get('include_update');

      $configuredBundles = $config->get('bundles');
      $bundles = [];
      // Get rid of the keys, take only values if they are the same.
      foreach ($configuredBundles as $key => $configuredBundle) {
        if ($configuredBundle === $key) {
          $bundles[] = $configuredBundle;
        }
      }

      $maxDays = $config->get('age_in_days');
      // @todo get from system settings
      $timeZone = new \DateTimeZone('Europe/Brussels');
      $contentAge = new \DateTime('now -' . $maxDays . ' day', $timeZone);

      // Get all the CiviMail mailings for entities that are matching
      // the configuration limitations.
      $civiMailQuery = $this->database->select('civimail_entity_mailing', 'cem')
        ->fields('cem', [
          'entity_id',
          'entity_bundle',
          'langcode',
          'civicrm_mailing_id',
          'timestamp',
        ]
      );
      $civiMailQuery->condition('cem.timestamp', $contentAge->getTimestamp(), '>');
      // @todo extend to other entity types
      $civiMailQuery->condition('cem.entity_type_id', 'node');
      $civiMailQuery->condition('cem.entity_bundle', $bundles, 'IN');
      $civiMailQuery->condition('cem.langcode', $language);
      $civiMailQuery->orderBy('cem.timestamp', 'DESC');
      $civiMailQuery->range(0, $quantityLimit);
      $civiMailResult = $civiMailQuery->execute()->fetchAll();

      // Store a reference of all the mailings for candidate entities.
      // @todo extend to other entity types
      $candidateEntities = [
        'node' => [],
      ];
      foreach ($civiMailResult as $row) {
        if (empty($candidateEntities['node'][$row->entity_id])) {
          $candidateEntities['node'][$row->entity_id] = ['mailing' => [$row->civicrm_mailing_id]];
        }
        else {
          $candidateEntities['node'][$row->entity_id]['mailing'][] = $row->civicrm_mailing_id;
        }
      }

      // @todo compare with what was sent previously

      if ($includeUpdate) {
        // @todo include update case
      }

    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function hasDigestContent() {
    return !empty($this->getDigestContent());
  }

  /**
   * {@inheritdoc}
   */
  public function previewDigest() {
    $content = $this->getDigestContent();
    if (!empty($content)) {
      $entities = $this->getDigestEntities($content);
      $digest = $this->buildDigest($entities);
    }
    // @todo cacheable response.
    return new Response();
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
    return $result;
  }

  /**
   * Builds the rendered array for a digest.
   *
   * @param array $entities
   *   List of rendered entities.
   *
   * @return array
   *   Render array of the digest.
   */
  private function buildDigest(array $entities) {
    $result = [];
    return $result;
  }

  /**
   * Creates a new digest id in the digest table and returns it.
   *
   * @return int
   *   The digest id.
   */
  public function createDigest() {
    // @todo implement
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDigest() {
    $content = $this->getDigestContent();
    if (!empty($content)) {
      $digestId = $this->createDigest();
      // Create digest
      // get its id.
    }
    // Get the digest content
    // store each entity to be sent.
    // TODO: Implement prepareDigest() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getDigests() {
    $config = $this->configFactory->get('civimail_digest.settings');
    // TODO: Implement getDigests() method.
  }

  /**
   * {@inheritdoc}
   */
  public function viewDigest($digest_id) {
    // TODO: Implement previewDigest() method.
    // @todo cacheable response.
    return new Response();
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
  }

}
