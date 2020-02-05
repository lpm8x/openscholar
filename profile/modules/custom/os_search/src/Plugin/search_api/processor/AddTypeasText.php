<?php

namespace Drupal\os_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the common type to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "add_type_as_text",
 *   label = @Translation("Sort: Type"),
 *   description = @Translation("Adds common type for all entites to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AddTypeasText extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Sort: Type'),
        'description' => $this->t('Common Type for all entities.'),
        'type' => 'string',
        'is_list' => FALSE,
        'processor_id' => $this->getPluginId(),
      ];
      $properties['custom_type'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {

    $object = $item->getOriginalObject()->getValue();
    $custom_bundle = $object->getEntityTypeId();

    $custom_type = '';
    // Get bundle or type based on Entity Type.
    if ($custom_bundle == 'node') {
      $custom_type = $object->bundle();
    }
    if ($custom_bundle == 'bibcite_reference') {
      $custom_type = $object->get('type')->getValue()[0]['target_id'];
    }

    $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, 'custom_type');

    foreach ($fields as $field) {
      $field->addValue($custom_type);
    }
  }

}
