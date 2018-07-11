<?php

namespace Drupal\civimail_digest;

/**
 * Interface CiviMailDigestInterface.
 */
interface CiviMailDigestInterface {

  /**
   * Checks if the digest to be prepared has content.
   *
   * @return bool
   *   The content status for the digest.
   */
  public function hasDigestContent();

  /**
   * Collects the nodes that must be part of the digest.
   *
   * As a side effect, it assigns a digest id to each content entity
   * based on the limitations.
   *
   * @return int
   *   Digest id.
   */
  public function prepareDigest();

  /**
   * Gets the digests with their status.
   *
   * @return array
   *   List of digests.
   */
  public function getDigests();

  /**
   * Previews the digest.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Digest preview.
   */
  public function previewDigest($digest_id);

  /**
   * Notifies the validator groups if a new digest is ready.
   *
   * @return bool
   *   Status of the notification.
   */
  public function notifyValidators();

  /**
   * Sends a test digest to the configured test groups.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return bool
   *   Digest send status.
   */
  public function sendTestDigest($digest_id);

  /**
   * Sends the digest to the configured groups.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return bool
   *   Digest send status.
   */
  public function sendDigest($digest_id);

}
