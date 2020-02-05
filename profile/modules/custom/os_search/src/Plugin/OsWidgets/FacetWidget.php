<?php

namespace Drupal\os_search\Plugin\OsWidgets;

use Drupal\os_widgets\OsWidgetsBase;
use Drupal\os_widgets\OsWidgetsInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\search_api\Entity\Index;
use Drupal\os_search\OsSearchHelper;
use Drupal\os_search\ListAppsHelper;

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
   * Route Match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * OS search service.
   *
   * @var \Drupal\os_search\OsSearchHelper
   */
  protected $osSearcHelper;

  /**
   * Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Block content.
   *
   * @var \Drupal\block\Entity\Block
   */
  protected $blockStorage;

  /**
   * Current User service.
   *
   * @var \Drupal\os_search\ListAppsHelper
   */
  protected $appHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Connection $connection, CurrentRouteMatch $route_match, OsSearchHelper $os_search_helper, RequestStack $request_stack, ListAppsHelper $app_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $connection);
    $this->routeMatch = $route_match;
    $this->osSearcHelper = $os_search_helper;
    $this->requestStack = $request_stack;
    $this->appHelper = $app_helper;
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
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('os_search.os_search_helper'),
      $container->get('request_stack'),
      $container->get('os_search.list_app_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBlock(&$build, $block_content) {
    $build['not_search_context'] = [
      '#markup' => $this->t('Place this block on a search page to work properly.'),
    ];

    $route_name = $this->routeMatch->getRouteName();
    // Declaration of array which will hold facets.
    if (strpos($route_name, 'search_api_page') !== FALSE) {
      $field_name = $block_content->get('field_facet_id')->value;
      $limit = $block_content->get('field_facet_limit')->value;
      $index = Index::load('os_search_index');

      $request = $this->requestStack->getCurrentRequest();
      $query_string_params = $request->query->all();
      $buckets = $this->buildBuckets($index, $field_name, $limit);

      switch ($field_name) {
        case "custom_date":
          $build = $this->filterPostByDate($query_string_params, $route_name, $buckets);

          break;

        case "custom_search_bundle":
          $build = $this->filterByPost($route_name, $buckets);
          break;

        case "custom_taxonomy":
          $buckets = $this->buildBuckets($index, $field_name, $limit, FALSE);
          $build = $this->filterByTaxonomy($route_name, $buckets);
          break;

        default:
          $build = $this->buildHtml($buckets, $route_name, $field_name);
      }

      $build['#block_content'] = $block_content;

    }
  }

  /**
   * Building buckets for widgets.
   *
   * @param Drupal\search_api\Entity\Index $index
   *   Search facet index.
   * @param string $field_name
   *   Facets fields.
   * @param int $limit
   *   Limit to set in facet block.
   * @param bool $set_option
   *   To check if case required setOption.
   * @param bool $sort_dir
   *   Sorting direction for result.
   *
   * @return array
   *   Widget build
   */
  private function buildBuckets(Index $index, string $field_name, int $limit, bool $set_option = TRUE, $sort_dir = 'DESC'): array {

    $query = $index->query();
    $query->keys('');
    if ($set_option) {
      $query->setOption('search_api_facets', [
        $field_name => [
          'field' => $field_name,
          'limit' => $limit,
          'operator' => 'OR',
          'min_count' => 1,
          'missing' => FALSE,
        ],
      ]);
    }

    $query->sort('search_api_relevance', $sort_dir);
    $results = $query->execute();
    $facets = $results->getExtraData('elasticsearch_response', []);
    // Get indexed bundle types.
    $buckets = isset($facets['aggregations']) ? $facets['aggregations'][$field_name]['buckets'] : [];

    return $buckets;
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
   *
   * @return array
   *   Widget build
   */
  private function buildHtml(array $buckets, $route_name, string $field_name): array {
    $items = [];

    foreach ($buckets as $bucket) {
      $items[] = $bucket['key'];
    }

    $build[$field_name] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $field_name,
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

  /**
   * Getting filter by post date Widget group.
   *
   * @param array $query_string_params
   *   Query parameters.
   * @param string $route_name
   *   Current route name.
   * @param array $buckets
   *   Facets buckets.
   *
   * @return array
   *   Widget build
   */
  private function filterPostByDate(array $query_string_params, $route_name, array $buckets): array {
    $query_params = [
      'year' => isset($query_string_params['year']) ? $query_string_params['year'] : '',
      'month' => isset($query_string_params['month']) ? $query_string_params['month'] : '',
      'day' => isset($query_string_params['day']) ? $query_string_params['day'] : '',
      'hour' => isset($query_string_params['hour']) ? $query_string_params['hour'] : '',
      'minutes' => isset($query_string_params['minutes']) ? $query_string_params['minutes'] : '',
    ];

    $build = $this->osSearcHelper->getFilterDateWidget($query_params, $route_name, $buckets);

    return $build;
  }

  /**
   * Getting filter by post type Widget group.
   *
   * @param string $route_name
   *   Current route name.
   * @param array $buckets
   *   Facets buckets.
   *
   * @return array
   *   Widget build
   */
  private function filterByPost($route_name, array $buckets): array {
    $titles = $this->appHelper->getAppLists();

    $build = $this->osSearcHelper->getPostWidget($route_name, $buckets, $titles);

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
  private function filterByTaxonomy($route_name, array $buckets): array {
    $build = $this->osSearcHelper->getTaxonomyWidget($route_name, $buckets);

    return $build;
  }

}
