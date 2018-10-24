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
    $this->civiMailDigest = $civimail_digest;
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
   * This needs to be compared with the last sent or prepared digest
   * to see if the cron is still executed withing the current week.
   *
   * @return bool
   *   Is the digest time condition met.
   */
  private function isDigestTime() {
    $result = FALSE;
    // @todo review DrupalDateTime and timezone
    // @see https://www.drupal.org/node/1834108
    $currentDateTime = new \DateTime();
    // Week day.
    $currentDay = $currentDateTime->format('w');
    $configuredDay = $this->digestConfig->get('scheduler_week_day');
    // Hour without the 0 padding.
    $currentHour = $currentDateTime->format('G');
    $configuredHour = $this->digestConfig->get('scheduler_hour');

    // Compare first if the a digest as already been sent or prepared
    // within the current week.
    // Depending on the scheduler type,
    // we need to get the last sent or last prepared digest.
    $lastDigestTimeStamp = $this->civiMailDigest->getLastDigestTimeStamp();
    if (!empty($lastDigestTimeStamp)) {
      $lastSentDigestDateTime = new \DateTime();
      $lastSentDigestDateTime->setTimestamp($lastDigestTimeStamp);
      // ISO-8601 week.
      $lastSentDigestWeek = $lastSentDigestDateTime->format('W');
      $currentWeek = $currentDateTime->format('W');
      if ((int) $lastSentDigestWeek === (int) $currentWeek) {
        // Already sent this week, quit by leaving the result to FALSE.
        return $result;
      }
    }

    // Compare then the current week day and time from the configuration.
    if (((int) $currentDay >= (int) $configuredDay) && ((int) $currentHour >= (int) $configuredHour)) {
      $result = TRUE;
      return $result;
    }

    // Otherwise, just leave the initial result to FALSE.
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function executeSchedulerOperation() {
    $result = FALSE;
    if ($this->canPrepareDigest()) {
      $digestId = $this->civiMailDigest->prepareDigest();
      // Send the digest to groups or send a notification to validators
      // depending on the configuration.
      $schedulerType = $this->digestConfig->get('scheduler_type');
      // @todo try catch / throw exception for notifyValidators and sendDigest
      // so we can assert that the result is TRUE if no exception risen.
      switch ($schedulerType) {
        case CiviMailDigestSchedulerInterface::SCHEDULER_NOTIFY:
          $this->notifyValidators($digestId);
          break;

        case CiviMailDigestSchedulerInterface::SCHEDULER_SEND:
          $this->civiMailDigest->sendDigest($digestId);
          break;
      }
      $result = TRUE;
    }
    else {
      // If the digest does not need to be prepared
      // it is regarded as a successful scheduler execution.
      $result = TRUE;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function notifyValidators($digest_id) {
    // TODO: Implement notifyValidators() method.
  }

}
