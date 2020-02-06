<?php

namespace Drupal\os_search\Plugin\OsWidgets;

use Drupal\os_widgets\OsWidgetsBase;
use Drupal\os_widgets\OsWidgetsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Database\Connection;
use Drupal\os_search\OsSearchFacetBuilder;
use Drupal\os_search\OsSearchQueryBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class FacetWidget.
 *
 * @OsWidget(
 *   id = "facet_widget",
 *   title = @Translation("Facets")
 * )
 */
class FacetWidget extends OsWidgetsBase implements OsWidgetsInterface {

  /**
   * Entity manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Route Match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Configuration Factory.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Block content.
   *
   * @var \Drupal\block\Entity\Block
   */
  protected $blockStorage;

  /**
   * Facets builder.
   *
   * @var \Drupal\os_search\OsSearchFacetBuilder
   */
  protected $osSearchFacetBuilder;

  /**
   * Os Search query builder.
   *
   * @var Drupal\os_search\OsSearchQueryBuilder
   */
  protected $searchQueryBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match, OsSearchFacetBuilder $os_search_facet_builder, OsSearchQueryBuilder $os_search_query_builder, Connection $connection, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $connection);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->osSearchFacetBuilder = $os_search_facet_builder;
    $this->searchQueryBuilder = $os_search_query_builder;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('os_search.os_search_facet_builder'),
      $container->get('os_search.os_search_query_builder'),
      $container->get('database'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBlock(&$build, $block_content) {
    $route_name = $this->routeMatch->getRouteName();
    $buckets = [];
    $reduced_filters = [];
    $field_id = '';
    $field_label = '';

    if (strpos($route_name, 'search_api_page') !== FALSE) {
      // Load search page.
      // Find better method to load search page object.
      $search_page_id = $this->routeMatch->getParameter('search_api_page_name');
      $search_page = $this->entityTypeManager->getStorage('search_api_page')->load($search_page_id);
      $search_page_index_id = $search_page->getIndex();
      $search_page_index = $this->entityTypeManager->getStorage('search_api_index')->load($search_page_index_id);
      $query = $search_page_index->query();
      $query->addTag('get_all_facets');

      // Dependent filters.
      $this->searchQueryBuilder->queryBuilder($query);

      $field_id = $block_content->get('field_facet_id')->value;
      $field_label = $search_page_index->getField($field_id)->getLabel();
      $field_type = $search_page_index->getField($field_id)->getType();
      $buckets = $this->osSearchFacetBuilder->getFacetBuckets($field_id, $query);
      $this->osSearchFacetBuilder->prepareFacetLabels($buckets, $field_id);
      $this->osSearchFacetBuilder->prepareFacetLinks($buckets, $field_id);

      // Get current search summary with (-) link.
      $reduced_filters = $this->osSearchFacetBuilder->getCurrentSearchSummary($field_id);
      if ($field_type != 'date') {
        $buckets = (count($buckets) > 1) ? $buckets : [];
      }

      // Generate renderable array.
      $build = $this->renderableArray($buckets, $route_name, $field_id, $field_label, $reduced_filters);
    }
    else {
      $build['empty_build'] = [
        '#theme' => 'item_list',
        '#empty' => '',
        '#list_type' => 'ul',
        '#title' => $this->t('Not search context.'),
        '#items' => [],
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }

    $build['#block_content'] = $block_content;
  }

  /**
   * Building links for future facets.
   *
   * @param array $buckets
   *   Facets buckets.
   * @param string $route_name
   *   Current route name.
   * @param string $field_name
   *   Facets field name.
   * @param string $field_label
   *   Label of facet field.
   * @param array $reduced_filters
   *   Array of reduced filters.
   *
   * @return array
   *   Widget build
   */
  private function renderableArray(array $buckets, $route_name, string $field_name, string $field_label, array $reduced_filters): array {
    $items = [];
    $summary_items = [];
    $keys = $this->requestStack->getCurrentRequest()->attributes->get('keys');
    $filters = $this->requestStack->getCurrentRequest()->query->get('f') ?? [];

    foreach ($buckets as $bucket) {
      $item_label = isset($bucket['label']) ? $bucket['label'] : ucwords($bucket['key']);
      $item_label = is_array($item_label) ? reset($item_label) : $item_label;

      $path = Url::fromRoute($route_name, [
        'f' => array_merge($filters, $bucket['query']),
        'keys' => $keys,
      ]);

      $items[] = Link::fromTextAndUrl($this->t('@label (@count)', ['@label' => $item_label, '@count' => $bucket['doc_count']]), $path)->toString();
    }

    if ($reduced_filters['needed']) {
      foreach ($reduced_filters['reduced_filter'] as $reduced_filter) {
        $querys = isset($reduced_filter['query']) ? $reduced_filter['query'] : [];
        foreach ($querys as $key => $query) {
          if ($query == $reduced_filter['filter']) {
            unset($querys[$key]);
          }
        }

        $item_label = isset($reduced_filter['label']) ? $reduced_filter['label'] : '';
        $item_label = is_array($item_label) ? reset($item_label) : $item_label;
        $path = Url::fromRoute($route_name, ['f' => $querys, 'keys' => $keys]);
        $path_string = Link::fromTextAndUrl("(-)", $path)->toString();
        $summary_items[] = $this->t('@path_string @label', ['@path_string' => $path_string, '@label' => $item_label]);
      }
    }

    $build[$field_name]['facets'] = [
      '#theme' => 'item_list',
      '#empty' => $this->t('No filters available'),
      '#list_type' => 'ul',
      '#title' => $this->t('Filter By @field_label', ['@field_label' => $field_label]),
      '#items' => array_merge($summary_items, $items),
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

}
