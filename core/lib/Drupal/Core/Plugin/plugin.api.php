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
 * Alter the sorting and filtering of plugin definitions for a specific type.
 *
 * @param callable[] $filters
 *   An array of callables to filter the definitions.
 * @param callable[] $sorts
 *   An array of callables to sort the definitions.
 * @param string $consumer
 *   A string identifying the consumer of these plugin definitions.
 */
function hook_plugin_definition_PLUGIN_TYPE_alter(array &$filters, array &$sorts, $consumer) {
  $filters[] = function ($definitions) {
    // Explicitly remove the "Help" blocks from the list.
    unset($definitions['help_block']);

    // Define which fields we want to remove from the list.
    $disallowed_fields = [
      'revision_timestamp',
      'vid',
      'revision_log',
      'revision_uid',
      'sticky',
      'title',
      'uid',
      'created',
      'changed',
      'type',
      'revision_default',
      'default_langcode',
      'langcode',
      'nid',
      'promote',
      'status',
      'vid',
      'revision_translation_affected',
    ];

    foreach ($definitions as $plugin_id => $definition) {
      // Field block IDs are in the form 'field_block:{entity}:{bundle}:{name}',
      // for example 'field_block:node:article:revision_timestamp'.
      preg_match('/field_block:.*:.*:(.*)/', $plugin_id, $parts);
      if (isset($parts[1]) && in_array($parts[1], $disallowed_fields, TRUE)) {
        // Unset any field blocks that match our predefined list.
        unset($definitions[$plugin_id]);
      }
    }
    return $definitions;
  };
}
