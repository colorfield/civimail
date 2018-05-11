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
   *   The content entity that is the subject of the mailing.
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
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity that is the subject of the mailing.
   *
   * @return bool
   *   The mailing status.
   */
  public function sendMailing(array $params, ContentEntityInterface $entity);

  /**
   * Sends a Drupal entity to a test address.
   *
   * @param int $from_cid
   *   The CiviCRM contact id for the mailing sender.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity that is the subject of the mailing.
   * @param string $to_mail
   *   The email address that will receive the test.
   *
   * @return bool
   *   The test status.
   */
  public function sendTestMail($from_cid, ContentEntityInterface $entity, $to_mail);

  /**
   * Fetches the mailing history for an entity.
   *
   * Aggregates the results of the civimail_entity_mailing table
   * and the CiviCRM Mailing API.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity that is the subject of the mailing.
   *
   * @return array
   *   List of CiviCRM mailing history for this entity.
   */
  public function getEntityMailingHistory(ContentEntityInterface $entity);

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
   * Prepares a list of CiviCRM Groups for select form element.
   *
   * @param array $filter
   *   Group filter.
   *
   * @return array
   *   Map of group labels indexed by group id.
   */
  public function getGroupSelectOptions(array $filter = []);

  /**
   * Prepares a list of CiviCRM Contacts for select form element.
   *
   * @param array $filter
   *   Contact filter.
   *
   * @return array
   *   Map of contact labels indexed by contact id.
   */
  public function getContactSelectOptions(array $filter);

  /**
   * Indicates if CiviCRM requirements are fulfilled.
   *
   * @return bool
   *   The status of the requirements.
   */
  public function hasCiviCrmRequirements();

}
