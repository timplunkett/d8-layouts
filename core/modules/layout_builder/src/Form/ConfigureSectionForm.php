<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for configuring a layout section.
 */
class ConfigureSectionForm extends FormBase {

  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The plugin being configured.
   *
   * @var \Drupal\Core\Layout\LayoutInterface|\Drupal\Core\Plugin\PluginFormInterface
   */
  protected $layout;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

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
   * Indicates whether the section is being added or updated.
   *
   * @var bool
   */
  protected $isUpdate;

  /**
   * Constructs a new ConfigureSectionForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, LayoutPluginManagerInterface $layout_manager, ClassResolverInterface $class_resolver) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->layoutManager = $layout_manager;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.core.layout'),
      $container->get('class_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_configure_section';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, $delta = NULL, $plugin_id = NULL) {
    $this->entity = $entity;
    $this->delta = $delta;
    $this->isUpdate = is_null($plugin_id);

    $configuration = [];
    if ($this->isUpdate) {
      /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
      $field = $this->entity->layout_builder__layout->get($this->delta);
      $plugin_id = $field->layout;
      $configuration = $field->layout_settings;
    }
    $this->layout = $this->layoutManager->createInstance($plugin_id, $configuration);

    $form['#tree'] = TRUE;
    $form['layout_settings'] = [];
    $subform_state = SubformState::createForSubform($form['layout_settings'], $form, $form_state);
    $form['layout_settings'] = $this->layout->buildConfigurationForm($form['layout_settings'], $subform_state);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->isUpdate ? $this->t('Update') : $this->t('Add section'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $subform_state = SubformState::createForSubform($form['layout_settings'], $form, $form_state);
    $this->layout->validateConfigurationForm($form['layout_settings'], $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Call the plugin submit handler.
    $subform_state = SubformState::createForSubform($form['layout_settings'], $form, $form_state);
    $this->layout->submitConfigurationForm($form['layout_settings'], $subform_state);

    $plugin_id = $this->layout->getPluginId();
    $configuration = $this->layout->getConfiguration();

    /** @var \Drupal\layout_builder\Field\LayoutSectionItemListInterface $field_list */
    $field_list = $this->entity->layout_builder__layout;
    if ($this->isUpdate) {
      $field = $field_list->get($this->delta);
      $field->layout = $plugin_id;
      $field->layout_settings = $configuration;
    }
    else {
      $field_list->addItem($this->delta, [
        'layout' => $plugin_id,
        'layout_settings' => $configuration,
        'section' => [],
      ]);
    }

    $this->layoutTempstoreRepository->set($this->entity);
    $form_state->setRedirect("entity.{$this->entity->getEntityTypeId()}.layout", [$this->entity->getEntityTypeId() => $this->entity->id()]);
  }

}
