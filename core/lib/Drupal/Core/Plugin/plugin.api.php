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
  // Remove the "Help" block from the Block UI list.
  if ($consumer == 'block_ui') {
    unset($definitions['help_block']);
  }

  // If the theme is specified, remove the branding block from the Bartik theme.
  if (isset($extra['theme']) && $extra['theme'] === 'bartik') {
    unset($definitions['system_branding_block']);
  }

  // Remove the "Main page content" block from everywhere.
  unset($definitions['system_main_block']);
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
  // Explicitly remove the "Help" block for this consumer.
  unset($definitions['help_block']);
}

/**
 * @} End of "addtogroup hooks".
 */
