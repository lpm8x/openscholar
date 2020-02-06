<?php

namespace Drupal\os_search;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\group\Entity\Group;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Config\ConfigFactory;

/**
 * Helper class for search.
 */
class OsSearchHelper {

  use StringTranslationTrait;

  /**
   * Entity storage for block content.
   *
   * @var Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockContent;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory) {
    $this->blockContent = $entity_type_manager->getStorage('block_content');
    $this->configFactory = $config_factory;
  }

  /**
   * Assigning block to group on creation.
   *
   * @param Drupal\group\Entity\Group $entity
   *   Group entity for assigning block.
   */
  public function createGroupBlockWidget(Group $entity): void {
    $fields = $this->getAllowedFacetIds();

    foreach ($fields as $key => $field_info) {

      $block_values = [
        'info' => $this->t('@group_name | Faceted Search: Filter By @field_name', [
          '@group_name' => $entity->label(),
          '@field_name' => $field_info,
        ]),
        'type' => 'facet',
        'field_facet_id' => $key,
      ];

      $block_content = $this->blockContent->create($block_values);
      if ($block_content->save()) {
        $entity->addContent($block_content, 'group_entity:block_content');
      }

    }
    $block_values = [
      'info' => $this->t('@group_name | Search Sort', ['@group_name' => $entity->label()]),

      'type' => 'search_sort',
    ];

    $block_content = $this->blockContent->create($block_values);

    if ($block_content->save()) {
      $entity->addContent($block_content, 'group_entity:block_content');
    }
  }

  /**
   * Function to get all fields with facet capabilities.
   *
   * General fields for OS Search Index.
   */
  public function getAllowedFacetIds(): array {
    $options = [];
    $config = $this->configFactory->get('os.search.settings');
    $index = Index::load('os_search_index');
    $fields = $index->getFieldsByDatasource(NULL);
    foreach ($fields as $key => $field) {
      if ($config->get('facet_widget')[$key] != NULL && $config->get('facet_widget')[$key] == $key) {
        $options[$key] = $field->getLabel();
      }
    }
    return $options;
  }

}
