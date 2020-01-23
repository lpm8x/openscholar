<?php

namespace Drupal\os_search;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\group\Entity\Group;
use Drupal\search_api\Entity\Index;

/**
 * Helper class for search.
 */
class OsSearchHelper {
  /**
   * Entity manager for node.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $nodeStorage;

  use StringTranslationTrait;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->blockContent = $entity_type_manager->getStorage('block_content');
  }

  /**
   * Alter query for search added condition for custom_date.
   */
  public function addCustomDateFilterQuery(&$query, $query_params): void {
    $year = $query_params['year'];
    $month = $query_params['month'];
    $day = $query_params['day'];
    $hour = $query_params['hour'];
    $minutes = $query_params['minutes'];

    if (($year) !== NULL) {
      if (!isset($month)) {
        $start_date = strtotime('01-01-' . $year);
        $end_date = strtotime('31-12-' . $year);
      }
      elseif (!isset($day)) {
        $start_date = strtotime('01-' . $month . '-' . $year);
        $end_date = strtotime('31-' . $month . '-' . $year);
      }
      elseif (!isset($hour)) {
        $start_date = strtotime($day . '-' . $month . '-' . $year . ' 00:00:00');
        $end_date = strtotime($day . '-' . $month . '-' . $year . ' 23:59:59');
      }
      elseif (!isset($minutes)) {
        $start_date = strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':00:00');
        $end_date = strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':59:59');
      }
      else {
        $start_date = strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':' . $minutes . ':00');
        $end_date = strtotime($day . '-' . $month . '-' . $year . ' ' . $hour . ':' . $minutes . ':59');
      }

      $query->addCondition('custom_date', $start_date, '>=');
      $query->addCondition('custom_date', $end_date, '<=');

    }
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
      $block_content = [];
      $block_content = [
        'info' => $entity->label() . ':' . $field_info,
        'type' => 'facet',
        'field_facet_id' => $key,
      ];

      $block = $this->blockContent->create($block_content);

      if ($block->save()) {
        $entity->addContent($block, 'group_entity:block_content');
      }
    }
  }

  /**
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

    // Declaration of array which will hold required query parameter.
    $gen_query_params = [];

    // Generating links from custom_date facets.
    // Using timestamp for condition filter the records to create links.
    $created_date = [];
    foreach ($buckets as $bundle) {
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
      '#header' => $this->t('Filter By Post Type'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

  /**
   * Function to get all fields with facet capabilities.
   *
   * General fields for OS Search Index.
   */
  public function getAllowedFacetIds(): array {
    $options = [];
    $index = Index::load('os_search_index');
    $fields = $index->getFieldsByDatasource(NULL);
    foreach ($fields as $key => $field) {
      $options[$key] = $field->getLabel();
    }
    return $options;
  }

}