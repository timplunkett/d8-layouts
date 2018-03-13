<?php

namespace Drupal\Core\Plugin\Discovery;

/**
 * @todo.
 *
 * @see \Drupal\Core\Plugin\DiscoveryFilterer
 */
interface DiscoveryFilterInterface {

  /**
   * Filters the plugin definitions for a specific type.
   *
   * @param string $type
   *   A string identifying the plugin type.
   * @param string $consumer
   *   A string identifying the consumer of these plugin definitions.
   * @param \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[] $definitions
   *   The array of plugin definitions.
   * @param mixed[] $context
   *   An associative array containing additional information provided by the
   *   code requesting the filtered definitins.
   *
   * @return \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[]
   *   The array of plugin definitions.
   */
  public function filter($type, $consumer, array $definitions, array $context);

}
