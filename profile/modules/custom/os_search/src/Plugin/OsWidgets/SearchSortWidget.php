<?php

namespace Drupal\os_search\Plugin\OsWidgets;

use Drupal\os_widgets\OsWidgetsBase;
use Drupal\os_widgets\OsWidgetsInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Class SearchSortWidget.
 *
 * @OsWidget(
 *   id = "search_sort_widget",
 *   title = @Translation("Sort By")
 * )
 */
class SearchSortWidget extends OsWidgetsBase implements OsWidgetsInterface {

  /**
   * Search sort type.
   */
  const SORT_TYPE = ['title', 'type', 'date'];

  /**
   * Route Match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, Connection $connection, CurrentRouteMatch $route_match, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $connection);
    $this->routeMatch = $route_match;
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
      $container->get('database'),
      $container->get('current_route_match'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBlock(&$build, $block_content) {

    $route_name = $this->routeMatch->getRouteName();
    if (strpos($route_name, 'search_api_page') !== FALSE) {
      $request = $this->requestStack->getCurrentRequest();
      $query_params = $request->query->all();
      $attributes = $request->attributes->all();
      if ($attributes['keys']) {
        $query_params['keys'] = $attributes['keys'];
      }

      $link_types = self::SORT_TYPE;
      $items = [];
      $sort_dir = [];
      // Check if there is an exists sort param in query and flip the direction.
      if (isset($query_params['sort'])) {
        if ($query_params['dir'] == 'ASC') {
          $sort_dir[$query_params['sort']] = 'DESC';
        }
        else {
          $sort_dir[$query_params['sort']] = 'ASC';
        }
      }

      foreach ($link_types as $link_type) {
        $query_params['sort'] = $link_type;
        if ($query_params['sort'] == 'date') {
          $query_params['dir'] = 'DESC';
        }
        else {
          $query_params['dir'] = 'ASC';
        }
        if (isset($sort_dir[$link_type])) {
          $query_params['dir'] = $sort_dir[$link_type];
        }
        $url = Url::fromRoute($route_name, $query_params);
        $items[] = Link::fromTextAndUrl($this->t('@text', ['@text' => ucfirst($link_type)]), $url)->toString();
      }

      $build['link-list'] = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#title' => $this->t('Relevance'),
        '#items' => $items,
        '#cache' => [
          'max-age' => 0,
        ],
      ];
    }
  }

}
