<?php

namespace Drupal\civimail\Controller;

use Drupal\civimail\CiviMailInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class MailPreviewController.
 */
class MailPreviewController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\civimail\CiviMailInterface definition.
   *
   * @var \Drupal\civimail\CiviMailInterface
   */
  protected $civiMail;

  /**
   * MailPreviewController constructor.
   *
   * @param \Drupal\civimail\CiviMailInterface $civi_mail
   *   The CiviMail service.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager definition.
   */
  public function __construct(CiviMailInterface $civi_mail, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->civiMail = $civi_mail;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('civimail'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Loads an entity.
   *
   * @param string $entity_type
   *   Entity type.
   * @param int $entity_id
   *   Entity id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Entity.
   */
  private function loadEntity($entity_type, $entity_id) {
    $result = NULL;
    try {
      $result = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    }
    catch (InvalidPluginDefinitionException $exception) {
      \Drupal::messenger()->addError($exception->getMessage());
    }
    return $result;
  }

  /**
   * Previews a mail for an entity.
   *
   * @return array
   *   Return render array of the mail preview
   */
  public function preview($entity_type, $entity_id) {
    $build = [];
    $entity = $this->loadEntity($entity_type, $entity_id);
    if ($entity instanceof ContentEntityInterface) {
      $build = $this->civiMail->getMailingTemplateHtml($entity);
      $build = $this->civiMail->removeCiviCrmTokens($build);
    }
    else {
      $build = [
        '#type' => 'markup',
        '#markup' => $this->t('No content entity found for the following parameters: @entity_type, @entity_id.',
          [
            '@entity_type' => $entity_type,
            '@entity_id' => $entity_id,
          ]),
      ];
    }
    return $build;
  }

}
