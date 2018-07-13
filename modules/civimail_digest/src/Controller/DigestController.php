<?php

namespace Drupal\civimail_digest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\civimail_digest\CiviMailDigestInterface;
use Drupal\Core\Datetime\DateFormatter;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class DigestListController.
 */
class DigestController extends ControllerBase {

  /**
   * Drupal\civimail_digest\CiviMailDigestInterface definition.
   *
   * @var \Drupal\civimail_digest\CiviMailDigestInterface
   */
  protected $civimailDigest;

  /**
   * Drupal\Core\Datetime\DateFormatter definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Constructs a new DigestListController object.
   */
  public function __construct(CiviMailDigestInterface $civimail_digest, DateFormatter $date_formatter) {
    $this->civimailDigest = $civimail_digest;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('civimail_digest'),
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
      'digest_id' => [
        'data' => $this->t('Digest Id'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'status' => [
        'data' => $this->t('Status'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'prepared' => [
        'data' => $this->t('Prepared on'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'view' => [
        'data' => $this->t('View'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'send' => [
        'data' => $this->t('Send'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'groups' => [
        'data' => $this->t('Groups'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
    ];
    return $header;
  }

  /**
   * Builds a table row.
   *
   * @return array
   *   List of rows mapped to header.
   */
  private function buildRows() {
    $result = [];
    // @todo get digests and iterate
    $row = [
      'digest_id' => '',
    // prepared, failed to be sent, sent.
      'status' => '',
    // Preparation date.
      'prepared' => '',
    // Preview or view.
      'view' => '',
    // Send action or sent date.
      'send' => '',
    // CiviCRM groups that received the digest.
      'groups' => '',
    ];
    return $result;
  }

  /**
   * Builds the digest list as a table.
   *
   * @return array
   *   Render array of the table.
   */
  private function buildDigestTable() {
    return [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->t('CiviMail digests'),
      '#rows' => $this->buildRows(),
      '#empty' => $this->t('No digests were prepared yet.'),
    ];
  }

  /**
   * Builds action links to prepare the digest and configure it.
   *
   * @return array
   *   Render array as a list of links.
   */
  private function buildActionLinks() {
    // Set destination back to the list for configuration.
    $digestListUrl = Url::fromRoute('civimail_digest.digest_list');
    $configureUrl = Url::fromRoute('civimail_digest.settings', [], [
      'query' => ['destination' => $digestListUrl->toString()],
      'absolute' => TRUE,
    ]);

    $previewUrl = ($this->civimailDigest->isActive()) ? Url::fromRoute('civimail_digest.preview') : '';
    $prepareUrl = ($this->civimailDigest->isActive()) ? Url::fromRoute('civimail_digest.prepare') : '';

    $build = [
      '#theme' => 'civimail_digest_actions',
      '#configure_url' => $configureUrl,
      '#preview_url' => $previewUrl,
      '#prepare_url' => $prepareUrl,
    ];

    return $build;
  }

  /**
   * Previews the digest to be prepared.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Digest preview.
   */
  public function preview() {
    return $this->civimailDigest->previewDigest();
  }

  /**
   * Prepares a digest and redirects to the list.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection the the digest list.
   */
  public function prepare() {
    \Drupal::messenger()->addStatus($this->t('Checking for content'));
    $this->civimailDigest->prepareDigest();

    $url = Url::fromRoute('civimail_digest.digest_list');
    return new RedirectResponse($url->toString());
  }

  /**
   * Views a digest that has already been prepared.
   *
   * @param int $digest_id
   *   The digest id.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Prepared digest view.
   */
  public function view($digest_id) {
    return $this->civimailDigest->viewDigest($digest_id);
  }

  /**
   * Returns a list of digests with status and actions.
   *
   * @return array
   *   Return list and actions links for digests.
   */
  public function digestList() {
    if ($this->civimailDigest->isActive()) {
      return [
        'links' => $this->buildActionLinks(),
        'table' => $this->buildDigestTable(),
      ];
    }
    else {
      return [
        'links' => $this->buildActionLinks(),
      ];
    }
  }

}
