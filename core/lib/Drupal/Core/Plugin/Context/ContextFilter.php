<?php

namespace Drupal\Core\Plugin\Context;

/**
 * Provides methods to filter plugins with satisfied context requirements.
 */
class ContextFilter {

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
   * Returns a callable which can be used to filter plugin definitions.
   *
   * @param array $contexts
   *   An array of contexts to use in the filter.
   *
   * @return array
   *   A callable for filtering plugin definitions.
   */
  public static function getFilter(array $contexts) {
    return [new static($contexts), 'filter'];
  }

  /**
   * Determines plugins whose constraints are satisfied by our contexts.
   *
   * @param array $definitions
   *   An array of plugin definitions.
   *
   * @return array
   *   An array of plugin definitions.
   */
  public function filter(array $definitions) {
    return \Drupal::service('context.handler')->filterPluginDefinitionsByContexts($this->contexts, $definitions);
  }

}
