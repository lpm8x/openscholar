<?php

namespace Drupal\os_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\vsite_privacy\Plugin\VsitePrivacyLevelManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\search_api\Entity\Index;

/**
 * Global App Filter By Date Block.
 *
 * @Block(
 *   id = "global_app_filter_by_date",
 *   admin_label = @Translation("Global APP Filter By Date."),
 * )
 */
class OsFilterByDateGlobalAPPBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Search sort type.
   */
  const SORT_TYPE = ['title', 'type', 'date'];

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * Vsite privacy service.
   *
   * @var \Drupal\vsite_privacy\Plugin\VsitePrivacyLevelManagerInterface
   */
  protected $privacyManager;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match, RequestStack $request_stack, VsitePrivacyLevelManagerInterface $privacy_manager, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
    $this->privacyManager = $privacy_manager;
    $this->currentUser = $current_user;
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
      $container->get('vsite.privacy.manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = $this->routeMatch->getRouteName();

    $items = [];

    if (strpos($route_name, 'os_search.app_global') !== FALSE) {
      $index = Index::load('os_search_index');
      $query = $index->query();
      $query->keys('');
      $request = $this->requestStack->getCurrentRequest();
      ksm($request);

      $query_string_params = $request->query->all();
      $attributes = $request->attributes->all();

      $query->setOption('search_api_facets', [
        'custom_date' => [
          'field' => 'custom_date',
          'limit' => 90,
          'operator' => 'OR',
          'min_count' => 1,
          'missing' => FALSE,
        ],
      ]);

      $results = $query->execute();
      $facets = $results->getExtraData('elasticsearch_response', []);
      // Get indexed bundle types.
      $buckets = $facets['aggregations']['custom_date']['buckets'];
      $query_params = [
        'year' => isset($query_string_params['year']) ? $query_string_params['year'] : '',
        'month' => isset($query_string_params['month']) ? $query_string_params['month'] : '',
        'day' => isset($query_string_params['day']) ? $query_string_params['day'] : '',
        'hour' => isset($query_string_params['hour']) ? $query_string_params['hour'] : '',
        'minutes' => isset($query_string_params['minutes']) ? $query_string_params['minutes'] : '',
      ];

      $year = $query_params['year'];
      $month = $query_params['month'];
      $day = $query_params['day'];
      $hour = $query_params['hour'];
      $minutes = $query_params['minutes'];

      // Declaration of array which will hold required query parameter.
      $query_params['app'] = $attributes['app'];

      // Generating links from custom_date facets.
      // Using timestamp for condition filter the records to create links.
      $created_date = [];
      // $created_date['app'] = $attributes['app'];.
      foreach ($buckets as $bundle) {
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

      foreach ($query_string as $key => $query_para) {
        $query_paramater[$key] = $query_para;
        $url = Url::fromRoute($route_name, $query_paramater);
        if (isset($created_date[$key])) {
          $items[$key] = Link::fromTextAndUrl($created_date[$key], $url)->toString();
        }

      }

    }

    $build['link-list'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Filter By Date'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

}
