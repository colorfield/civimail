<?php

namespace Drupal\civimail_digest;

/**
 * Interface CiviMailDigestSchedulerInterface.
 */
interface CiviMailDigestSchedulerInterface {

  /**
   * Checks if the digest scheduler is configured as active.
   *
   * @return bool
   *   The scheduler configuration status.
   */
  public function isSchedulerActive();

  /**
   * Checks if the digest can be prepared.
   *
   * Implies the following conditions:
   * - the scheduler is active
   * - the digest has enough content
   * - configured digest time is greater or equal than the current time.
   *
   * @return bool
   *   The scheduler configuration status.
   */
  public function canPrepareDigest();

  /**
   * Executes the configured operation for the scheduler.
   *
   * Prepare the digest then send the digest to groups
   * or send a notification to validators
   * depending on the configuration.
   * Pre-condition: canPrepareDigest() evaluated to TRUE.
   *
   * @return bool
   *   The status of the operation.
   */
  public function executeSchedulerOperation();

}
