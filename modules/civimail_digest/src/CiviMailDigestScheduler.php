<?php

namespace Drupal\civimail_digest;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class CiviMailDigestScheduler.
 */
class CiviMailDigestScheduler implements CiviMailDigestSchedulerInterface {

  /**
   * Drupal\civimail_digest\CiviMailDigestInterface definition.
   *
   * @var \Drupal\civimail_digest\CiviMailDigestInterface
   */
  protected $civiMailDigest;

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
  public function __construct(CiviMailDigestInterface $civimail_digest, ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->digestConfig = $this->configFactory->get('civimail_digest.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function isSchedulerActive() {
    return (bool) $this->digestConfig->get('is_scheduler_active');
  }

  /**
   * {@inheritdoc}
   */
  public function canPrepareDigest() {
    $result = FALSE;
    if (!$this->isSchedulerActive()) {
      return $result;
    }
    if (!$this->isDigestTime()) {
      return $result;
    }
    if (!$this->civiMailDigest->hasNextDigestContent()) {
      return $result;
    }
    $result = TRUE;
    return $result;
  }

  /**
   * Compare the current time to the scheduler configured time.
   *
   * @return bool
   *   Is the digest time condition met.
   */
  private function isDigestTime() {
    // @todo implement
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeSchedulerOperation() {
    if ($this->canPrepareDigest()) {
      // @todo prepare digest
      // @todo send the digest to groups or send a notification to validators
    }
  }

}
