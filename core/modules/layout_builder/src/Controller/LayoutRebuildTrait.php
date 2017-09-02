<?php

namespace Drupal\layout_builder\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides AJAX responses to rebuild the Layout Builder.
 */
trait LayoutRebuildTrait {

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
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]', $form));
    }
    else {
      $response = $this->rebuildAndClose(new AjaxResponse(), $this->entity);
    }
    return $response;
  }

  /**
   * Rebuilds the layout.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to either rebuild the layout and close the dialog, or
   *   reload the page.
   */
  protected function rebuildAndClose(AjaxResponse $response, EntityInterface $entity) {
    $response = $this->rebuildLayout($response, $entity);
    $url = Url::fromRoute("entity.{$entity->getEntityTypeId()}.layout", [$entity->getEntityTypeId() => $entity->id()]);
    return $this->closeLayout($response, $url);
  }

  /**
   * Rebuilds the layout.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to either rebuild the layout and close the dialog, or
   *   reload the page.
   */
  protected function rebuildLayout(AjaxResponse $response, EntityInterface $entity) {
    $layout_controller = $this->getClassResolver()->getInstanceFromDefinition(LayoutBuilderController::class);
    $layout = $layout_controller->layout($entity);
    $response->addCommand(new ReplaceCommand('#layout-builder', $layout));
    return $response;
  }

  /**
   * Returns to the layout builder.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The AJAX response.
   * @param \Drupal\Core\Url $url
   *   The URL to redirect to if not using a dialog.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response to either rebuild the layout and close the dialog, or
   *   reload the page.
   */
  protected function closeLayout(AjaxResponse $response, Url $url) {
    if ($this->isDialog()) {
      $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    }
    else {
      $response->addCommand(new RedirectCommand($url->setAbsolute()->toString()));
    }
    return $response;
  }

  /**
   * Determines if the current request is within a dialog.
   *
   * @return bool
   *   TRUE if the current request is within a dialog, FALSE otherwise.
   */
  protected function isDialog() {
    return $this->getRequest()->get(MainContentViewSubscriber::WRAPPER_FORMAT) === 'drupal_dialog.off_canvas';
  }

  /**
   * Gets the request object.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request object.
   */
  protected function getRequest() {
    if (!$this->requestStack) {
      $this->requestStack = \Drupal::requestStack();
    }
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Gets the class resolver.
   *
   * @return \Drupal\Core\DependencyInjection\ClassResolver
   *   The class resolver.
   */
  protected function getClassResolver() {
    if (!$this->classResolver) {
      $this->classResolver = \Drupal::classResolver();
    }
    return $this->classResolver;
  }

}
