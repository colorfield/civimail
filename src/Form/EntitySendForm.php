<?php

namespace Drupal\civimail\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\civimail\CiviMailInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity send form.
 */
class EntitySendForm extends FormBase {

  /**
   * Drupal\civimail\CiviMailInterface definition.
   *
   * @var \Drupal\civimail\CiviMailInterface
   */
  protected $civiMail;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an EntitySendForm object.
   *
   * @param \Drupal\civimail\CiviMailInterface $civi_mail
   *   The CiviMail service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(CiviMailInterface $civi_mail, ConfigFactoryInterface $config_factory) {
    $this->civiMail = $civi_mail;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('civimail'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'civimail_entity_send_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node_type = NULL) {
    // @todo check if CiviCRM Contact and Groups entities are available
    // @todo add filter
    // @todo add default contact
    $form['from_contact'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('From'),
      '#description' => $this->t('The sender CiviCRM contact.'),
      '#target_type' => 'civicrm_contact',
      '#required' => TRUE,
    ];
    $form['test_mode'] = [
      '#type' => 'checkbox',
      '#title' => t('Send a test'),
    ];
    $form['test_mail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test email'),
      '#description' => $this->t('The email address that will receive the test.'),
      '#maxlength' => 254,
      '#size' => 64,
      '#default_value' => \Drupal::config('system.site')->get('mail'),
      '#states' => [
        'visible' => [
          ':input[name="test_mode"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="test_mode"]' => ['checked' => TRUE],
        ],
      ],
    ];
    // @todo filter by groups that are configured for this content type
    $form['to_groups'] = [
      '#type' => 'select',
      '#title' => t('Groups'),
      '#options' => $this->civiMail->getGroupEntitiesLabel(),
      '#multiple' => TRUE,
      '#limit_validation_errors' => ['submit'],
      '#states' => [
        'visible' => [
          ':input[name="test_mode"]' => ['checked' => FALSE],
        ],
        'required' => [
          ':input[name="test_mode"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Send'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fromCid = $form_state->getValue('from_contact');
    $testMode = $form_state->getValue('test_mode');
    $testMail = $form_state->getValue('test_mail');
    $entity = $this->civiMail->getEntityFromRoute('node');
    if ($testMode) {
      $mailingResult = $this->civiMail->sendTestMail($fromCid, $entity, $testMail);
    }
    else {
      $groups = $form_state->getValue('to_groups');
      $params = $this->civiMail->getEntityMailingParams($fromCid, $entity, $groups);
      $mailingResult = $this->civiMail->sendMailing($params, $entity);
    }
  }

}
