<?php

namespace Drupal\off_canvas_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\outside_in\OffCanvasFormDialogTrait;

/**
 * Just a test form.
 */
class TestForm extends FormBase {

  use OffCanvasFormDialogTrait;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "off_canvas_test_form";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['data-off-canvas-form'] = TRUE;
    $form['force_error'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force error?'),
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
      'cancel' => [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
      ],
    ];
    $this->buildFormDialog($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('force_error')) {
      $form_state->setErrorByName('force_error', 'Validation error');
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message('submitted');
  }

}
