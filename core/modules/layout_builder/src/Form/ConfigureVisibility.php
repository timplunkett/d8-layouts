<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class ConfigureVisibility extends FormBase {
  use ContextAwarePluginAssignmentTrait;
  use TempstoreIdHelper;
  //use DialogFormTrait;

  /**
   * Tempstore factory.
   *
   * @var \Drupal\user\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The plugin being configured.
   *
   * @var \Drupal\Core\Block\BlockPluginInterface
   */
  protected $condition;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The condition manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolver
   */
  protected $classResolver;

  /**
   * Constructs a new ConfigureBlock.
   *
   * @param \Drupal\user\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   */
  public function __construct(SharedTempStoreFactory $tempstore, ContextRepositoryInterface $context_repository, EntityTypeManagerInterface $entity_type_manager, ConditionManager $condition_manager, UuidInterface $uuid, ClassResolver $class_resolver) {
    $this->tempStoreFactory = $tempstore;
    $this->contextRepository = $context_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->conditionManager = $condition_manager;
    $this->uuid = $uuid;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.shared_tempstore'),
      $container->get('context.repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.condition'),
      $container->get('uuid'),
      $container->get('class_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_configure_visibility';
  }

  /**
   * Prepares the condition plugin based on the condition ID.
   *
   * @param string $condition_id
   *   A condition UUID, or the plugin ID used to create a new condition.
   *
   * @param array $value
   *   The condition configuration.
   *
   * @return \Drupal\Core\Condition\ConditionInterface
   *   The condition plugin.
   */
  protected function prepareCondition($condition_id, array $value) {
    if ($value) {
      return $this->conditionManager->createInstance($value['id'], $value);
    }
    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $this->conditionManager->createInstance($condition_id);
    $configuration = $condition->getConfiguration();
    $configuration['uuid'] = $this->uuid->generate();
    $condition->setConfiguration($configuration);
    return $condition;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $entity = NULL, $field_name = NULL, $delta = NULL, $region = NULL, $block_id = NULL, $plugin_id = NULL) {
    $entity = $this->entityTypeManager->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity, $field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $values = $entity->$field_name->getValue();
    $value = !empty($values[$delta]['section'][$region][$block_id]['visibility'][$plugin_id]) ? $values[$delta]['section'][$region][$block_id]['visibility'][$plugin_id] : [];
    $this->condition = $this->prepareCondition($plugin_id, $value);

    $form_state->setTemporaryValue('gathered_contexts', $this->contextRepository->getAvailableContexts());

    $form_state->set('collection', $collection);
    $form_state->set('machine_name', $id);
    $form_state->set('entity_type', $entity_type);
    $form_state->set('entity', $entity);
    $form_state->set('field_name', $field_name);
    $form_state->set('delta', $delta);
    $form_state->set('region', $region);
    $form_state->set('block_id', $block_id);
    $form_state->set('condition_id', $this->condition->getConfiguration()['uuid']);

    // Some Block Plugins rely on the block_theme value to load theme settings.
    // @see \Drupal\system\Plugin\Block\SystemBrandingBlock::blockForm().
    $form_state->set('block_theme', $this->config('system.theme')->get('default'));

    $form['#tree'] = TRUE;
    $form['settings'] = $this->condition->buildConfigurationForm([], $form_state);
    $form['settings']['id'] = [
      '#type' => 'value',
      '#value' => $this->condition->getPluginId(),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $value ? $this->t('Update') : $this->t('Add Condition'),
      '#button_type' => 'primary',
    ];

    //$this->buildFormDialog($form, $form_state);
    $form['actions']['submit']['#ajax']['callback'] = [$this, 'ajaxSubmit'];
    $form['#validate'][] = [$this, 'validateForm'];
    $form['#submit'][] = [$this, 'submitForm'];

    return $form;
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or redirects
   *   to a URL
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    // @todo Check for errors.
    // @see \Drupal\layout_builder\Form\DialogFormTrait::submitFormDialog()
    $response = new AjaxResponse();
    /** @var \Drupal\layout_builder\Controller\LayoutController $layout_controller */
    $layout_controller = $this->classResolver->getInstanceFromDefinition('\Drupal\layout_builder\Controller\LayoutController');
    $entity = $form_state->get('entity');
    $field = $form_state->get('field_name');
    $layout = $layout_controller->layout($entity, $field);
    $command = new ReplaceCommand('#layout-builder', $layout);
    $response->addCommand($command);
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $settings = (new FormState())->setValues($form_state->getValue('settings'));
    // Call the plugin validate handler.
    $this->condition->validateConfigurationForm($form, $settings);
    // Update the original form values.
    $form_state->setValue('settings', $settings->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = (new FormState())->setValues($form_state->getValue('settings'));

    // Call the plugin submit handler.
    $this->condition->submitConfigurationForm($form, $settings);
    // Update the original form values.
    $form_state->setValue('settings', $settings->getValues());

    if ($this->condition instanceof ContextAwarePluginInterface) {
      $this->condition->setContextMapping($settings->getValue('context_mapping', []));
    }

    $configuration = $this->condition->getConfiguration();

    $entity = $form_state->get('entity');
    $collection = $form_state->get('collection');
    $id = $form_state->get('machine_name');
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    $field_name = $form_state->get('field_name');
    $delta = $form_state->get('delta');
    $region = $form_state->get('region');
    $block_id = $form_state->get('block_id');
    $condition_id = $form_state->get('condition_id');
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $values = $entity->$field_name->getValue();
    $values[$delta]['section'][$region][$block_id]['visibility'][$condition_id] = $configuration;
    $entity->$field_name->setValue($values);

    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);
    $form_state->setRedirect("entity.{$entity->getEntityTypeId()}.layout", [$entity->getEntityTypeId() => $entity->id()]);
  }

}
