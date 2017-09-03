<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Layout Builder routes.
 */
class MoveBlockController implements ContainerInjectionInterface {

  use LayoutRebuildTrait;

  /**
   * The layout tempstore repository.
   *
   * @var \Drupal\layout_builder\LayoutTempstoreRepositoryInterface
   */
  protected $layoutTempstoreRepository;

  /**
   * LayoutController constructor.
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
   * Moves a block to another region.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response.
   */
  public function build(EntityInterface $entity, Request $request) {
    $data = $request->request->all();

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->layout_builder__layout->get($data['delta_from']);
    $values = $field->section ?: [];

    $region_from = $data['region_from'];
    $region_to = $data['region_to'];
    $block_uuid = $data['block_uuid'];
    $configuration = $values[$region_from][$block_uuid];
    unset($values[$region_from][$block_uuid]);
    $field->section = array_filter($values);

    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->layout_builder__layout->get($data['delta_to']);
    $values = $field->section ?: [];
    if (isset($data['preceding_block_uuid'])) {
      $slice_id = array_search($data['preceding_block_uuid'], array_keys($values[$region_to]));
      $before = array_slice($values[$region_to], 0, $slice_id + 1);
      $after = array_slice($values[$region_to], $slice_id + 1);
      $values[$region_to] = array_merge($before, [$block_uuid => $configuration], $after);
    }
    else {
      if (empty($values[$region_to])) {
        $values[$region_to] = [];
      }
      $values[$region_to] = array_merge([$block_uuid => $configuration], $values[$region_to]);
    }
    $field->section = array_filter($values);

    $this->layoutTempstoreRepository->set($entity);
    return $this->rebuildLayout(new AjaxResponse(), $entity);
  }

}
