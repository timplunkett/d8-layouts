<?php

namespace Drupal\Core\Plugin\Context;

/**
 * @todo.
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
  public static function getFilter(array $contexts) {
    return [new static($contexts), 'filter'];
  }

  public function filter(array $definitions) {
    return \Drupal::service('context.handler')->filterPluginDefinitionsByContexts($this->contexts, $definitions);
  }

}
