<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for confirmation forms that rebuild the Layout Builder.
 */
abstract class LayoutRebuildConfirmFormBase extends ConfirmFormBase {

  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

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
   * The field delta.
   *
   * @var int
   */
  protected $delta;

  /**
   * Constructs a new RemoveSectionForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository')
    );
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
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $entity_id = NULL, $delta = NULL) {
    $form = parent::buildForm($form, $form_state);

    $this->entityTypeId = $entity_type_id;
    $this->entityId = $entity_id;
    $this->delta = $delta;

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';

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
    $entity = $this->layoutTempstoreRepository->getFromId($this->entityTypeId, $this->entityId);

    $this->handleEntity($entity, $form_state);

    $this->layoutTempstoreRepository->set($entity);

    $form_state->setRedirect("entity.{$entity->getEntityTypeId()}.layout", [$entity->getEntityTypeId() => $entity->id()]);
  }

  /**
   * Performs any actions on the layout entity before saving.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  abstract protected function handleEntity(EntityInterface $entity, FormStateInterface $form_state);

  /**
   * Ajax callback to close the modal.
   */
  public function ajaxCancel(array &$form, FormStateInterface $form_state) {
    return $this->closeLayout(new AjaxResponse(), $this->getCancelUrl());
  }

}
