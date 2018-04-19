<?php

namespace Drupal\civimail\Controller;

use Drupal\civimail\Form\EntitySendForm;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class NodeController.
 */
class NodeController extends ControllerBase {

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
   * Loads mailings that have a node for reference.
   *
   * @param int $node_id
   *   The node entity id.
   *
   * @return array
   *   List of CiviCRM Mailings.
   */
  private function getMailings($node_id) {
    $result = [];
    // @todo implement
    return $result;
  }

  /**
   * Builds a table header.
   *
   * @return array
   *   Header.
   */
  private function buildHeader() {
    $header = [
      'display' => [
        'data' => $this->t('Mail subject'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'groups' => [
        'data' => $this->t('Groups'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'from' => [
        'data' => $this->t('From'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'author' => [
        'data' => $this->t('Author'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'created' => [
        'data' => $this->t('Created'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    // $this->storage->getEntityType()->isTranslatable() ?
    if (\Drupal::languageManager()->isMultilingual()) {
      $header['language_name'] = [
        'data' => $this->t('Language'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ];
    }
    return $header;
  }

  /**
   * Builds a table row.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity for this row.
   *
   * @return array
   *   Array mapped to header.
   */
  private function buildRow(EntityInterface $entity) {
    return [
      'display' => '@todo',
      'from' => '@todo',
      'groups' => '@todo',
      'author' => '@todo',
      'created' => '@todo',
      // 'created' => $this->dateFormatter->format
      // ($entity->getCreatedTime(), 'short'),.
    ];
  }

  /**
   * Builds the mailing listing as a render array for table.html.twig.
   *
   * @param array $mailings
   *   List of CiviCRM mailings.
   *
   * @return array
   *   Table render array.
   */
  public function renderTable(array $mailings) {
    // @todo composition with entity list builder.
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->t('CiviCRM mailings'),
      '#rows' => [],
      '#empty' => $this->t('Mailings history not implemented yet.'),
      // @todo empty should contain a call to action.
      // '#empty' => $this->t('There is no mailings sent yet.'),
      // @todo
      // '#cache' => [
      // ],
    ];
    foreach ($mailings as $mailing) {
      if ($row = $this->buildRow($mailing)) {
        $build['table']['#rows'][] = $row;
      }
    }

    // @todo pagination
    // Only add the pager if a limit is specified.
    // if ($this->limit) {
    // $build['pager'] = [
    // '#type' => 'pager',
    // ];
    // }
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
    // @todo check if this node is published first.
    $build = [];
    try {
      $nodeEntity = $this->entityTypeManager->getStorage('node')->load($node);
      $mailings = $this->getMailings($node);
      // @todo set render keys
      $build = [
        '#theme' => 'entity_mailing',
        '#entity' => $nodeEntity,
        '#entity_send_form' => \Drupal::formBuilder()->getForm(EntitySendForm::class),
        '#sent_mailings' => $this->renderTable($mailings),
      ];
    }
    catch (InvalidPluginDefinitionException $exception) {
      $this->messenger->addError($exception);
    }
    return $build;
  }

}
