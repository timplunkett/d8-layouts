<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Layout Builder routes.
 */
class AddSectionController implements ContainerInjectionInterface {

  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * AddSectionController constructor.
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
   * Add the layout to the entity field in a tempstore.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param int $delta
   *   The delta of the section to splice.
   * @param string $plugin_id
   *   The plugin ID of the layout to add.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The render array.
   */
  public function build(EntityInterface $entity, $delta, $plugin_id) {
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemListInterface $field_list */
    $field_list = $entity->layout_builder__layout;
    $field_list->addItem($delta, [
      'layout' => $plugin_id,
      'layout_settings' => [],
      'section' => [],
    ]);

    $this->layoutTempstoreRepository->set($entity);
    return $this->rebuildAndClose(new AjaxResponse(), $entity);
  }

}
