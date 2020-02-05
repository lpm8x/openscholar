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
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Global App Search Block.
 *
 * @Block(
 *   id = "global_app_taxonomy_filter",
 *   admin_label = @Translation("Global APP Taxonomy Filter"),
 * )
 */
class OsSearchTaxonomyGlobalAPPBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $route_name = $this->routeMatch->getRouteName();
    $request = $this->requestStack->getCurrentRequest();
    $attributes = $request->attributes->all();

    $query_string_params = $request->query->all();
    $query_params = [];
    foreach ($query_string_params as $key => $query_param) {

      if (strpos($key, '_') === FALSE) {
        $query_params[$key] = $query_param;
      }
    }
    $count_params = count($query_params);
    $query_params['app'] = isset($attributes['app']) ? $attributes['app'] : '';
    $items = [];
    if (strpos($route_name, 'os_search.app_global') !== FALSE) {
      // $titles = $this->appHelper->getAppLists();
      $index = $this->searchApiIndexStorage->load('os_search_index');
      $query = $index->query();
      $query->keys('');
      $query->setOption('search_api_facets', [
        'custom_taxonomy' => [
          'field' => 'custom_taxonomy',
          'limit' => 90,
          'operator' => 'OR',
          'min_count' => 1,
          'missing' => FALSE,
        ],
      ]);
      $query->sort('search_api_relevance', 'DESC');
      $query->addTag('global_taxonomy_filter');
      $results = $query->execute();
      $facets = $results->getExtraData('elasticsearch_response', []);

      // Get indexed bundle types.
      $buckets = isset($facets['aggregations']) ? $facets['aggregations']['custom_taxonomy']['buckets'] : [];

      $vocabularies = Vocabulary::loadMultiple();

      $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
      foreach ($buckets as $bucket) {
        $term = $termStorage->load($bucket['key']);
        $name = $term->get('name')->value;
        $facet_array['f' . $count_params] = 'custom_taxonomy_text:' . $bucket['key'];
        $url = Url::fromRoute($route_name, $query_params + $facet_array);

        $title = $this->t('@app_title (@count)', ['@app_title' => $name, '@count' => $bucket['doc_count']]);
        $vname = $vocabularies[$term->getVocabularyId()]->get('name');
        $items[$vname][] = Link::fromTextAndUrl($title, $url)->toString();

      }
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

}
