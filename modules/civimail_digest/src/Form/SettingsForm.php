<?php

namespace Drupal\civimail_digest\Form;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\civicrm_tools\CiviCrmGroupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Drupal\civicrm_tools\CiviCrmGroupInterface definition.
   *
   * @var \Drupal\civicrm_tools\CiviCrmGroupInterface
   */
  protected $civicrmToolsGroup;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    CiviCrmGroupInterface $civicrm_tools_group,
    EntityTypeManagerInterface $entity_type_manager
    ) {
    parent::__construct($config_factory);
    $this->civicrmToolsGroup = $civicrm_tools_group;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('civicrm_tools.group'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'civimail_digest.settings',
    ];
  }

  /**
   * Returns a list of days.
   *
   * @return array
   *   List of week days.
   */
  private function getWeekDays() {
    // @todo review existing API
    return [
      0 => t('Sunday'),
      1 => t('Monday'),
      2 => t('Tuesday'),
      3 => t('Wednesday'),
      4 => t('Thursday'),
      5 => t('Friday'),
      6 => t('Saturday'),
    ];
  }

  /**
   * Returns a list of hours.
   *
   * @return array
   *   List of hours.
   */
  private function getHours() {
    // @todo review existing API
    $result = [];
    for ($h = 0; $h < 24; $h++) {
      $result[$h] = $h . ':00';
    }
    return $result;
  }

  /**
   * Returns a list of bundles currently limited to node type.
   *
   * @return array
   *   List of bundles.
   */
  private function getBundles() {
    $result = [];
    try {
      // @todo extend to other entity types
      $nodeBundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      foreach ($nodeBundles as $key => $bundle) {
        $result[$key] = $bundle->label();
      }
    }
    catch (InvalidPluginDefinitionException $exception) {
      $exception->getMessage();
    }
    return $result;
  }

  /**
   * Returns all the groups, to be used as select options.
   *
   * @return array
   *   List of CiviCRM groups.
   */
  private function getGroups() {
    $result = [];
    $groups = $this->civicrmToolsGroup->getAllGroups();
    foreach ($groups as $key => $group) {
      $result[$key] = $group['title'];
    }
    return $result;
  }

  /**
   * Returns a list of contacts for a group, to be used as select options.
   *
   * @param int $group_id
   *   CiviCRM group id.
   *
   * @return array
   *   List of CiviCRM contacts.
   */
  private function getContacts($group_id) {
    $result = [];
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'civimail_digest.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('civimail_digest.settings');
    $form['digest_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Digest title'),
      '#description' => $this->t('Title that appears in mail subject, title, browser view. The digest number will be appended.'),
      '#maxlength' => 254,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('digest_title'),
    ];
    $form['is_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is active'),
      '#description' => $this->t('When checked, digests will be mailed automatically on the selected day and hour, each week.'),
      '#default_value' => $config->get('is_active'),
    ];
    $form['week_day'] = [
      '#type' => 'select',
      '#title' => $this->t('Week day'),
      '#description' => $this->t('Day to send the weekly digest.'),
      '#options' => $this->getWeekDays(),
      '#required' => TRUE,
      '#default_value' => $config->get('week_day'),
    ];
    $form['hour'] = [
      '#type' => 'select',
      '#title' => $this->t('Hour'),
      '#description' => $this->t('Hour to send the weekly digest.'),
      '#options' => $this->getHours(),
      '#required' => TRUE,
      '#default_value' => $config->get('hour'),
    ];
    $form['entity_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Entity limit'),
      '#description' => $this->t('Limit entities that will be included in a single digest.'),
      '#required' => TRUE,
      '#default_value' => $config->get('entity_limit'),
    ];
    $form['bundles'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundles'),
      '#description' => $this->t('Optionally limit bundles that can be part of the digest. All apply if none selected.'),
      '#options' => $this->getBundles(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#default_value' => $config->get('bundles'),
    ];
    $form['from_contact'] = [
      '#type' => 'select',
      '#title' => $this->t('From contact'),
      '#description' => $this->t('Contact that will be used as the sender.'),
    // @todo
      '#options' => [],
      '#required' => TRUE,
      '#default_value' => $config->get('from_contact'),
    ];
    $form['to_groups'] = [
      '#type' => 'select',
      '#title' => $this->t('To groups'),
      '#description' => $this->t('CiviCRM groups that will receive the digest.'),
      '#options' => $this->getGroups(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#default_value' => $config->get('to_groups'),
    ];
    $form['test_groups'] = [
      '#type' => 'select',
      '#title' => $this->t('Test groups'),
      '#description' => $this->t('CiviCRM groups that will receive tests.'),
      '#options' => $this->getGroups(),
      '#multiple' => TRUE,
      '#default_value' => $config->get('test_groups'),
    ];
    $form['validation_contacts'] = [
      '#type' => 'select',
      '#title' => $this->t('Validation contacts'),
      '#description' => $this->t('CiviCRM contacts that will confirm that the digest can be sent.'),
    // @todo
      '#options' => [],
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#default_value' => $config->get('validation_contacts'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('civimail_digest.settings')
      ->set('digest_title', $form_state->getValue('digest_title'))
      ->set('is_active', $form_state->getValue('is_active'))
      ->set('week_day', $form_state->getValue('week_day'))
      ->set('hour', $form_state->getValue('hour'))
      ->set('entity_limit', $form_state->getValue('entity_limit'))
      ->set('bundles', $form_state->getValue('bundles'))
      ->set('from_contact', $form_state->getValue('from_contact'))
      ->set('to_groups', $form_state->getValue('to_groups'))
      ->set('test_groups', $form_state->getValue('test_groups'))
      ->set('validation_contacts', $form_state->getValue('validation_contacts'))
      ->save();
  }

}
