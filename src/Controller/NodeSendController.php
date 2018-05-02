<?php

namespace Drupal\civimail\Controller;

use Drupal\civimail\Form\EntitySendForm;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class NodeController.
 */
class NodeSendController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Datetime\DateFormatter definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Drupal\Core\Entity\EntityStorageInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Constructs a new NodeMessageController object.
   */
  public function __construct(EntityTypeManager $entity_type_manager, DateFormatter $date_formatter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * Builds a table header.
   *
   * @return array
   *   Header.
   */
  private function buildHeader() {
    $header = [
      'mailing_id' => [
        'data' => $this->t('Mailing Id'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'subject' => [
        'data' => $this->t('Subject'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'from' => [
        'data' => $this->t('From'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'groups' => [
        'data' => $this->t('Groups'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'created' => [
        'data' => $this->t('Created'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];
    return $header;
  }

  /**
   * Builds a table row.
   *
   * @param array $mailing
   *   CiviCRM mailing details.
   *
   * @return array
   *   Array mapped to header.
   */
  private function buildRow(array $mailing) {
    $dateTime = DrupalDateTime::createFromFormat(
      'Y-m-d H:i:s',
      $mailing['mailing']['created_date']
    );
    $timeStamp = $dateTime->getTimestamp();
    // @todo waiting to see how evolves the CiviCRM entity to introduce filters in CiviMailInterface.
    $civicrmStorage = \Drupal::entityTypeManager()->getStorage('civicrm_group');
    $groups = $civicrmStorage->loadByProperties(['id' => 'civicrm_group']);
    $groupLabels = [];
    foreach ($groups as $groupId => $group) {
      if (in_array($groupId, $mailing['groups'])) {
        $groupLabels[] = $group->get('title')->value;
      }
    }
    return [
      'mailing_id' => $mailing['mailing']['id'],
      'subject' => $mailing['mailing']['subject'],
    // @todo link with from_name
      'from' => $mailing['mailing']['from_email'],
      'groups' => implode(',', $groupLabels),
      'created' => $this->dateFormatter->format($timeStamp, 'short'),
    ];
  }

  /**
   * Builds the mailing listing as a render array for table.html.twig.
   *
   * @param array $mailing_history
   *   List of CiviCRM mailings.
   *
   * @return array
   *   Table render array.
   */
  public function renderTable(array $mailing_history) {
    // @todo composition with entity list builder.
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->t('CiviCRM mailings'),
      '#rows' => [],
      // @todo empty should contain a call to action.
      '#empty' => $this->t('No mailing was sent yet for this content.'),
      // @todo
      // '#cache' => [
      // ],
    ];
    foreach ($mailing_history as $mailing) {
      if ($row = $this->buildRow($mailing)) {
        $build['table']['#rows'][] = $row;
      }
    }

    // @todo pagination
    // $build['pager'] = [
    // '#type' => 'pager',
    // ];
    return $build;
  }

  /**
   * Gets sent mailings per group and provides group notify feature.
   *
   * @param int $node
   *   Node entity id.
   *
   * @return array
   *   Render array of sent messages and notify groups form.
   */
  public function mailing($node) {
    $build = [];
    try {
      /** @var \Drupal\node\Entity\Node $nodeEntity */
      $nodeEntity = $this->entityTypeManager->getStorage('node')->load($node);
      if (!$nodeEntity->isPublished()) {
        $this->messenger()->addWarning($this->t('This content is currently unpublished'));
      }
      $civiMail = \Drupal::service('civimail');
      $mailingHistory = $civiMail->getEntityMailingHistory($nodeEntity);
      // @todo set render keys
      $build = [
        '#theme' => 'entity_mailing',
        '#entity' => $nodeEntity,
        '#entity_send_form' => \Drupal::formBuilder()->getForm(EntitySendForm::class, $nodeEntity->bundle()),
        '#sent_mailings' => $this->renderTable($mailingHistory),
      ];
    }
    catch (InvalidPluginDefinitionException $exception) {
      $this->messenger->addError($exception);
    }
    return $build;
  }

}
