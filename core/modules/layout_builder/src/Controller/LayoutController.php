<?php


namespace Drupal\layout_builder\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\LayoutSectionBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LayoutController extends ControllerBase {

  /**
   * The layout builder.
   *
   * @var \Drupal\layout_builder\LayoutSectionBuilder
   */
  protected $builder;

  /**
   * LayoutController constructor.
   *
   * @param \Drupal\layout_builder\LayoutSectionBuilder $builder
   */
  public function __construct(LayoutSectionBuilder $builder) {
    $this->builder = $builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('layout_builder.builder'));
  }


  public function layout(FieldableEntityInterface $entity, $field_name) {
    $output = [];
    foreach ($entity->$field_name as $item) {
      $output[] = $this->builder->buildSection($item);
    }
    return $output;
  }

}
