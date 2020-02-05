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
use Drupal\Core\Url;
use Drupal\os_search\ListAppsHelper;
use Drupal\Core\Link;

/**
 * Global App Filter By Post Block.
 *
 * @Block(
 *   id = "global_app_filter_by_post",
 *   admin_label = @Translation("Global APP Filter By Post."),
 * )
 */
class OsFilterByPostGlobalAPPBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * App titles.
   *
   * @var \Drupal\os_search\ListAppsHelper
   */
  protected $appHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct($configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $route_match, RequestStack $request_stack, VsitePrivacyLevelManagerInterface $privacy_manager, AccountInterface $current_user, ListAppsHelper $app_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
    $this->privacyManager = $privacy_manager;
    $this->currentUser = $current_user;
    $this->appHelper = $app_helper;
    $this->searchApiIndexStorage = $this->entityTypeManager->getStorage('search_api_index');
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
      $container->get('current_user'),
      $container->get('os_search.list_app_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = $this->routeMatch->getRouteName();
    $request = $this->requestStack->getCurrentRequest();
    $attributes = $request->attributes->all();
    $query_params['app'] = isset($attributes['app']) ? $attributes['app'] : '';
    $items = [];
    if (strpos($route_name, 'os_search.app_global') !== FALSE) {
      $titles = $this->appHelper->getAppLists();
      $index = $this->searchApiIndexStorage->load('os_search_index');
      $query = $index->query();
      $query->keys('');
      $query->setOption('search_api_facets', [
        'custom_search_bundle' => [
          'field' => 'custom_search_bundle',
          'limit' => 90,
          'operator' => 'OR',
          'min_count' => 1,
          'missing' => FALSE,
        ],
      ]);

      $results = $query->execute();
      $facets = $results->getExtraData('elasticsearch_response', []);

      // Get indexed bundle types.
      $buckets = $facets['aggregations']['custom_search_bundle']['buckets'];

      foreach ($buckets as $bundle) {
        $facet_array['f1'] = 'custom_bundle_text:' . $bundle['key'];
        $url = Url::fromRoute($route_name, $query_params + $facet_array);
        $title = $this->t('@app_title (@count)', ['@app_title' => $titles[$bundle['key']], '@count' => $bundle['doc_count']]);
        $items[] = Link::fromTextAndUrl($title, $url)->toString();
      }

    }
    $build['link-list'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Filter By Post'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

}
