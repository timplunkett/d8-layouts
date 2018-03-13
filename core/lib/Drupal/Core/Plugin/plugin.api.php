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
 * @param Callable[] $filters
 *   An array of callables to filter the definitions.
 * @param mixed[] $context
 *   An associative array containing additional information provided by the code
 *   requesting the filtered definitins.
 */
function hook_plugin_filter_TYPE_alter(array &$filters, array $context) {
  $filters[] = function ($definitions) {
    // Explicitly remove the "Help" blocks from the list.
    unset($definitions['help_block']);
    return $definitions;
  };
}

/**
 * Alter the filtering of plugin definitions for a specific type and consumer.
 *
 * @param Callable[] $filters
 *   An array of callables to filter the definitions.
 * @param mixed[] $context
 *   An associative array containing additional information provided by the code
 *   requesting the filtered definitins.
 */
function hook_plugin_filter_TYPE__CONSUMER_alter(array &$filters, array $context) {
  $filters[] = function ($definitions) {
    // Explicitly remove the "Help" blocks from the list.
    unset($definitions['help_block']);
    return $definitions;
  };
}

/**
 * @} End of "addtogroup hooks".
 */
