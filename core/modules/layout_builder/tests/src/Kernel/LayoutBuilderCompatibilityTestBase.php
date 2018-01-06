<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests Layout Builder's compatibility with existing systems.
 */
abstract class LayoutBuilderCompatibilityTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'layout_discovery',
  ];

  /**
   * The entity view display.
   *
   * @var \Drupal\field_layout\Display\EntityDisplayWithLayoutInterface
   */
  protected $display;

  /**
   * The entity being rendered.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_base_field_display');
    $this->installConfig(['filter']);

    // Set up a non-admin user that is allowed to view test entities.
    \Drupal::currentUser()->setAccount($this->createUser(['uid' => 2], ['view test entity']));

    \Drupal::service('theme_handler')->install(['classy']);
    $this->config('system.theme')->set('default', 'classy')->save();

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test_base_field_display',
      'field_name' => 'test_field_display_configurable',
      'type' => 'boolean',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test_base_field_display',
      'label' => 'FieldConfig with configurable display',
    ])->save();

    $this->display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test_base_field_display',
      'bundle' => 'entity_test_base_field_display',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $this->display
      ->setComponent('test_field_display_configurable', ['region' => 'content', 'weight' => 5])
      ->save();

    // Create an entity with fields that are configurable and non-configurable.
    $entity_storage = $this->container->get('entity_type.manager')->getStorage('entity_test_base_field_display');
    // @todo Remove langcode workarounds after resolving
    //   https://www.drupal.org/node/2915034.
    $this->entity = $entity_storage->createWithSampleValues('entity_test_base_field_display', [
      'langcode' => 'en',
      'langcode_default' => TRUE,
    ]);
    $this->entity->save();
  }

  /**
   * Installs the Layout Builder.
   *
   * Also configures and reloads the entity display, and reloads the entity.
   */
  protected function installLayoutBuilder() {
    $this->container->get('module_installer')->install(['layout_builder']);
    $this->refreshServices();

    $this->display = $this->reloadEntity($this->display);
    $this->display->setOverridable()->save();
    $this->entity = $this->reloadEntity($this->entity);
  }

  /**
   * Renders the provided entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the entity.
   * @param string $langcode
   *   (optional) For which language the entity should be rendered, defaults to
   *   the current content language.
   *
   * @return string
   *   The rendered string output (typically HTML).
   */
  protected function renderEntity(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    $view_builder = $this->container->get('entity_type.manager')->getViewBuilder($entity->getEntityTypeId());
    $build = $view_builder->view($entity, $view_mode, $langcode);
    return $this->render($build);
  }

}
