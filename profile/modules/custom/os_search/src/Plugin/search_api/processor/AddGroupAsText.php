<?php

namespace Drupal\os_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\group\Entity\GroupContent;

/**
 * Adds the item's Group to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "add_group_as_text",
 *   label = @Translation("Other Sites"),
 *   description = @Translation("Adds the item's Group to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AddGroupAsText extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Other Sites'),
        'description' => $this->t('Group for entity.'),
        'type' => 'integer',
        'is_list' => FALSE,
        'processor_id' => $this->getPluginId(),
      ];
      $properties['custom_search_group'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {

    $object = $item->getOriginalObject()->getValue();
    $groups = GroupContent::loadByEntity($object);

    if ($groups) {
      $groups = array_reverse($groups);
      $group = array_pop($groups);
      $group_id = (string) $group->getGroup()->id();
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, 'custom_search_group');

      foreach ($fields as $field) {
        $field->addValue($group_id);
      }
    }
  }

}
