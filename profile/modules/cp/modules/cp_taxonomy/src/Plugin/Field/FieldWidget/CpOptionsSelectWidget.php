<?php

namespace Drupal\cp_taxonomy\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\cp_taxonomy\CpTaxonomyHelper;

/**
 * Plugin implementation of the 'cp_options_select' widget.
 *
 * @FieldWidget(
 *   id = "cp_options_select",
 *   label = @Translation("CP Select list"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class CpOptionsSelectWidget extends CpOptionsWidgetBase implements ContainerFactoryPluginInterface {

  protected $taxonomyHelper;
  protected $selectionPluginManager;
  protected $entityTypeManager;

  /**
   * CpOptionsSelectWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_plugin_manager
   *   Selection Plugin Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager.
   * @param \Drupal\cp_taxonomy\CpTaxonomyHelper $taxonomy_helper
   *   Taxonomy Helper.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, SelectionPluginManagerInterface $selection_plugin_manager, EntityTypeManagerInterface $entity_type_manager, CpTaxonomyHelper $taxonomy_helper) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $selection_plugin_manager, $entity_type_manager);
    $this->selectionPluginManager = $selection_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->taxonomyHelper = $taxonomy_helper;
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
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('entity_type.manager'),
      $container->get('cp.taxonomy.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $form_state_storage = $form_state->getStorage();
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $form_state_storage['taxonomy_terms_widget_vocabulary'];
    $options = $this->taxonomyHelper->getOptionsTree($vocabulary->id());

    if (!empty($options[$vocabulary->id()])) {
      return [
        '#type' => 'select2',
        '#options' => $options[$vocabulary->id()],
        '#default_value' => $this->getSelectedOptions($items),
        '#multiple' => 1,
        '#select2' => [
          'allowClear' => FALSE,
        ],
        '#title' => $vocabulary->label(),
      ];
    }
    return [];
  }

}
