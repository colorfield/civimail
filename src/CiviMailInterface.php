<?php

namespace Drupal\civimail;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface CiviMailInterface.
 */
interface CiviMailInterface {

  /**
   * Prepares the CiviCRM mailing parameters for a Drupal content entity.
   *
   * @param int $from_cid
   *   The CiviCRM contact id for the mailing sender.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param array $groups
   *   List of CiviCRM group id's.
   *
   * @return array
   *   Parameter to be passed to the CiviCRM Mailing creation.
   */
  public function getEntityMailingParams($from_cid, ContentEntityInterface $entity, array $groups);

  /**
   * Schedules and sends a CiviCRM mailing.
   *
   * @param array $params
   *   The mailing parameters.
   *
   * @return bool
   *   The mailing status.
   */
  public function sendMailing(array $params);

  /**
   * Sends a Drupal entity to a test address.
   *
   * @param int $from_cid
   *   The CiviCRM contact id for the mailing sender.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $to_mail
   *   The email address that will receive the test.
   *
   * @return bool
   *   The test status.
   */
  public function sendTestMail($from_cid, ContentEntityInterface $entity, $to_mail);

  /**
   * Fetches a single contact straight from the CiviCRM API.
   *
   * @param array $filter
   *   Optional list of filters.
   *
   * @return array
   *   The contact details.
   */
  public function getContact(array $filter);

  /**
   * Returns the entity for the current route.
   *
   * @param string $entity_type_id
   *   The entity type id for the route match.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity that is the subject of the CiviCRM mailing.
   */
  public function getEntityFromRoute($entity_type_id);

  /**
   * Prepares a list of Drupal CiviCRM Group entities.
   *
   * @return array
   *   List of labels indexed by group id.
   */
  public function getGroupEntitiesLabel();

  /**
   * Prepares a list of Drupal CiviCRM Contact entities.
   *
   * @return array
   *   List of labels indexed by group id.
   */
  public function getContactEntitiesLabel();

  /**
   * Returns a single Drupal CiviCRM Contact entity.
   *
   * @param int $cid
   *   The CiviCRM Contact id.
   *
   * @return \Drupal\civicrm_entity\Entity\CivicrmEntity
   *   CiviCRM Contact Drupal entity
   */
  public function getContactEntity($cid);

}
