<?php

namespace Drupal\layout_builder;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * @todo.
 */
class LayoutSectionBuilder {
  use StringTranslationTrait;

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
    $weight = 0;
    foreach ($section as $region => $blocks) {
      // @todo determine if config should at least always be an empty array.
      foreach ($blocks as $uuid => $configuration) {
        $block = $this->getBlock($uuid, $configuration);

        $access = $block->access($this->account, TRUE);
        $cacheability->addCacheableDependency($access);

        if ($access->isAllowed()) {
          $regions[$region][$uuid] = [
            '#theme' => 'block',
            '#attributes' => [
              'class' => ['draggable'],
            ],
            '#contextual_links' => [],
            '#weight' => $weight++,
            '#configuration' => $block->getConfiguration(),
            '#plugin_id' => $block->getPluginId(),
            '#base_plugin_id' => $block->getBaseId(),
            '#derivative_plugin_id' => $block->getDerivativeId(),
          ];
          $regions[$region][$uuid]['content'] = $block->build();
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
   *
   */
  public function buildAdministrativeSection($layout_id, array $section, $entity_type, $entity_id, $delta) {
    $cacheability = CacheableMetadata::createFromRenderArray([]);

    $regions = [];
    // @todo load a layout, figure out its regions, add a block add link to all regions.
    $layout = $this->layoutPluginManager->getDefinition($layout_id);
    foreach ($layout->getRegions() as $region => $info) {
      $url = new Url(
        'layout_builder.choose_block',
        [
          'entity_type' => $entity_type,
          'entity' => $entity_id,
          'delta' => $delta,
          'region' => $region,
        ],
        [
          'attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'dialog',
            'data-dialog-renderer' => 'off_canvas',
          ],
        ]
      );
      $link = new Link($this->t('Add Block'), $url);
      $regions[$region]['layout_builder_add_block'] = $link->toRenderable();
      $regions[$region]['layout_builder_add_block']['#prefix'] = "<div class=\"add-block\">";
      $regions[$region]['layout_builder_add_block']['#suffix'] = "</div>";
    }
    foreach ($section as $region => $blocks) {
      $weight = 0;
      foreach ($blocks as $uuid => $configuration) {
        $block = $this->getBlock($uuid, $configuration);
        $access = $block->access($this->account, TRUE);
        $cacheability->addCacheableDependency($access);

        // @todo Figure out how to handle blocks a user doesn't have access to
        // during administration.
        if ($access->isAllowed()) {
          $regions[$region][$uuid] = [
            '#theme' => 'block',
            '#attributes' => [
              'class' => ['draggable'],
              'data-layout-block-uuid' => $uuid,
            ],
            '#contextual_links' => [],
            '#weight' => $weight++,
            '#configuration' => $block->getConfiguration(),
            '#plugin_id' => $block->getPluginId(),
            '#base_plugin_id' => $block->getBaseId(),
            '#derivative_plugin_id' => $block->getDerivativeId(),
          ];

          // Build the block and bubble its attributes up if possible. This
          // allows modules like Quickedit to function.
          // See \Drupal\block\BlockViewBuilder::preRender() for reference.
          $content = $block->build();
          if ($content !== NULL && !Element::isEmpty($content)) {
            foreach (['#attributes', '#contextual_links'] as $property) {
              if (isset($content[$property])) {
                $regions[$region][$uuid][$property] += $content[$property];
                unset($content[$property]);
              }
            }
          }

          // If the block is empty, instead of trying to render the block
          // correctly return just #cache, so that the render system knows the
          // reasons (cache contexts & tags) why this block is empty.
          if ($content && Element::isEmpty($content)) {
            $cacheable_metadata = CacheableMetadata::createFromRenderArray($regions[$region][$uuid]);
            $cacheable_metadata->merge(CacheableMetadata::createFromRenderArray($content['#cache']));
            $cacheable_metadata->applyTo($regions[$region][$uuid]);
          }

          $regions[$region][$uuid]['#contextual_links'] = [
            'layout_builder_block' => [
              'route_parameters' => [
                'entity_type' => $entity_type,
                'entity' => $entity_id,
                'delta' => $delta,
                'region' => $region,
                'uuid' => $uuid,
                'plugin_id' => $uuid,
              ],
            ],
          ];
          $regions[$region][$uuid]['content'] = $content;
          // @todo cacheability in the administration? is that a thing?
          $cacheability->addCacheableDependency($block);
        }
      }
    }

    $layout = $this->layoutPluginManager->createInstance($layout_id);
    $section = $layout->build($regions);
    foreach (Element::children($section) as $name) {
      $section[$name]['#attributes']['data-region'] = $name;
    }

    $section['#attributes']['data-layout-update-url'] = Url::fromRoute('layout_builder.move_block', [
      'entity_type' => $entity_type,
      'entity' => $entity_id,
    ])->toString();
    $section['#attributes']['data-layout-delta'] = $delta;

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
   *   the plugin ID with the key 'id'.
   *
   * @return \Drupal\Core\Block\BlockPluginInterface
   *   The block instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown when the configuration parameter does not contain 'id'.
   */
  protected function getBlock($uuid, array $configuration) {
    if (!isset($configuration['id'])) {
      throw new PluginException(sprintf('No plugin ID specified for block with "%s" UUID', $uuid));
    }

    $block = $this->blockManager->createInstance($configuration['id'], $configuration);
    if ($block instanceof ContextAwarePluginInterface) {
      $contexts = $this->contextRepository->getRuntimeContexts(array_values($block->getContextMapping()));
      $this->contextHandler->applyContextMapping($block, $contexts);
    }
    return $block;
  }

}
