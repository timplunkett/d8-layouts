<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to confirm the removal of a block.
 */
class RemoveBlockForm extends LayoutRebuildConfirmFormBase {

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The UUID of the block being removed.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to remove this block?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_remove_block';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $entity_id = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->region = $region;
    $this->uuid = $uuid;
    return parent::buildForm($form, $form_state, $entity_type_id, $entity_id, $delta);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleEntity(EntityInterface $entity, FormStateInterface $form_state) {
    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->layout_builder__layout->get($this->delta);
    $values = $field->section;
    unset($values[$this->region][$this->uuid]);
    $field->section = $values;
  }

}
