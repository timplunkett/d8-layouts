<?php

namespace Drupal\layout_builder;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * @todo.
 */
class LayoutSectionBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutPluginManager;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The context manager service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Constructs a LayoutSectionFormatter object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface $layoutPluginManager
   *   The layout plugin manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   THe block plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The ContextHandler for applying contexts to conditions properly.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The lazy context repository service.
   */
  public function __construct(AccountInterface $account, LayoutPluginManagerInterface $layoutPluginManager, BlockManagerInterface $blockManager, ContextHandlerInterface $context_handler, ContextRepositoryInterface $context_repository) {
    $this->account = $account;
    $this->layoutPluginManager = $layoutPluginManager;
    $this->blockManager = $blockManager;
    $this->contextHandler = $context_handler;
    $this->contextRepository = $context_repository;
  }

  /**
   * Builds the render array for the layout section.
   *
   * @param string $layout_id
   *   The ID of the layout.
   * @param array $section
   *   An array of configuration, keyed first by region and then by block UUID.
   *
   * @return array
   *   The render array for a given section.
   */
  public function buildSection($layout_id, array $section) {
    $cacheability = CacheableMetadata::createFromRenderArray([]);

    $regions = [];
    foreach ($section as $region => $blocks) {
      foreach ($blocks as $uuid => $configuration) {
        $block = $this->getBlock($uuid, $configuration);

        $access = $block->access($this->account, TRUE);
        $cacheability->addCacheableDependency($access);

        if ($access->isAllowed()) {
          $regions[$region][$uuid] = $block->build();
          $cacheability->addCacheableDependency($block);
        }
      }
    }

    $layout = $this->layoutPluginManager->createInstance($layout_id);
    $section = $layout->build($regions);
    $cacheability->applyTo($section);
    return $section;
  }

  /**
   * Gets a block instance.
   *
   * @param string $uuid
   *   The UUID of this block instance.
   * @param array $configuration
   *   An array of configuration relevant to the block instance. Must contain
   *   the plugin ID with the key 'plugin_id'.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown when the configuration parameter does not contain 'plugin_id'.
   */
  protected function getBlock($uuid, array $configuration) {
    if (!isset($configuration['plugin_id'])) {
      throw new PluginException(sprintf('No plugin ID specified for block with "%s" UUID', $uuid));
    }

    $block = $this->blockManager->createInstance($configuration['plugin_id'], $configuration);
    if ($block instanceof ContextAwarePluginInterface) {
      $contexts = $this->contextRepository->getRuntimeContexts(array_values($block->getContextMapping()));
      $this->contextHandler->applyContextMapping($block, $contexts);
    }
    return $block;
  }

}
