<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\outside_in\Ajax\OpenOffCanvasDialogCommand;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class DeleteVisibility extends ConfirmFormBase {
  use TempstoreIdHelper;

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

  protected $uuid;

  protected $region;

  protected $delta;

  protected $fieldName;

  protected $entity;

  protected $entityType;

  /**
   * Constructs a new ConfigureBlock.
   *
   * @param \Drupal\user\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   */
  public function __construct(SharedTempStoreFactory $tempstore, ContextRepositoryInterface $context_repository, EntityTypeManagerInterface $entity_type_manager) {
    $this->tempStoreFactory = $tempstore;
    $this->contextRepository = $context_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.shared_tempstore'),
      $container->get('context.repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   *
   */
  public function getQuestion() {
    return $this->t("Are you sure you want to delete this visibility condition?");
  }

  /**
   *
   */
  public function getCancelUrl() {
    $parameters = [
      'entity_type' => $this->entityType,
      'entity' => $this->entity,
      'field_name' => $this->fieldName,
      'delta' => $this->delta,
      'region' => $this->region,
      'uuid' => $this->uuid,
    ];
    return new Url('layout_builder.visibility', $parameters);
  }

  /**
   *
   */
  public function getFormId() {
    return 'layout_builder_delete_visibility';
  }

  /**
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $entity = NULL, $field_name = NULL, $delta = NULL, $region = NULL, $block_id = NULL, $plugin_id = NULL) {
    $form = parent::buildForm($form, $form_state);
    $form['parameters'] = [
      '#type' => 'value',
      '#value' => [
        'entity_type' => $entity_type,
        'entity' => $entity,
        'field_name' => $field_name,
        'delta' => $delta,
        'region' => $region,
        'uuid' => $block_id,
        'plugin_id' => $plugin_id,
      ],
    ];
    $this->entityType = $entity_type;
    $this->entity = $entity;
    $this->fieldName = $field_name;
    $this->delta = $delta;
    $this->region = $region;
    $this->uuid = $block_id;
    $form['actions']['cancel'] = $this->buildCancelLink();
    return $form;
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $parameters = $form_state->getValue('parameters');
    $entity_type = $parameters['entity_type'];
    $entity = $parameters['entity'];
    $field_name = $parameters['field_name'];
    $delta = $parameters['delta'];
    $region = $parameters['region'];
    $block_id = $parameters['uuid'];
    $plugin_id = $parameters['plugin_id'];
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity, $field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $values = $entity->$field_name->getValue();
    unset($values[$delta]['section'][$region][$block_id]['visibility'][$plugin_id]);
    $entity->$field_name->setValue($values);
    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);
    $form_state->setRedirect("entity.{$entity->getEntityTypeId()}.layout", [$entity->getEntityTypeId() => $entity->id()]);
  }

  /**
   *
   */
  protected function buildCancelLink() {
    return [
      '#type' => 'button',
      '#value' => $this->getCancelText(),
      '#ajax' => [
        'callback' => '::ajaxCancel',
      ],
    ];
  }

  /**
   *
   */
  public function ajaxCancel(array &$form, FormStateInterface $form_state) {
    $parameters = $form_state->getValue('parameters');
    $new_form = \Drupal::formBuilder()->getForm('\Drupal\layout_builder\Form\BlockVisibilityForm', $parameters['entity_type'], $parameters['entity'], $parameters['field_name'], $parameters['delta'], $parameters['region'], $parameters['uuid']);
    $new_form['#action'] = $this->getCancelUrl()->toString();
    $response = new AjaxResponse();
    $response->addCommand(new OpenOffCanvasDialogCommand($this->t("Configure Condition"), $new_form));
    return $response;
  }

}
