<?php

namespace Drupal\cp_taxonomy\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\cp_taxonomy\CpTaxonomyHelperInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Taxonomy terms widget for reference terms.
 *
 * @FieldWidget(
 *   id = "filter_by_vocab",
 *   label = @Translation("Filter By Vocab widget"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class FilterByVocabWidget extends WidgetBase implements WidgetInterface, ContainerFactoryPluginInterface {

  /**
   * Cp taxonomy helper.
   *
   * @var \Drupal\cp_taxonomy\CpTaxonomyHelperInterface
   */
  protected $taxonomyHelper;

  /**
   * Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * TaxonomyTermsWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $visibility_settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\cp_taxonomy\CpTaxonomyHelperInterface $taxonomy_helper
   *   Config Factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $visibility_settings, array $third_party_settings, CpTaxonomyHelperInterface $taxonomy_helper, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $visibility_settings, $third_party_settings);
    $this->taxonomyHelper = $taxonomy_helper;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('cp.taxonomy.helper'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $title = $element['#title'];
    $description = $element['#description'];
    $vocabularies = Vocabulary::loadMultiple();
    foreach ($vocabularies as $vid => $vocabulary) {
      $list[$vid] = $vocabulary;
    }

    $element['terms_container'] = [
      '#type' => 'details',
      '#title' => $title,
      '#description' => $description,
      '#open' => TRUE,
    ];

    $list = [];
    foreach ($list as $vid => $vocab) {
      $visibility_settings = [];
      $visibility_settings[] = ['value' => 'all'];
      $allowed_types = $this->taxonomyHelper->getVocabularySettings($vid)['allowed_vocabulary_reference_types'];
      foreach ($allowed_types as $type) {
        if (strpos($type, 'node') !== FALSE) {
          $visibility_settings[] = ['value' => str_replace('node:', '', $type)];
        }
        elseif (strpos($type, 'bibcite_reference') !== FALSE) {
          $visibility_settings[] = ['value' => 'publication'];
        }
      }
      $element['terms_container'][$vid] = [
        '#type' => 'select',
        '#title' => $vocab->get('name'),
        '#multiple' => TRUE,
        '#chosen' => TRUE,
        '#options' => $this->getVocabTerms($vid),
        '#default_value' => $this->getVocabTerms($vid, $items, TRUE) ?? '',
        '#states' => [
          'visible' => [
            'select[name="field_content_type"]' => [$visibility_settings],
          ],
        ],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $tids = [];
    $values = $values['terms_container'];
    foreach ($values as $value) {
      if ($value) {
        $tids[] = array_keys($value);
      }
    }
    return array_merge(...$tids);
  }

  /**
   * Gets terms based on vid.
   *
   * @param string $vid
   *   Vocabulary id.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param bool $defaults
   *   If need default/selected values.
   *
   * @return array
   *   Term ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getVocabTerms($vid, FieldItemListInterface $items = NULL, $defaults = FALSE): ?array {
    $data = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
    foreach ($terms as $term) {
      $data[$term->tid] = $term->name;
    }
    if ($items && $defaults) {
      foreach ($items as $item) {
        if (in_array($item->getValue()['target_id'], array_keys($data))) {
          $data[] = $item->getValue()['target_id'];
        }
      }
    }
    return $data;
  }

}
