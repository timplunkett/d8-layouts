<?php

namespace Drupal\outside_in;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Helper trait for forms that are used in the off-canvas dialog.
 *
 * Classes that use this trait should implement \Drupal\Core\Form\FormInterface.
 */
trait OffCanvasFormDialogTrait {

  /**
   * Is the current request for an AJAX modal dialog.
   *
   * @return bool
   *   TRUE is the current request if for an AJAX modal dialog.
   */
  protected function isModalDialog() {
    $wrapper_format = $this->getRequest()
      ->get(MainContentViewSubscriber::WRAPPER_FORMAT);
    return (in_array($wrapper_format, [
      'drupal_ajax',
      'drupal_modal',
      'drupal_dialog_off_canvas',
    ])) ? TRUE : FALSE;
  }

  /**
   * Add modal dialog support to a form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildFormDialog(array &$form, FormStateInterface $form_state) {
    if (!$this->isModalDialog()) {
      return;
    }

    $ajax_callback_added = FALSE;

    if (!empty($form['actions']['submit'])) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::submitFormDialog',
        'event' => 'click',
      ];
      $ajax_callback_added = TRUE;
    }

    if (!empty($form['actions']['cancel'])) {
      // Replace 'Cancel' link button with a close dialog button.
      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#submit' => ['::noSubmit'],
        '#limit_validation_errors' => [],
        '#weight' => 100,
        '#ajax' => [
          'callback' => '::closeDialog',
          'event' => 'click',
        ],
      ];
      $ajax_callback_added = TRUE;
    }

    if ($ajax_callback_added) {
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $form['#prefix'] = '<div id="off-canvas-form">';
      $form['#suffix'] = '</div>';
    }
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
    if ($form_state->hasAnyErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $response = new AjaxResponse();
      $response->addCommand(new HtmlCommand('#off-canvas-form', $form));
      // @todo Do we need the scroll to the top command from Webform?
      //$response->addCommand(new ScrollTopCommand('#off-canvas-form'));
      return $response;
    }
    else {
      $response = new AjaxResponse();
      if ($path = $this->getRedirectDestinationPath()) {
        $redirect_url = Url::fromUserInput('/' . $path)->setAbsolute()->toString();
        $command = new RedirectCommand($redirect_url);
        $response->addCommand($command);
      }
      elseif ($redirect_url = $this->getRedirectUrl($form_state)) {
        $response->addCommand(new RedirectCommand($redirect_url->toString()));
      }
      else {
        $response->addCommand(new CloseDialogCommand());
      }
      return $response;
    }
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
    $response = new AjaxResponse();
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
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
   * Get the form's redirect URL.
   *
   * Isolate a form's redirect URL/destination so that it can be used by
   * ::submitFormDialog or ::submitForm.
   *
   * @return \Drupal\Core\Url|null
   *   The redirect URL or NULL if dialog should just be closed.
   */
  protected function getRedirectUrl(FormStateInterface $form_state) {

    return $form_state->getRedirect() ?: $this->getDestinationUrl();
  }

  /**
   * Get the URL from the destination service.
   *
   * @return \Drupal\Core\Url|null
   *   The destination URL or NULL no destination available.
   */
  protected function getDestinationUrl() {
    if ($destination = $this->getRedirectDestinationPath()) {
      return Url::fromUserInput('/' . $destination);
    }

    return NULL;
  }

  /**
   * Get the redirect destination path if specified in request.
   *
   * @return string|null
   *   The redirect path or NULL if it is not specified.
   */
  protected function getRedirectDestinationPath() {
    if ($this->requestStack->getCurrentRequest()->get('destination')) {
      return $this->getRedirectDestination()->get();
    }
    return NULL;
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $this->buildFormDialog($form, $form_state);
    return $form;
  }

}
