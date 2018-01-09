<?php

namespace Drupal\layout_builder\Plugin\SectionStorage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Url;
use Drupal\layout_builder\Routing\LayoutBuilderRoutesTrait;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionListInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageTrait;
use Drupal\layout_builder\SectionStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Stores sections for the whole page.
 *
 * @SectionStorage(
 *   id = "theme",
 * )
 */
class SectionPageStorage extends PluginBase implements SectionStorageInterface, ContainerFactoryPluginInterface {

  use LayoutBuilderRoutesTrait;
  use SectionStorageTrait;

  /**
   * The theme name.
   *
   * @var string
   */
  protected $themeName;

  /**
   * The sections for this theme.
   *
   * @var \Drupal\layout_builder\Section[]
   */
  protected $sections;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ThemeHandlerInterface $theme_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeHandler = $theme_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme_handler'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    return $this->sections;
  }

  /**
   * {@inheritdoc}
   */
  protected function setSections(array $sections) {
    $this->sections = $sections;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    return $this->themeName;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageType() {
    return 'theme';
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->themeHandler->getName($this->themeName);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $sections = array_map(function (Section $section) {
      return $section->toArray();
    }, $this->getSections());

    $this->configFactory->getEditable($this->themeName . '.layout_builder')
      ->set('sections', $sections)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getCanonicalUrl() {
    // Themes do not have a canonical URL, go to the Layout Builder UI.
    return $this->getLayoutBuilderUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl() {
    return Url::fromRoute('theme.layout_builder', ['theme_name' => $this->themeName]);
  }

  /**
   * {@inheritdoc}
   */
  public function setSectionList(SectionListInterface $section_list) {
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $this->buildRoute($collection, $this->getPluginDefinition(), 'theme', '/page-layout/{theme_name}', [], [], []);
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!$value && isset($defaults['theme_name'])) {
      $value = $defaults['theme_name'];
    }

    if ($value) {
      $sections = $this->configFactory->get("$value.layout_builder")->get('sections') ?: [];
      foreach ($sections as $section_delta => $section) {
        $sections[$section_delta] = new Section(
          $section['layout_id'],
          $section['layout_settings'],
          array_map(function (array $component) {
            return (new SectionComponent($component['uuid'], $component['region'], $component['configuration'], $component['additional']))->setWeight($component['weight']);
          }, $section['components'])
        );
      }

      $this->themeName = $value;
      $this->sections = $sections;
      return $this;
    }
  }

}
