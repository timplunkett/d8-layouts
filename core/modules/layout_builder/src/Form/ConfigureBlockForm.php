<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\PluginFormFactoryInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\layout_builder\Controller\LayoutRebuildTrait;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to configure a block.
 */
class ConfigureBlockForm extends FormBase {

  use ContextAwarePluginAssignmentTrait;
  use LayoutRebuildTrait;

  /**
   * The plugin being configured.
   *
   * @var \Drupal\Core\Block\BlockPluginInterface
   */
  protected $block;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The UUID generator.
   *
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
   * The plugin form manager.
   *
   * @var \Drupal\Core\Plugin\PluginFormFactoryInterface
   */
  protected $pluginFormFactory;

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
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * Constructs a new ConfigureBlockForm.
   *
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The context repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID generator.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Plugin\PluginFormFactoryInterface $plugin_form_manager
   *   The plugin form manager.
   */
  public function __construct(LayoutTempstoreRepositoryInterface $layout_tempstore_repository, ContextRepositoryInterface $context_repository, EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, UuidInterface $uuid, ClassResolverInterface $class_resolver, PluginFormFactoryInterface $plugin_form_manager) {
    $this->layoutTempstoreRepository = $layout_tempstore_repository;
    $this->contextRepository = $context_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->uuid = $uuid;
    $this->classResolver = $class_resolver;
    $this->pluginFormFactory = $plugin_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_builder.tempstore_repository'),
      $container->get('context.repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('uuid'),
      $container->get('class_resolver'),
      $container->get('plugin_form.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_configure_block';
  }

  /**
   * Prepares the block plugin based on the block ID.
   *
   * @param string $block_id
   *   Either a block ID, or the plugin ID used to create a new block.
   * @param array $configuration
   *   The block configuration.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block plugin.
   */
  protected function prepareBlock($block_id, array $configuration) {
    if (!isset($configuration['uuid'])) {
      $configuration['uuid'] = $this->uuid->generate();
    }

    return $this->blockManager->createInstance($block_id, $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $entity_id = NULL, $delta = NULL, $region = NULL, $plugin_id = NULL, $uuid = NULL) {
    $this->entityTypeId = $entity_type_id;
    $this->entityId = $entity_id;
    $this->delta = $delta;
    $this->region = $region;

    $configuration = [];
    if ($uuid) {
      $entity = $this->layoutTempstoreRepository->getFromId($this->entityTypeId, $this->entityId);

      /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
      $field = $entity->layout_builder__layout->get($this->delta);
      $plugin_id = $field->section[$region][$uuid]['id'];
      $configuration = $field->section[$region][$uuid];
    }
    $this->block = $this->prepareBlock($plugin_id, $configuration);

    $form_state->setTemporaryValue('gathered_contexts', $this->contextRepository->getAvailableContexts());

    // Some Block Plugins rely on the block_theme value to load theme settings.
    // @see \Drupal\system\Plugin\Block\SystemBrandingBlock::blockForm().
    $form_state->set('block_theme', $this->config('system.theme')->get('default'));

    $form['#tree'] = TRUE;
    $form['settings'] = [];
    $subform_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $form['settings'] = $this->getPluginForm($this->block)->buildConfigurationForm($form['settings'], $subform_state);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $uuid ? $this->t('Update') : $this->t('Add Block'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
      ],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attributes']['id'] = 'dialog-form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $sub_form_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->getPluginForm($this->block)->validateConfigurationForm($form['settings'], $sub_form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Call the plugin submit handler.
    $sub_form_state = SubformState::createForSubform($form['settings'], $form, $form_state);
    $this->getPluginForm($this->block)->submitConfigurationForm($form, $sub_form_state);

    // If this block is context-aware, set the context mapping.
    if ($this->block instanceof ContextAwarePluginInterface) {
      $this->block->setContextMapping($sub_form_state->getValue('context_mapping', []));
    }

    $configuration = $this->block->getConfiguration();

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $entity = $this->layoutTempstoreRepository->getFromId($this->entityTypeId, $this->entityId);
    $values = $entity->layout_builder__layout->getValue();
    $values[$this->delta]['section'][$this->region][$configuration['uuid']] = $configuration;
    $entity->layout_builder__layout->setValue($values);

    $this->layoutTempstoreRepository->set($entity);
    $form_state->setRedirect("entity.{$this->entityTypeId}.layout", [$this->entityTypeId => $this->entityId]);
  }

  /**
   * Retrieves the plugin form for a given block and operation.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   The block plugin.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   The plugin form for the block.
   */
  protected function getPluginForm(BlockPluginInterface $block) {
    if ($block instanceof PluginWithFormsInterface) {
      return $this->pluginFormFactory->createInstance($block, 'configure');
    }
    return $block;
  }

}
