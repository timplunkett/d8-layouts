<?php

namespace Drupal\layout_builder\Entity;

use Drupal\Core\Entity\Entity\EntityViewDisplay as BaseEntityViewDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\layout_builder\Section;

/**
 * Provides an entity view display entity that has a layout.
 *
 * @internal
 */
class LayoutBuilderEntityViewDisplay extends BaseEntityViewDisplay implements LayoutEntityDisplayInterface {

  /**
   * {@inheritdoc}
   */
  public function isOverridable() {
    return $this->getThirdPartySetting('layout_builder', 'allow_custom', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function setOverridable($overridable = TRUE) {
    $this->setThirdPartySetting('layout_builder', 'allow_custom', $overridable);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    return $this->getThirdPartySetting('layout_builder', 'sections', []);
  }

  /**
   * Store the information for all sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   The sections information.
   *
   * @return $this
   */
  protected function setSections(array $sections) {
    $this->setThirdPartySetting('layout_builder', 'sections', $sections);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    return count($this->getSections());
  }

  /**
   * {@inheritdoc}
   */
  public function getSection($delta) {
    $sections = $this->getSections();
    if (!isset($sections[$delta])) {
      throw new \OutOfBoundsException(sprintf('Invalid delta "%s" for the "%s" entity', $delta, $this->id()));
    }
    return $sections[$delta];
  }

  /**
   * Sets the section for the given delta on the display.
   *
   * @param int $delta
   *   The delta of the section.
   * @param \Drupal\layout_builder\Section $section
   *   The layout section.
   *
   * @return $this
   */
  protected function setSection($delta, Section $section) {
    $sections = $this->getSections();
    $sections[$delta] = $section;
    $this->setSections($sections);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function appendSection(Section $section) {
    $delta = $this->count();

    $this->setSection($delta, $section);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function insertSection($delta, Section $section) {
    $sections = $this->getSections();
    if (isset($sections[$delta])) {
      $start = array_slice($sections, 0, $delta);
      $end = array_slice($sections, $delta);
      $this->setSections(array_merge($start, [$section], $end));
    }
    else {
      $this->appendSection($section);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeSection($delta) {
    $sections = $this->getSections();
    unset($sections[$delta]);
    $this->setSections(array_values($sections));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $route_parameters = parent::urlRouteParameters($rel);

    // @todo Move this to \Drupal\Core\Entity\EntityDisplayBase.
    $entity_type = $this->entityTypeManager()->getDefinition($this->getTargetEntityTypeId());
    $bundle_parameter_key = $entity_type->getBundleEntityType() ?: 'bundle';
    $route_parameters[$bundle_parameter_key] = $this->getTargetBundle();
    return $route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  protected function linkTemplates() {
    $link_templates = parent::linkTemplates();
    $link_templates[$this->getTargetEntityTypeId() . '.layout-builder'] = TRUE;
    return $link_templates;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'edit-form', array $options = []) {
    if ($rel === 'canonical') {
      $rel = 'layout-builder';
    }

    if ($rel === 'layout-builder') {
      $rel = $this->getTargetEntityTypeId() . '.' . $rel;
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::preSave($storage, $update);

    // Ensure the plugin configuration is updated. Once layouts are no longer
    // stored as third party settings, this will be handled by the code in
    // \Drupal\Core\Config\Entity\ConfigEntityBase::preSave() that handles
    // \Drupal\Core\Entity\EntityWithPluginCollectionInterface.
    foreach ($this->getSections() as $delta => $section) {
      $this->setSection($delta, $section);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultRegion($delta = 0) {
    $sections = $this->getSections();
    if (!isset($sections[$delta])) {
      return parent::getDefaultRegion();
    }

    $section = $this->getSection($delta);
    return $this->getLayoutDefinition($section->getLayoutId())->getDefaultRegion();
  }

  /**
   * Gets a layout definition.
   *
   * @param string $layout_id
   *   The layout ID.
   *
   * @return \Drupal\Core\Layout\LayoutDefinition
   *   The layout definition.
   */
  protected function getLayoutDefinition($layout_id) {
    return $this->layoutPluginManager()->getDefinition($layout_id);
  }

  /**
   * Wraps the layout plugin manager.
   *
   * @return \Drupal\Core\Layout\LayoutPluginManagerInterface
   *   The layout plugin manager.
   */
  protected function layoutPluginManager() {
    return \Drupal::service('plugin.manager.core.layout');
  }

  /**
   * {@inheritdoc}
   */
  public function buildMultiple(array $entities) {
    $build_list = parent::buildMultiple($entities);

    foreach ($entities as $id => $entity) {
      $sections = $this->getRuntimeSections($entity);
      if ($sections) {
        foreach ($build_list[$id] as $name => $build_part) {
          $field_definition = $this->getFieldDefinition($name);
          if ($field_definition && $field_definition->isDisplayConfigurable('view')) {
            unset($build_list[$id][$name]);
          }
        }

        foreach ($sections as $delta => $section) {
          $build_list[$id]['_layout_builder'][$delta] = $section->toRenderArray();
        }
      }
    }

    return $build_list;
  }

  /**
   * Gets the runtime sections for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\layout_builder\Section[]
   *   The sections.
   */
  protected function getRuntimeSections(EntityInterface $entity) {
    if ($this->isOverridable() && !$entity->layout_builder__layout->isEmpty()) {
      return $entity->layout_builder__layout->getSections();
    }

    return $this->getSections();
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageType() {
    return 'defaults';
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageId() {
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getCanonicalUrl() {
    // Defaults do not have a canonical URL, go to the Layout Builder UI.
    return $this->getLayoutBuilderUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutBuilderUrl() {
    return $this->toUrl('layout-builder');
  }

}
