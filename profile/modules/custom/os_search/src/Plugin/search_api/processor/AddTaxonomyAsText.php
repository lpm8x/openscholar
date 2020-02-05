<?php

namespace Drupal\os_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Adds the item's Taxonomy to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "add_taxonomy_as_text",
 *   label = @Translation("Taxonomy"),
 *   description = @Translation("Adds the item's taxonomy to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AddTaxonomyAsText extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Taxonomy'),
        'description' => $this->t('Custom Taxonomy for entity.'),
        'type' => 'string',
        'is_list' => FALSE,
        'processor_id' => $this->getPluginId(),
      ];
      $properties['custom_taxonomy'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {

    $object = $item->getOriginalObject()->getValue();

    if ($object->hasField('field_taxonomy_terms')) {
      $tids = $object->get('field_taxonomy_terms')->getValue();

      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, 'custom_taxonomy');
      foreach ($fields as $field) {
        foreach ($tids as $tid) {
          $field->addValue($tid['target_id']);
        }
      }
    }
  }

}
