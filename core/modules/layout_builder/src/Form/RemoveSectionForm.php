<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to confirm the removal of a section.
 */
class RemoveSectionForm extends ConfirmFormBase {

  use TempstoreIdHelper;

  /**
   * Tempstore factory.
   *
   * @var \Drupal\user\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The entity ID.
   *
   * @var int
   */
  protected $entityId;

  /**
   * The field machine name.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The field delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * Constructs a new RemoveSectionForm.
   *
   * @param \Drupal\user\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SharedTempStoreFactory $tempstore, EntityTypeManagerInterface $entity_type_manager) {
    $this->tempStoreFactory = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.shared_tempstore'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to remove this section?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $parameters = [
      $this->entityTypeId => $this->entityId,
    ];
    return new Url("entity.{$this->entityTypeId}.layout", $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_remove_section';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $entity = NULL, $field_name = NULL, $delta = NULL) {
    $form = parent::buildForm($form, $form_state);

    $this->entityTypeId = $entity_type;
    $this->entityId = $entity;
    $this->fieldName = $field_name;
    $this->delta = $delta;

    // @todo Improve the cancel link of ConfirmFormBase to handle AJAX links.
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->getCancelText(),
      '#ajax' => [
        'callback' => '::ajaxCancel',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity_from_storage = $this->entityTypeManager->getStorage($this->entityTypeId)->loadRevision($this->entityId);

    list($collection, $id) = $this->generateTempstoreId($entity_from_storage, $this->fieldName);
    $entity = $this->tempStoreFactory->get($collection)->get($id)['entity'] ?: $entity_from_storage;

    $entity->{$this->fieldName}->removeItem($this->delta);

    $this->tempStoreFactory->get($collection)->set($id, ['entity' => $entity]);

    $form_state->setRedirect("entity.{$entity->getEntityTypeId()}.layout", [$entity->getEntityTypeId() => $entity->id()]);
  }

  /**
   * Ajax callback to close the modal.
   */
  public function ajaxCancel(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
  }

}
