<?php

namespace Drupal\layout_builder;

/**
 * Provides a wrapper around getting contexts from a section storage object.
 */
trait LayoutBuilderContextTrait {

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Gets the context repository service.
   *
   * @return \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   *   The context repository service.
   */
  protected function contextRepository() {
    if (!$this->contextRepository) {
      $this->contextRepository = \Drupal::service('context.repository');
    }
    return $this->contextRepository;
  }

  /**
   * Provides all available contexts, both global and section_storage-specific.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The array of context objects.
   */
  protected function getAvailableContexts(SectionStorageInterface $section_storage) {
    // Get all globally available contexts.
    $contexts = $this->contextRepository()->getAvailableContexts();
    // The node context is always declared as being available, even if it will
    // not be available. This is necessary for other systems like core's block
    // module, but Layout Builder expects that available contexts will always be
    // available during runtime.
    unset($contexts['@node.node_route_context:node']);
    // We should only rely on the context repository for contexts with globally
    // available values. Any conditionally available value isn't usable within
    // the layout builder.
    foreach ($contexts as $context_id => $context) {
      if (!$context->hasContextValue()) {
        unset($contexts[$context_id]);
      }
    }

    // Add in the per-section_storage contexts.
    $contexts += $section_storage->getAvailableContexts();
    return $contexts;
  }

}
