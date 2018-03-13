<?php

namespace Drupal\Core\Plugin\Context;

use Drupal\Core\Plugin\Discovery\DiscoveryFilterInterface;

/**
 * Provides methods to filter plugins with satisfied context requirements.
 */
class ContextFilter implements DiscoveryFilterInterface {

  /**
   * An array of contexts.
   *
   * @var array|\Drupal\Component\Plugin\Context\ContextInterface[]
   */
  protected $contexts;

  /**
   * ContextFilter constructor.
   *
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts.
   */
  public function __construct(array $contexts) {
    $this->contexts = $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function filter($type, $consumer, array $definitions, array $context) {
    return \Drupal::service('context.handler')->filterPluginDefinitionsByContexts($this->contexts, $definitions);
  }

}
