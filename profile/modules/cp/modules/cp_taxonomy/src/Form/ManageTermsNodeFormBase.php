<?php

namespace Drupal\cp_taxonomy\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\cp_taxonomy\CpTaxonomyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Manage terms node entities base form.
 */
abstract class ManageTermsNodeFormBase extends FormBase {

  /**
   * The array of entities to delete.
   *
   * @var array
   */
  protected $entityInfo = [];

  /**
   * The tempstore factory object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type identifier.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Cp taxonomy helper.
   *
   * @var \Drupal\cp_taxonomy\CpTaxonomyHelper
   */
  protected $taxonomyHelper;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user object.
   * @param \Drupal\cp_taxonomy\CpTaxonomyHelper $taxonomy_helper
   *   Taxonomy Helper.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   Taxonomy Helper.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityTypeManagerInterface $manager, AccountInterface $current_user, CpTaxonomyHelper $taxonomy_helper, Renderer $renderer) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityTypeManager = $manager;
    $this->currentUser = $current_user;
    $this->taxonomyHelper = $taxonomy_helper;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('cp.taxonomy.helper'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL) {
    $this->entityTypeId = $entity_type_id;
    $this->entityInfo = $this->tempStore->get($this->currentUser->id());
    if (empty($this->entityInfo)) {
      return new RedirectResponse(Url::fromRoute('cp.content.collection')->toString());
    }
    $vocabulary_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $vocabs = $vocabulary_storage->loadMultiple();

    $options = [];
    $options_terms = [];
    foreach ($vocabs as $vocab) {
      $options[$vocab->id()] = $vocab->label();
      $terms = $term_storage->loadTree($vocab->id());
      foreach ($terms as $term) {
        // We have to collect all terms to prevent error from allowed values.
        $options_terms[$term->tid] = $term->name;
      }
    }
    $form['vocabulary'] = [
      '#type' => 'select',
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'callback' => '::getTermsAjaxCallback',
        'event' => 'change',
        'wrapper' => 'edit-terms',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Getting terms...'),
        ],
      ],
    ];

    $form['terms'] = [
      '#type' => 'select',
      '#options' => $options_terms,
      '#multiple' => 1,
      '#chosen' => 1,
      '#title' => $this->t('Terms'),
      '#prefix' => '<div id="edit-terms">',
      '#suffix' => '</div>',
      '#states' => [
        'invisible' => [
          ':input[name="vocabulary"]' => ['value' => ''],
        ],
      ],
    ];

    $form['entities'] = [
      '#title' => $this->t('The selected terms above will be applied to the following content:'),
      '#theme' => 'item_list',
      '#items' => $this->entityInfo,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $vocabulary_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $selected_vocabulary = $form_state->getValue('vocabulary');
    $allowed_types = $vocabulary_storage->load($selected_vocabulary)->get('allowed_vocabulary_reference_types');
    $vocab_entities = $this->taxonomyHelper->explodeEntityBundles($allowed_types);
    if (empty($vocab_entities[$this->entityTypeId])) {
      $form_state->setError($form['vocabulary'], $this->t('Selected vocabulary is not handle %entity_type_id entity type.', ['%entity_type_id' => $this->entityTypeId]));
    }
  }

  /**
   * Ajax callback for handling vocabulary depends terms selection.
   */
  public function getTermsAjaxCallback(array &$form, FormStateInterface $form_state) {
    if ($selected_vocabulary = $form_state->getValue('vocabulary')) {
      $vocabulary_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $vocab = $vocabulary_storage->load($selected_vocabulary);
      // Get terms from selected vocabulary.
      $terms = $term_storage->loadTree($vocab->id());
      $options = [];
      foreach ($terms as $term) {
        $options[$term->tid] = $term->name;
      }
      $form['terms']['#options'] = $options;
      // Return the prepared element.
      return $form['terms'];
    }
    return [];
  }

}
