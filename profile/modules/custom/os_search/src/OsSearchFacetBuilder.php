<?php

namespace Drupal\os_search;

use Drupal\search_api\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Helper class for search facet builder.
 */
class OsSearchFacetBuilder {
  use StringTranslationTrait;

  // @var array field_id => entity_type mapping, help generating labels.
  const FIELD_MAPPING = [
    'custom_search_group' => 'group',
    'custom_date' => 'date',
    'custom_search_bundle' => 'bundle',
    'custom_title' => 'string',
    'custom_type' => 'bundle',
    'custom_taxonomy' => 'taxonomy_term',
  ];

  /**
   * Entity manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Configuration Factory.
   *
   * @var Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Request Stack.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Bundle info.
   *
   * @var Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $bundleInfo;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory, RequestStack $request_stack, EntityTypeBundleInfo $bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->bundleInfo = $bundle_info;
  }

  /**
   * Creates facet buckets.
   *
   * @param string $field_name
   *   Machine name of the field which is extracted as facet buckets.
   * @param Drupal\search_api\Query\QueryInterface $query
   *   Query loaded & altered for current search page.
   * @param bool $grouped
   *   If facets needed to be grouped by parent (vid, entity_type etc.)
   *
   * @return array
   *   Facet buckets.
   */
  public function getFacetBuckets($field_name, QueryInterface $query, $grouped = FALSE): array {
    $query->setOption('search_api_facets', [
      $field_name => [
        'field' => $field_name,
        'limit' => 0,
        'operator' => 'OR',
        'min_count' => 1,
        'missing' => FALSE,
      ],
    ]);

    $results = $query->execute();
    $facets = $results->getExtraData('elasticsearch_response', []);

    return isset($facets['aggregations']) ? $facets['aggregations'][$field_name]['buckets'] : [];
  }

  /**
   * Update bucket data with corresponding labels.
   *
   * @param array $buckets
   *   Facets loaded for a field.
   * @param string $field_id
   *   Machine name of the field.
   */
  public function prepareFacetLabels(array &$buckets, $field_id) {
    $field_label_processor = static::FIELD_MAPPING[$field_id];
    $bucket_labels = [];

    if ($field_label_processor == 'bundle') {
      $bucket_labels = $this->bundleInfo->getBundleInfo('node');
      $bucket_labels['bibcite_reference']['label'] = 'Publication';

      foreach ($buckets as $key => $bucket) {
        $buckets[$key]['label'] = $bucket_labels[$bucket['key']];
      }
    }
    elseif ($field_label_processor == 'group' || $field_label_processor == 'taxonomy_term') {
      $entity_storage = $this->entityTypeManager->getStorage($field_label_processor);

      foreach ($buckets as $key => $bucket) {
        $buckets[$key]['label'] = $entity_storage->load($bucket['key'])->label();
      }
    }
    elseif ($field_label_processor == 'date') {
      $this->prepareDateFacets($buckets, $field_id);
    }
  }

  /**
   * Update bucket data with relevant query parameters (Helps prepare links).
   *
   * @param array $buckets
   *   Facets loaded for a field.
   * @param string $field_id
   *   Machine name of the field.
   */
  public function prepareFacetLinks(array &$buckets, $field_id) {
    foreach ($buckets as $key => $bucket) {
      $buckets[$key]['query'] = ["{$field_id}:{$bucket['key']}"];
    }
  }

  /**
   * Generating date facets from timestamp.
   *
   * @param array $buckets
   *   Facets buckets.
   * @param string $field_id
   *   ID of the date field.
   */
  private function prepareDateFacets(array &$buckets, string $field_id) {
    $date_filters = $this->extractDateParts($field_id);

    $unprocessed_buckets = $buckets;
    $buckets = [];

    // Converting simple timestamp buckets to date facets.
    foreach ($unprocessed_buckets as $bucket) {
      // Dividing 1000 to convert timestamp into proper format to be used.
      $bucket['key'] = (strlen((string) $bucket['key']) > 10) ? $bucket['key'] / 1000 : $bucket['key'];

      if (!isset($date_filters['year'])) {
        $year = date("Y", $bucket['key']);
        if (!isset($buckets[$year])) {
          $buckets[$year] = [
            'key' => 'year-' . $year,
            'doc_count' => $bucket['doc_count'],
            'date_key' => $year,
            'label' => $year,
          ];
        }
        else {
          $buckets[$year]['doc_count'] += $bucket['doc_count'];
        }
      }
      elseif (!isset($date_filters['month'])) {
        $month = date("n", $bucket['key']);
        if (!isset($buckets[$month])) {
          $buckets[$month] = [
            'key' => 'month-' . $month,
            'doc_count' => $bucket['doc_count'],
            'label' => date("M Y", $bucket['key']),
            'date_key' => $month,
          ];
        }
        else {
          $buckets[$month]['doc_count'] += $bucket['doc_count'];
        }
      }
      elseif (!isset($date_filters['day'])) {
        $day = date("j", $bucket['key']);
        if (!isset($buckets[$day])) {
          $buckets[$day] = [
            'key' => 'day-' . $day,
            'doc_count' => $bucket['doc_count'],
            'label' => date("M d, Y", $bucket['key']),
            'date_key' => $day,
          ];
        }
        else {
          $buckets[$day]['doc_count'] += $bucket['doc_count'];
        }
      }
      elseif (!isset($date_filters['hour'])) {
        $hour = date("h", $bucket['key']);
        if (!isset($buckets[$hour])) {
          $buckets[$hour] = [
            'key' => 'hour-' . $hour,
            'doc_count' => $bucket['doc_count'],
            'label' => date("h A", $bucket['key']),
            'date_key' => $hour,
          ];
        }
        else {
          $buckets[$hour]['doc_count'] += $bucket['doc_count'];
        }
      }
      elseif (!isset($date_filters['minute'])) {
        $minute = date("i", $bucket['key']);
        if (!isset($buckets[$minute])) {
          $buckets[$minute] = [
            'key' => 'minute-' . $minute,
            'doc_count' => $bucket['doc_count'],
            'label' => date("h:i A", $bucket['key']),
            'date_key' => $minute,
          ];
        }
        else {
          $buckets[$minute]['doc_count'] += $bucket['doc_count'];
        }
      }
    }
  }

  /**
   * Extract date parts from query string.
   *
   * @param string $field_id
   *   Field ID to extract date parts.
   *
   * @return array
   *   An array of date parts
   */
  public function extractDateParts(string $field_id): array {
    // Consider only 'f' array key as facet filters from querystring.
    $filters = $this->requestStack->getCurrentRequest()->query->get('f') ?? [];
    $date_parts = [];

    // Extract complete date based on selected date filters.
    // Helps building date labels.
    foreach ($filters as $filter) {
      if (strpos($filter, "{$field_id}:year-") !== FALSE) {
        $date_parts['year'] = (int) str_replace("{$field_id}:year-", "", $filter);
      }
      if (strpos($filter, "{$field_id}:month-") !== FALSE) {
        $date_parts['month'] = (int) str_replace("{$field_id}:month-", "", $filter);
      }
      if (strpos($filter, "{$field_id}:day-") !== FALSE) {
        $date_parts['day'] = (int) str_replace("{$field_id}:day-", "", $filter);
      }
      if (strpos($filter, "{$field_id}:hour-") !== FALSE) {
        $date_parts['hour'] = (int) str_replace("{$field_id}:hour-", "", $filter);
      }
      if (strpos($filter, "{$field_id}:minute-") !== FALSE) {
        $date_parts['minute'] = (int) str_replace("{$field_id}:minute-", "", $filter);
      }
    }

    return $date_parts;
  }

  /**
   * Get required processor.
   *
   * @param string $field_id
   *   Field for which processor is needed.
   *
   * @return string
   *   Processor string.
   */
  public function getFieldProcessor($field_id) {
    return static::FIELD_MAPPING[$field_id] ?? 'string';
  }

  /**
   * Generate remove (-) links for already applied filters using query string.
   *
   * @param string $field_id
   *   Field for which summary is needed.
   *
   * @return array
   *   Array of current search summary.
   */
  public function getCurrentSearchSummary($field_id) {
    $filters = $this->requestStack->getCurrentRequest()->query->get('f') ?? [];
    $summary = [
      'reduced_filter' => [],
      'needed' => FALSE,
    ];

    $i = 0;
    foreach ($filters as $filter) {
      if (strpos($filter, $field_id) !== FALSE) {
        $value = str_replace("{$field_id}:", '', $filter);
        $summary['needed'] = TRUE;
        $summary['reduced_filter'][$i]['filter'] = $filter;
        $summary['reduced_filter'][$i]['label'] = $this->prepareSingleLabel($this->getFieldProcessor($field_id), $field_id, $value);
        $summary['reduced_filter'][$i]['query'] = $filters;
        $summary['reduced_filter'][$i]['value'] = $value;
        $i++;
      }
    }

    return $summary;
  }

  /**
   * Convert value to label.
   *
   * @param string $field_label_processor
   *   Processor to be used.
   * @param string $field_id
   *   Field ID.
   * @param string $value
   *   Value of the filter.
   *
   * @return string
   *   Label string.
   */
  protected function prepareSingleLabel($field_label_processor, $field_id, $value) {
    if ($field_label_processor == 'bundle') {
      $bucket_labels = $this->bundleInfo->getBundleInfo('node');
      $bucket_labels['bibcite_reference']['label'] = 'Publication';

      return $bucket_labels[$value];
    }
    elseif ($field_label_processor == 'group' || $field_label_processor == 'taxonomy_term') {
      $entity_storage = $this->entityTypeManager->getStorage($field_label_processor);
      $label = $entity_storage->load($value)->label();

      return $label;
    }
    elseif ($field_label_processor == 'date') {
      $default_parts = [
        'year' => 1970,
        'month' => 1,
        'day' => 1,
        'hour' => 0,
        'minute' => 0,
      ];

      $date_parts = $this->extractDateParts($field_id) + $default_parts;
      $timestamp = strtotime("{$date_parts['year']}-{$date_parts['month']}-{$date_parts['day']} {$date_parts['hour']}:{$date_parts['minute']}:00");

      if (strpos($value, 'year-') !== FALSE) {
        return date('Y', $timestamp);
      }
      elseif (strpos($value, 'month-') !== FALSE) {
        return date('M Y', $timestamp);
      }
      elseif (strpos($value, 'day-') !== FALSE) {
        return date('M d, Y', $timestamp);
      }
      elseif (strpos($value, 'hour-') !== FALSE) {
        return date('h A', $timestamp);
      }
      elseif (strpos($value, 'minute-') !== FALSE) {
        return date('h:i A', $timestamp);
      }

      return $value;
    }
  }
}
