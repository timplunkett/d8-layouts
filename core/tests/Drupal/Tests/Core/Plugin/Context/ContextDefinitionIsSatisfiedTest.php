<?php

namespace Drupal\Tests\Core\Plugin\Context;

use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\Core\Validation\ConstraintManager;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\Core\Plugin\Context\ContextDefinition
 * @group Plugin
 */
class ContextDefinitionIsSatisfiedTest extends UnitTestCase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $namespaces = new \ArrayObject([
      'Drupal\\Core\\TypedData' => $this->root . '/core/lib/Drupal/Core/TypedData',
      'Drupal\\Core\\Validation' => $this->root . '/core/lib/Drupal/Core/Validation',
      'Drupal\\Core\\Entity' => $this->root . '/core/lib/Drupal/Core/Entity',
    ]);
    $cache_backend = new NullBackend('cache');
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);

    $class_resolver = $this->prophesize(ClassResolverInterface::class);
    $class_resolver->getInstanceFromDefinition(Argument::type('string'))->will(function ($arguments) {
      $class_name = $arguments[0];
      return new $class_name();
    });

    $type_data_manager = new TypedDataManager($namespaces, $cache_backend, $module_handler->reveal(), $class_resolver->reveal());
    $type_data_manager->setValidationConstraintManager(new ConstraintManager($namespaces, $cache_backend, $module_handler->reveal()));

    $this->entityTypeManager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityManager = $this->prophesize(EntityManagerInterface::class);

    $this->entityTypeBundleInfo = $this->prophesize(EntityTypeBundleInfoInterface::class);

    $container = new ContainerBuilder();
    $container->set('typed_data_manager', $type_data_manager);
    $container->set('entity_type.manager', $this->entityTypeManager->reveal());
    $container->set('entity.manager', $this->entityManager->reveal());
    $container->set('entity_type.bundle.info', $this->entityTypeBundleInfo->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Asserts that the requirement is satisfied as expected.
   *
   * @param bool $expected
   *   The expected outcome.
   * @param \Drupal\Core\Plugin\Context\ContextDefinition $requirement
   *   The requirement to check against.
   * @param \Drupal\Core\Plugin\Context\ContextDefinition $definition
   *   The context definition to check.
   * @param mixed $value
   *   (optional) The value to set on the context, defaults to NULL.
   */
  protected function assertRequirementIsSatisfied($expected, ContextDefinition $requirement, ContextDefinition $definition, $value = NULL) {
    $context = new Context($definition, $value);
    $this->assertSame($expected, $requirement->isSatisfiedBy($context));
  }

  /**
   * @covers ::isSatisfiedBy
   * @covers ::getSampleValues
   * @covers ::getConstraintObjects
   *
   * @dataProvider providerTestIsSatisfiedBy
   */
  public function testIsSatisfiedBy($expected, ContextDefinition $requirement, ContextDefinition $definition, $value = NULL) {
    $entity_storage = $this->prophesize(EntityStorageInterface::class);
    $content_entity_storage = $this->prophesize(ContentEntityStorageInterface::class);
    $this->entityTypeManager->getStorage('test_config')->willReturn($entity_storage->reveal());
    $this->entityTypeManager->getStorage('test_content')->willReturn($content_entity_storage->reveal());
    $this->entityManager->getDefinitions()->willReturn([
      'test_config' => new EntityType(['id' => 'test_config']),
      'test_content' => new EntityType(['id' => 'test_content']),
    ]);
    $this->entityTypeBundleInfo->getBundleInfo('test_config')->willReturn([]);
    $this->entityTypeBundleInfo->getBundleInfo('test_content')->willReturn([]);

    $this->assertRequirementIsSatisfied($expected, $requirement, $definition, $value);
  }

  /**
   * Provides test data for ::testIsSatisfiedBy().
   */
  public function providerTestIsSatisfiedBy() {
    $data = [];

    // Simple data types.
    $data['both any'] = [
      TRUE,
      new ContextDefinition('any'),
      new ContextDefinition('any'),
    ];
    $data['requirement any'] = [
      TRUE,
      new ContextDefinition('any'),
      new ContextDefinition('integer'),
    ];
    $data['integer, out of range'] = [
      FALSE,
      (new ContextDefinition('integer'))->addConstraint('Range', ['min' => 0, 'max' => 10]),
      new ContextDefinition('integer'),
      20,
    ];
    $data['integer, within range'] = [
      TRUE,
      (new ContextDefinition('integer'))->addConstraint('Range', ['min' => 0, 'max' => 10]),
      new ContextDefinition('integer'),
      5,
    ];
    $data['integer, no value'] = [
      TRUE,
      (new ContextDefinition('integer'))->addConstraint('Range', ['min' => 0, 'max' => 10]),
      new ContextDefinition('integer'),
    ];
    $data['non-integer, within range'] = [
      FALSE,
      (new ContextDefinition('integer'))->addConstraint('Range', ['min' => 0, 'max' => 10]),
      new ContextDefinition('any'),
      5,
    ];

    // Entities without bundles.
    $data['content entity, matching type, no value'] = [
      TRUE,
      new ContextDefinition('entity:test_content'),
      new ContextDefinition('entity:test_content'),
    ];
    $entity = $this->prophesize(ContentEntityInterface::class)->willImplement(\IteratorAggregate::class);
    $entity->getIterator()->willReturn(new \ArrayIterator([]));
    $entity->getCacheContexts()->willReturn([]);
    $entity->getCacheTags()->willReturn([]);
    $entity->getCacheMaxAge()->willReturn(0);
    $entity->getEntityTypeId()->willReturn('test_content');
    $data['content entity, matching type, correct value'] = [
      TRUE,
      new ContextDefinition('entity:test_content'),
      new ContextDefinition('entity:test_content'),
      $entity->reveal(),
    ];
    $data['content entity, incorrect manual constraint'] = [
      TRUE,
      new ContextDefinition('entity:test_content'),
      (new ContextDefinition('entity:test_content'))->addConstraint('EntityType', 'test_config'),
    ];
    $data['config entity, matching type, no value'] = [
      TRUE,
      new ContextDefinition('entity:test_config'),
      new ContextDefinition('entity:test_config'),
    ];

    return $data;
  }

  /**
   * @covers ::isSatisfiedBy
   * @covers ::getSampleValues
   * @covers ::getConstraintObjects
   *
   * @dataProvider providerTestIsSatisfiedByGenerateBundledEntity
   */
  public function testIsSatisfiedByGenerateBundledEntity($expected, array $requirement_bundles, array $candidate_bundles, array $bundles_to_instantiate = NULL) {
    // If no bundles are explicitly specified, instantiate all bundles.
    if (!$bundles_to_instantiate) {
      $bundles_to_instantiate = $candidate_bundles;
    }

    $content_entity_storage = $this->prophesize(ContentEntityStorageInterface::class);
    foreach ($bundles_to_instantiate as $bundle) {
      $entity = $this->prophesize(ContentEntityInterface::class)->willImplement(\IteratorAggregate::class);
      $entity->getEntityTypeId()->willReturn('test_content');
      $entity->getIterator()->willReturn(new \ArrayIterator([]));
      $entity->bundle()->willReturn($bundle);
      $content_entity_storage->createWithSampleValues($bundle)
        ->willReturn($entity->reveal())
        ->shouldBeCalled();
    }

    $this->entityTypeManager->getStorage('test_content')->willReturn($content_entity_storage->reveal());
    $this->entityManager->getDefinitions()->willReturn([
      'test_content' => new EntityType(['id' => 'test_content']),
    ]);

    $this->entityTypeBundleInfo->getBundleInfo('test_content')->willReturn([
      'first_bundle' => ['label' => 'First bundle'],
      'second_bundle' => ['label' => 'Second bundle'],
      'third_bundle' => ['label' => 'Third bundle'],
    ]);

    $requirement = new ContextDefinition('entity:test_content');
    if ($requirement_bundles) {
      $requirement->addConstraint('Bundle', $requirement_bundles);
    }
    $definition = (new ContextDefinition('entity:test_content'))->addConstraint('Bundle', $candidate_bundles);
    $this->assertRequirementIsSatisfied($expected, $requirement, $definition);
  }

  /**
   * Provides test data for ::testIsSatisfiedByGenerateBundledEntity().
   */
  public function providerTestIsSatisfiedByGenerateBundledEntity() {
    $data = [];
    $data['no requirement'] = [
      TRUE,
      [],
      ['first_bundle'],
    ];
    $data['single requirement'] = [
      TRUE,
      ['first_bundle'],
      ['first_bundle'],
    ];
    $data['single requirement, multiple candidates, satisfies last candidate'] = [
      TRUE,
      ['third_bundle'],
      ['first_bundle', 'second_bundle', 'third_bundle'],
    ];
    $data['single requirement, multiple candidates, satisfies first candidate'] = [
      TRUE,
      ['first_bundle'],
      ['first_bundle', 'second_bundle', 'third_bundle'],
      // Once the first match is found, subsequent candidates are not checked.
      ['first_bundle'],
    ];
    $data['unsatisfied requirement'] = [
      FALSE,
      ['second_bundle'],
      ['first_bundle', 'third_bundle'],
    ];
    $data['multiple requirements'] = [
      TRUE,
      ['first_bundle', 'second_bundle'],
      ['first_bundle'],
    ];
    return $data;
  }

  /**
   * @covers ::isSatisfiedBy
   * @covers ::getSampleValues
   * @covers ::getConstraintObjects
   *
   * @dataProvider providerTestIsSatisfiedByPassBundledEntity
   */
  public function testIsSatisfiedByPassBundledEntity($expected, $requirement_constraint) {
    $this->entityManager->getDefinitions()->willReturn([
      'test_content' => new EntityType(['id' => 'test_content']),
    ]);
    $this->entityTypeManager->getStorage('test_content')->shouldNotBeCalled();

    $this->entityTypeBundleInfo->getBundleInfo('test_content')->willReturn([
      'first_bundle' => ['label' => 'First bundle'],
      'second_bundle' => ['label' => 'Second bundle'],
      'third_bundle' => ['label' => 'Third bundle'],
    ]);

    $entity = $this->prophesize(ContentEntityInterface::class)->willImplement(\IteratorAggregate::class);
    $entity->getEntityTypeId()->willReturn('test_content');
    $entity->getIterator()->willReturn(new \ArrayIterator([]));
    $entity->getCacheContexts()->willReturn([]);
    $entity->getCacheTags()->willReturn([]);
    $entity->getCacheMaxAge()->willReturn(0);
    $entity->bundle()->willReturn('third_bundle');

    $requirement = new ContextDefinition('entity:test_content');
    if ($requirement_constraint) {
      $requirement->addConstraint('Bundle', $requirement_constraint);
    }
    $definition = new ContextDefinition('entity:test_content');
    $this->assertRequirementIsSatisfied($expected, $requirement, $definition, $entity->reveal());
  }

  /**
   * Provides test data for ::testIsSatisfiedByPassBundledEntity().
   */
  public function providerTestIsSatisfiedByPassBundledEntity() {
    $data = [];
    $data[] = [TRUE, []];
    $data[] = [FALSE, ['first_bundle']];
    $data[] = [FALSE, ['second_bundle']];
    $data[] = [TRUE, ['third_bundle']];
    $data[] = [TRUE, ['first_bundle', 'second_bundle', 'third_bundle']];
    $data[] = [FALSE, ['first_bundle', 'second_bundle']];
    $data[] = [TRUE, ['first_bundle', 'third_bundle']];
    $data[] = [TRUE, ['second_bundle', 'third_bundle']];
    return $data;
  }

}

namespace Drupal\Core\Validation;

if (!function_exists('t')) {
  function t($string, array $args = []) {
    return strtr($string, $args);
  }
}
