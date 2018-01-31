<?php

namespace Drupal\layout_builder\Plugin\DisplayVariant;

use Drupal\Core\Display\ContextAwareVariantInterface;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a section-based page display variant.
 *
 * @PageDisplayVariant(
 *   id = "layout_builder__section",
 *   admin_label = @Translation("Section-based")
 * )
 */
class SectionDisplayVariant extends VariantBase implements PageVariantInterface, ContainerFactoryPluginInterface, ContextAwareVariantInterface {

  /**
   * The render array representing the main content.
   *
   * @var array
   */
  protected $mainContent = [];

  /**
   * An array of collected contexts.
   *
   * This is only used on runtime, and is not stored.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]
   */
  protected $contexts = [];

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  protected $title = '';

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * SectionDisplayVariant constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SectionStorageManagerInterface $section_storage_manager, ThemeManagerInterface $theme_manager, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sectionStorageManager = $section_storage_manager;
    $this->themeManager = $theme_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('theme.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content) {
    $this->mainContent = $main_content;
    $this->contexts['main_content'] = new Context(new ContextDefinition('string', 'Main Content'), $main_content);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    $this->contexts['title'] = new Context(new ContextDefinition('string', 'Title'), $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->routeMatch->getRouteName() !== 'layout_builder.theme.view') {
      $section_storage = $this->sectionStorageManager->loadFromRoute('theme', $this->themeManager->getActiveTheme()->getName(), [], '', []);
      if ($sections = $section_storage->getSections()) {
        $contexts = $this->getContexts();
        $build['content']['sections'] = array_map(function (Section $section) use ($contexts) {
          return $section->toRenderArray($contexts);
        }, $sections);
        return $build;
      }
    }

    return [
      'content' => [
        'messages' => [
          '#type' => 'status_messages',
          '#weight' => -1000,
        ],
        'page_title' => [
          '#type' => 'page_title',
          '#title' => $this->title,
          '#weight' => -900,
        ],
        'main_content' => ['#weight' => -800] + $this->mainContent,
      ],
    ];
  }

  /**
   * Gets the contexts.
   *
   * @return \Drupal\Component\Plugin\Context\ContextInterface[]
   *   An array of set contexts, keyed by context name.
   */
  public function getContexts() {
    return $this->contexts;
  }

  /**
   * Sets the contexts.
   *
   * @param \Drupal\Component\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts, keyed by context name.
   *
   * @return $this
   */
  public function setContexts(array $contexts) {
    $this->contexts += $contexts;
    return $this;
  }

}
