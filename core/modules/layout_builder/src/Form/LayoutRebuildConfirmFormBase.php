<?php

namespace Drupal\layout_builder\Form;

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
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

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
    return Url::fromRoute("entity.{$this->entity->getEntityTypeId()}.layout", [$this->entity->getEntityTypeId() => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, $delta = NULL) {
    $this->entity = $entity;
    $this->delta = $delta;

    $form = parent::buildForm($form, $form_state);

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['actions']['submit']['#ajax']['callback'] = '::ajaxSubmit';

    $form['actions']['cancel']['#attributes']['class'][] = 'dialog-cancel';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->handleEntity($this->entity, $form_state);

    $this->layoutTempstoreRepository->set($this->entity);

    $form_state->setRedirectUrl($this->getCancelUrl());
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

}
