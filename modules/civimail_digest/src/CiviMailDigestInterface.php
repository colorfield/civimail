<?php

namespace Drupal\civimail_digest;

/**
 * Interface CiviMailDigestInterface.
 */
interface CiviMailDigestInterface {

  /**
   * Checks if the digest has content ready to be sent.
   *
   * @return bool
   *   The content status for the digest.
   */
  public function hasDigestContent();

  /**
   * Collects the nodes that must be part of the digest.
   *
   * @return int
   *   Digest id.
   */
  public function prepareDigest();

  /**
   * Sends the digest.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return bool
   *   Digest send status.
   */
  public function sendDigest($digest_id);

}
