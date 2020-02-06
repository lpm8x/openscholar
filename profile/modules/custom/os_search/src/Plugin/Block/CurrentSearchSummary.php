<?php

namespace Drupal\os_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\os_search\OsSearchFacetBuilder;
use Drupal\os_search\OsSearchQueryBuilder;

/**
 * Subsite Search Block.
 *
 * @Block(
 *   id = "current_search_summary",
 *   admin_label = @Translation("Current Search Summary"),
 * )
 */
class CurrentSearchSummary extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Facet builder.
   *
   * @var \Drupal\os_search\OsSearchFacetBuilder
   */
  protected $facetBuilder;

  /**
   * Os Search query builder.
   *
   * @var Drupal\os_search\OsSearchQueryBuilder
   */
  protected $searchQueryBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match, RequestStack $request_stack, AccountInterface $current_user, OsSearchFacetBuilder $facet_builder, OsSearchQueryBuilder $os_search_query_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->facetBuilder = $facet_builder;
    $this->searchQueryBuilder = $os_search_query_builder;
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
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('os_search.os_search_facet_builder'),
      $container->get('os_search.os_search_query_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $filters = $this->requestStack->getCurrentRequest()->query->get('f') ?? [];
    $keys = $this->requestStack->getCurrentRequest()->attributes->get('keys');
    $route_name = $this->routeMatch->getRouteName();
    $reduced_filters = [];
    $summary_items = [];

    $query = $this->searchQueryBuilder->getQuery();

    // Dependent filters.
    $this->searchQueryBuilder->queryBuilder($query);
    $result_count = $query->execute()->getResultCount();

    if ($keys) {
      $summary_items[] = $keys;
    }

    foreach ($filters as $filter) {
      $criteria = explode(':', $filter);
      $reduced_filters = (isset($criteria[0])) ? $this->facetBuilder->getCurrentSearchSummary($criteria[0]) : '';

      if ($reduced_filters['needed']) {
        foreach ($reduced_filters['reduced_filter'] as $reduced_filter) {
          $querys = $reduced_filter['query'] ?? [];
          foreach ($querys as $key => $query) {
            if ($query == $reduced_filter['filter']) {
              unset($querys[$key]);
            }
          }

          $item_label = isset($reduced_filter['label']) ? $reduced_filter['label'] : '';
          $item_label = is_array($item_label) ? reset($item_label) : $item_label;
          $path = Url::fromRoute($route_name, ['f' => $querys, 'keys' => $keys]);
          $path_string = Link::fromTextAndUrl("(-)", $path)->toString();
          $summary_items[$reduced_filter['value']] = $this->t('@path_string @label', ['@path_string' => $path_string, '@label' => $item_label]);
        }
      }
    }

    if ($result_count > 0 && ($filters || $keys)) {
      array_unshift($summary_items, $this->t('Search found @count items', ['@count' => $result_count]));
    }

    $build['current_search_summary'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Current search'),
      '#items' => $summary_items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

}
