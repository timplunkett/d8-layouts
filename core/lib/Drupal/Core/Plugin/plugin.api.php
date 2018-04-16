<?php

/**
 * @file
 * Hooks provided by the Plugin system.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the filtering of plugin definitions for a specific type.
 *
 * @param \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[] $definitions
 *   The array of plugin definitions.
 * @param mixed[] $extra
 *   An associative array containing additional information provided by the code
 *   requesting the filtered definitions.
 * @param string $consumer
 *   A string identifying the consumer of these plugin definitions.
 */
function hook_plugin_filter_TYPE_alter(array &$definitions, array $extra, $consumer) {
  // Explicitly remove the "Help" block from the Block UI list.
  if ($consumer == 'block_ui') {
    unset($definitions['help_block']);
  }
}

/**
 * Alter the filtering of plugin definitions for a specific type and consumer.
 *
 * @param \Drupal\Component\Plugin\Definition\PluginDefinitionInterface[]|array[] $definitions
 *   The array of plugin definitions.
 * @param mixed[] $extra
 *   An associative array containing additional information provided by the code
 *   requesting the filtered definitions.
 */
function hook_plugin_filter_TYPE__CONSUMER_alter(array &$definitions, array $extra) {
  // Explicitly remove the "Help" block from the list.
  unset($definitions['help_block']);
}

/**
 * @} End of "addtogroup hooks".
 */
