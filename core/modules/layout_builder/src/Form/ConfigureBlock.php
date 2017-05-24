<?php


namespace Drupal\layout_builder\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigureBlock extends FormBase {
  use ContextAwarePluginAssignmentTrait;
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
  protected $block;

  /**
   * The context repository.
   *
   * @var ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block manager.
   *
   * @var BlockManagerInterface
   */
  protected $blockManager;

  /**
   * @var UuidInterface
   */
  protected $uuid;

  /**
   * Constructs a new ConfigureBlock.
   *
   * @param \Drupal\user\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   */
  public function __construct(SharedTempStoreFactory $tempstore, ContextRepositoryInterface $context_repository, EntityTypeManagerInterface $entity_type_manager, BlockManagerInterface $block_manager, UuidInterface $uuid) {
    $this->tempStoreFactory = $tempstore;
    $this->contextRepository = $context_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->blockManager = $block_manager;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.shared_tempstore'),
      $container->get('context.repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.block'),
      $container->get('uuid')
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
   *
   * @param array $value
   *   The block configuration.
   * @return \Drupal\Core\Block\BlockPluginInterface The block plugin.
   * The block plugin.
   */
  protected function prepareBlock($block_id, array $value) {
    if ($value) {
      return $this->blockManager->createInstance($value['id'], $value);
    }
    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = $this->blockManager->createInstance($block_id);
    $block->setConfigurationValue('uuid', $this->uuid->generate());
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $entity = NULL, $field_name = NULL, $delta = NULL, $region = NULL, $plugin_id = NULL) {
    $entity = $this->entityTypeManager->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity, $field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $values = $entity->$field_name->getValue();
    $value = !empty($values[$delta]['section'][$region][$plugin_id]) ? $values[$delta]['section'][$region][$plugin_id] : [];
    $this->block = $this->prepareBlock($plugin_id, $value);

    $form_state->setTemporaryValue('gathered_contexts', $this->contextRepository->getAvailableContexts());

    $form_state->set('collection', $collection);
    $form_state->set('machine_name', $id);
    $form_state->set('entity_type', $entity_type);
    $form_state->set('entity', $entity);
    $form_state->set('field_name', $field_name);
    $form_state->set('delta', $delta);
    $form_state->set('region', $region);
    $form_state->set('block_id', $this->block->getConfiguration()['uuid']);

    // Some Block Plugins rely on the block_theme value to load theme settings.
    // @see \Drupal\system\Plugin\Block\SystemBrandingBlock::blockForm().
    $form_state->set('block_theme', $this->config('system.theme')->get('default'));

    $form['#tree'] = TRUE;
    $form['settings'] = $this->block->buildConfigurationForm([], $form_state);
    $form['settings']['id'] = [
      '#type' => 'value',
      '#value' => $this->block->getPluginId(),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Block'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $settings = (new FormState())->setValues($form_state->getValue('settings'));
    // Call the plugin validate handler.
    $this->block->validateConfigurationForm($form, $settings);
    // Update the original form values.
    $form_state->setValue('settings', $settings->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = (new FormState())->setValues($form_state->getValue('settings'));

    // Call the plugin submit handler.
    $this->block->submitConfigurationForm($form, $settings);
    // Update the original form values.
    $form_state->setValue('settings', $settings->getValues());

    if ($this->block instanceof ContextAwarePluginInterface) {
      $this->block->setContextMapping($settings->getValue('context_mapping', []));
    }

    $configuration = $this->block->getConfiguration();

    $entity = $form_state->get('entity');
    $collection = $form_state->get('collection');
    $id = $form_state->get('machine_name');
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    $field_name = $form_state->get('field_name');
    $delta = $form_state->get('delta');
    $region = $form_state->get('region');
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    $values = $entity->$field_name->getValue();
    $values[$delta]['section'][$region][$configuration['uuid']] = $configuration;
    $entity->$field_name->setValue($values);

    $tempstore['entity'] = $entity;
    $this->tempStoreFactory->get($collection)->set($id, $tempstore);
    $path = '/'. $this->getUrlGenerator()->getPathFromRoute("entity.{$entity->getEntityTypeId()}.layout", [$entity->getEntityTypeId() => $entity->id()]);
    $response = new TrustedRedirectResponse($path);
    $form_state->setResponse($response);
  }

}
