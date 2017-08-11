<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Url;
use Drupal\layout_builder\Traits\TempstoreIdHelper;
use Drupal\outside_in\Ajax\OpenOffCanvasDialogCommand;
use Drupal\user\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class BlockVisibilityForm extends FormBase {

  use TempstoreIdHelper;

  /**
   * Tempstore factory.
   *
   * @var \Drupal\user\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $conditionManager;

  /**
   * Constructs a new BlockVisibilityForm.
   *
   * @param \Drupal\user\SharedTempStoreFactory $tempstore
   *   The tempstore factory.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Condition\ConditionManager $condition_manager
   */
  public function __construct(SharedTempStoreFactory $tempstore, ContextRepositoryInterface $context_repository, EntityTypeManagerInterface $entity_type_manager, ConditionManager $condition_manager) {
    $this->tempStoreFactory = $tempstore;
    $this->contextRepository = $context_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->conditionManager = $condition_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.shared_tempstore'),
      $container->get('context.repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.condition')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_block_visibility';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $entity = NULL, $field_name = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $form['parameters'] = [
      '#type' => 'value',
      '#value' => [
        'entity_type' => $entity_type,
        'entity' => $entity,
        'field_name' => $field_name,
        'delta' => $delta,
        'region' => $region,
        'block_id' => $uuid,
      ],
    ];
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->loadRevision($entity);
    list($collection, $id) = $this->generateTempstoreId($entity, $field_name);
    $tempstore = $this->tempStoreFactory->get($collection)->get($id);
    if (!empty($tempstore['entity'])) {
      $entity = $tempstore['entity'];
    }
    /** @var \Drupal\layout_builder\LayoutSectionItemInterface $field */
    $field = $entity->$field_name->get($delta);
    $values = $field->section;
    $visibility = !empty($values[$region][$uuid]['visibility']) ? $values[$region][$uuid]['visibility'] : [];
    $conditions = [];
    foreach ($this->conditionManager->getDefinitionsForContexts($this->contextRepository->getAvailableContexts()) as $plugin_id => $definition) {
      $conditions[$plugin_id] = $definition['label'];
    }
    $form['condition'] = [
      '#type' => 'select',
      '#title' => $this->t('Add a visibility condition'),
      '#options' => $conditions,
      '#empty_value' => '',
    ];
    $items = [];
    foreach ($visibility as $visibility_id => $configuration) {
      $parameters = $form['parameters']['#value'];
      $parameters['plugin_id'] = $visibility_id;
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance($configuration['id'], $configuration);
      $condition->summary();
      $options = [
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'dialog',
          'data-dialog-renderer' => 'off_canvas',
          'data-outside-in-edit' => TRUE,
        ],
      ];
      $items[] = [
          ['#markup' => $condition->getPluginId() . '<br />' . $condition->summary()],
          [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('layout_builder.add_visibility', $parameters, $options),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('layout_builder.delete_visibility', $parameters, $options),
              ],
            ],
          ],
      ];
    }
    if ($items) {
      $form['visibility_title'] = [
        '#markup' => '<h3>' . $this->t('Configured Conditions') . '</h3>',
      ];
      $form['visibility'] = [
        '#prefix' => '<div id="configured-conditions">',
        '#suffix' => '</div>',
        '#theme' => 'item_list',
        // '#header' => array($this->t('Plugin Id'), $this->t('Summary'), $this->t('Operations')),.
        '#items' => $items,
        '#empty' => $this->t('No required conditions have been configured.'),
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Condition'),
      '#ajax' => [
        'callback' => [$this, 'submitFormDialog'],
        'event' => 'click',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitFormDialog(array &$form, FormStateInterface $form_state) {
    $condition = $form_state->getValue('condition');
    $parameters = $form_state->getValue('parameters');
    $new_form = \Drupal::formBuilder()->getForm('\Drupal\layout_builder\Form\ConfigureVisibility', $parameters['entity_type'], $parameters['entity'], $parameters['field_name'], $parameters['delta'], $parameters['region'], $parameters['uuid'], $condition);
    $parameters['plugin_id'] = $condition;
    $new_form['#action'] = (new Url('layout_builder.add_visibility', $parameters))->toString();
    $new_form['actions']['submit']['#attached']['drupalSettings']['ajax'][$new_form['actions']['submit']['#id']]['url'] = new Url('layout_builder.add_visibility', $parameters, ['query' => [FormBuilderInterface::AJAX_FORM_REQUEST => TRUE]]);
    $response = new AjaxResponse();
    $response->addCommand(new OpenOffCanvasDialogCommand($this->t("Configure Condition"), $new_form));
    return $response;
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // $parameters = $form_state->getValue('parameters');
    //    $parameters['block_id'] = $parameters['uuid'];
    //    unset($parameters['uuid']);
    //    $parameters['plugin_id'] = $form_state->getValue('condition');
    //    $url = new Url('layout_builder.add_visibility', $parameters);
    //    $response = new RedirectResponse($url->toString());
    //    $form_state->setResponse($response);
  }

}
