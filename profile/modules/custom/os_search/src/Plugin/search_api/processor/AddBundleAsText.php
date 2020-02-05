<?php

namespace Drupal\os_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the item's Bundle to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "add_bundle_as_text",
 *   label = @Translation("Post Type"),
 *   description = @Translation("Adds the item's Bundle/Entity Type ID for non-nodes to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AddBundleAsText extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Post Type'),
        'description' => $this->t('Custom Bundle for entity.'),
        'type' => 'string',
        'is_list' => FALSE,
        'processor_id' => $this->getPluginId(),
        'facet_type' => 'sort',
      ];
      $properties['custom_search_bundle'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {

    $object = $item->getOriginalObject()->getValue();
    $custom_bundle = $object->getEntityTypeId();

    if ($custom_bundle == 'node') {
      $custom_bundle = $object->bundle();
    }

    if ($custom_bundle) {
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, 'custom_search_bundle');

      foreach ($fields as $field) {
        $field->addValue($custom_bundle);
      }
    }
  }

}
