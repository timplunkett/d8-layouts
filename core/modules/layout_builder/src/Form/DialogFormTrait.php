<?php

namespace Drupal\layout_builder\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides utilities for forms that want to be rendered in dialogs.
 */
trait DialogFormTrait {

  /**
   * Adds dialog support to a form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param bool $create_cancel
   *   If TRUE the create submit button will be created.
   * @param string $dialog_selector
   *   The CSS selector for the associated dialog.
   */
  protected function buildFormDialog(array &$form, FormStateInterface $form_state, $create_cancel = FALSE, $dialog_selector = '#drupal-modal') {
    if (!$this->isDialog()) {
      return;
    }
    $form_state->set('dialog_selector', $dialog_selector);

    $ajax_callback_added = FALSE;

    if (!empty($form['actions']['submit'])) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::submitFormDialog',
        'event' => 'click',
      ];
      $ajax_callback_added = TRUE;
    }

    if ($create_cancel) {
      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#weight' => 100,
      ];
    }
    if (!empty($form['actions']['cancel'])) {
      // Replace 'Cancel' link button with a close dialog button.
      $form['actions']['cancel'] = [
        '#submit' => ['::noSubmit'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::closeDialog',
          'event' => 'click',
        ],
      ] + $form['actions']['cancel'];
      $ajax_callback_added = TRUE;
    }

    if ($ajax_callback_added) {
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $form['#attributes']['id'] = 'dialog-form';
    }
  }

  /**
   * Empty submit #ajax submit callback.
   *
   * This allows modal dialog to using ::submitCallback to validate and submit
   * the form via one ajax required.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function noSubmit(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Determines if the current request is for an AJAX dialog.
   *
   * @return bool
   *   TRUE is the current request if for an AJAX dialog.
   */
  protected function isDialog() {
    return in_array($this->getRequestWrapperFormat(), [
      'drupal_ajax',
      'drupal_dialog',
      'drupal_modal',
      'drupal_dialog.off_canvas',
    ]);
  }

  /**
   * Submit form dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages or redirects
   *   to a URL
   */
  public function submitFormDialog(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $command = new ReplaceCommand('#dialog-form', $form);
    }
    else {
      if ($redirect_url = $this->getRedirectUrl()) {
        $command = new RedirectCommand($redirect_url->setAbsolute()->toString());
      }
      else {
        return $this->closeDialog($form, $form_state);
      }
    }
    return $response->addCommand($command);
  }

  /**
   * Close dialog #ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool|\Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response that display validation error messages.
   */
  public function closeDialog(array &$form, FormStateInterface $form_state) {
    $selector = $form_state->get('dialog_selector');
    return (new AjaxResponse())->addCommand(new CloseDialogCommand($selector));
  }

  /**
   * @return mixed
   */
  protected function getRequestWrapperFormat() {
    $wrapper_format = $this->getRequest()
      ->get(MainContentViewSubscriber::WRAPPER_FORMAT);
    return $wrapper_format;
  }

  /**
   * Gets the form's redirect URL.
   *
   * Isolate a form's redirect URL/destination so that it can be used by
   * ::submitFormDialog or ::submitForm.
   *
   * @return \Drupal\Core\Url|null
   *   The redirect URL or NULL if dialog should just be closed.
   */
  protected function getRedirectUrl() {
    return $this->getDestinationUrl();
  }

  /**
   * Gets the URL from the destination service.
   *
   * @return \Drupal\Core\Url|null
   *   The destination URL or NULL no destination available.
   */
  protected function getDestinationUrl() {
    if ($destination = $this->getRedirectDestinationPath()) {
      $options = UrlHelper::parse($destination);
      return Url::fromUserInput('/' . ltrim($options['path'], '/'), $options);
    }
  }

  /**
   * Gets the redirect destination path if specified in request.
   *
   * \Drupal\Core\Routing\RedirectDestination::get() cannot be used directly
   * because it will use <current> if 'destination' is not in the query string.
   *
   * @return string|null
   *   The redirect path or NULL if it is not specified.
   */
  protected function getRedirectDestinationPath() {
    if ($this->requestStack->getCurrentRequest()->get('destination')) {
      return $this->getRedirectDestination()->get();
    }
  }

}
