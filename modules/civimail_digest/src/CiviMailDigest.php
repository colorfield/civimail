<?php

namespace Drupal\civimail_digest;

use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\civicrm_tools\CiviCrmApiInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

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
   * Get the content entities that are candidates for a digest.
   *
   * These candidates are evaluated from CiviMail mailings that were
   * previously sent and the configured limitations.
   *
   * @return array
   */
  private function getDigestContent() {
    $result = [];
    $config = $this->configFactory->get('civimail_digest.settings');
    if ($config->get('is_active')) {
      $maxDays = $config->get('age_in_days');
      $contentLimit = $config->get('entity_limit');
      $bundles = $config->get('bundles');
      $includeUpdate = $config->get('include_update');
    }
    // Table civimail_entity_mailing.
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
  public function prepareDigest() {
    // Create digest
    // get its id
    // get the digest content
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
  public function previewDigest($digest_id) {
    // TODO: Implement previewDigest() method.
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
