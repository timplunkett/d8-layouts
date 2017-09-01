<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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

  protected $isUpdate;

  /**
   * Constructs a new ConfigureSectionForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layout_manager
   *   The layout manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, LayoutPluginManagerInterface $layout_manager, EntityTypeManagerInterface $entity_type_manager, ClassResolverInterface $class_resolver) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->layoutManager = $layout_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('plugin.manager.core.layout'),
      $container->get('entity_type.manager'),
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
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $entity_id = NULL, $delta = NULL, $plugin_id = NULL) {
    $this->entityTypeId = $entity_type_id;
    $this->entityId = $entity_id;
    $this->delta = $delta;

    $this->isUpdate = is_null($plugin_id);

    $configuration = [];
    if ($this->isUpdate) {
      $entity = $this->layoutTempstoreRepository->getFromId($this->entityTypeId, $this->entityId);

      /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
      $field = $entity->layout_builder__layout->get($this->delta);
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

    $entity = $this->layoutTempstoreRepository->getFromId($this->entityTypeId, $this->entityId);

    if ($this->isUpdate) {
      /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
      $field = $entity->layout_builder__layout->get($this->delta);
      $field->layout = $plugin_id;
      $field->layout_settings = $configuration;
    }
    else {
      $values = $entity->layout_builder__layout->getValue();
      $value = [
        'layout' => $plugin_id,
        'layout_settings' => $configuration,
        'section' => [],
      ];
      if (isset($values[$this->delta])) {
        $start = array_slice($values, 0, $this->delta);
        $end = array_slice($values, $this->delta);
        $values = array_merge($start, [$value], $end);
      }
      else {
        $values[] = $value;
      }
      $entity->layout_builder__layout->setValue($values);
    }

    $this->layoutTempstoreRepository->set($entity);
    $form_state->setRedirect("entity.{$this->entityTypeId}.layout", [$this->entityTypeId => $this->entityId]);
  }

}
