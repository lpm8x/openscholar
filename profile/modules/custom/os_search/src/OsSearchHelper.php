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
<<<<<<< HEAD
=======
   * Getting filter by Date Widget group.
   *
   * @param array $query_params
   *   Request Params.
   * @param string $route_name
   *   Current route name.
   * @param array $buckets
   *   Facets buckets.
   *
   * @return array
   *   Widget build.
   */
  public function getFilterDateWidget(array $query_params, string $route_name, array $buckets): array {
    $year = $query_params['year'];
    $month = $query_params['month'];
    $day = $query_params['day'];
    $hour = $query_params['hour'];
    $minutes = $query_params['minutes'];

    $items = [];

    // Declaration of array which will hold required query parameter.
    $gen_query_params = [];

    // Generating links from custom_date facets.
    // Using timestamp for condition filter the records to create links.
    $created_date = [];
    foreach ($buckets as $bundle) {

      // Dividing 1000 to convert timestamp into proper format to be used.
      $bundle['key'] = $bundle['key'] / 1000;
      if (!isset($year) || $year == '') {
        $created_date['year'] = date('Y', $bundle['key']);
        $gen_query_params = $created_date;
      }
      elseif (!isset($month) || $month == '') {
        $condition = $bundle['key'] >= strtotime('01-01-' . $year) &&
          $bundle['key'] <= strtotime('31-12-' . $year);
        if ($condition) {
          $created_date['year'] = date('Y', $bundle['key']);
          $created_date['month'] = date('M Y', $bundle['key']);
          $gen_query_params = [
            'month' => date('m', $bundle['key']),
          ];
        }

      }
      elseif (!isset($day) || $day == '') {
        $condition = $bundle['key'] >= strtotime('01-' . $month . '-' . $year) &&
          $bundle['key'] < strtotime('31-' . $month . '-' . $year);
        if ($condition) {
          $created_date['year'] = date('Y', $bundle['key']);
          $created_date['month'] = date('M Y', $bundle['key']);
          $created_date['day'] = date('M d, Y', $bundle['key']);
          $gen_query_params = [
            'day' => date('d', $bundle['key']),
          ];
        }

      }
      elseif (!isset($hour) || $hour == '') {
        $condition = $bundle['key'] >= strtotime($day . '-' . $month . '-' . $year . ' 00:00:00') &&
          $bundle['key'] <= strtotime($day . '-' . $month . '-' . $year . ' 23:59:59');
        if ($condition) {
          $created_date['year'] = date('Y', $bundle['key']);
          $created_date['month'] = date('M Y', $bundle['key']);
          $created_date['day'] = date('M d, Y', $bundle['key']);
          $created_date['hour'] = date('h A', $bundle['key']);
          $gen_query_params = [
            'hour' => date('H', $bundle['key']),
          ];
        }
      }
      elseif (!isset($minutes) || $minutes == '') {
        $condition = $bundle['key'] >= strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':00:00') &&
          $bundle['key'] <= strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':59:59');
        if ($condition) {
          $created_date['year'] = date('Y', $bundle['key']);
          $created_date['month'] = date('M Y', $bundle['key']);
          $created_date['day'] = date('M d, Y', $bundle['key']);
          $created_date['hour'] = date('h A', $bundle['key']);
          $created_date['minutes'] = date('h:i A', $bundle['key']);
          $gen_query_params = [
            'minutes' => date('A', $bundle['key']),
          ];
        }

      }
      else {
        $condition = $bundle['key'] >= strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':' . $minutes . ':00') &&
          $bundle['key'] <= strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':' . $minutes . ':59');
        if ($condition) {
          $created_date['year'] = date('Y', $bundle['key']);
          $created_date['month'] = date('M Y', $bundle['key']);
          $created_date['day'] = date('M d, Y', $bundle['key']);
          $created_date['hour'] = date('h A', $bundle['key']);
          $created_date['minutes'] = date('h:i A', $bundle['key']);
        }
      }
    }

    $query_string = array_merge(array_filter($query_params), $gen_query_params);
    if (count($query_string) == 0) {
      $items['no_records'] = '';
    }
    foreach ($query_string as $key => $query_para) {
      $query_paramater[$key] = $query_para;
      $url = Url::fromRoute($route_name, $query_paramater);
      $items[$key] = Link::fromTextAndUrl($created_date[$key], $url)->toString();
    }

    $build['date_granular_list'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Filter By Post Date'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;

  }

  /**
   * Getting Post Widget group.
   *
   * @param string $route_name
   *   Current route name.
   * @param array $buckets
   *   Search buckets.
   * @param array $titles
   *   List APP titles.
   *
   * @return array
   *   Widget build
   */
  public function getPostWidget(string $route_name, array $buckets, array $titles): array {
    $items = [];
    if (count($buckets) == 0) {
      $items['no_records'] = '';

    foreach ($buckets as $bundle) {
      $url = Url::fromRoute($route_name, ['f[0]' => 'custom_bundle_text:' . $bundle['key']]);
      $title = $this->t('@app_title (@count)', ['@app_title' => $titles[$bundle['key']], '@count' => $bundle['doc_count']]);
      $items[] = Link::fromTextAndUrl($title, $url)->toString();
    }

    $build['filter-post-list'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Filter By Post Type'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

  /**
   * Getting Taxonomy Widget group.
   *
   * @param string $route_name
   *   Current route name.
   * @param array $buckets
   *   Facets buckets.
   *
   * @return array
   *   Widget build
   */
  public function getTaxonomyWidget(string $route_name, array $buckets): array {
    $items = [];
    $vocabularies = Vocabulary::loadMultiple();
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach ($buckets as $bucket) {
      $term = $termStorage->load($bucket['key']);
      $name = $term->get('name')->value;
      $url = Url::fromRoute($route_name, ['f[0]' => 'custom_taxonomy_text:' . $bucket['key']]);
      $title = $this->t('@app_title (@count)', ['@app_title' => $name, '@count' => $bucket['doc_count']]);
      $vname = $vocabularies[$term->getVocabularyId()]->get('name');
      $items[$vname][] = Link::fromTextAndUrl($title, $url)->toString();
    }
    $build['filter-taxonomy-list'] = [
      '#theme' => 'os_filter_taxonomy_widget',
      '#header' => $this->t('Filter By Taxonomy'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

  /**
>>>>>>> Issue #12637 - Changes as per discussion.
   * Function to get all fields with facet capabilities.
   *
   * General fields for OS Search Index.
   */
  public function getAllowedFacetIds(): array {
    $options = [];
    $config = $this->configFactory->get('os.search.settings');
    $index = Index::load('os_search_index');
    // ksm($index);
    $fields = $index->getFieldsByDatasource(NULL);
    foreach ($fields as $key => $field) {
      if ($config->get('facet_widget')[$key] != NULL && $config->get('facet_widget')[$key] == $key) {
        $options[$key] = $field->getLabel();
      }
    }
    return $options;
  }

}
