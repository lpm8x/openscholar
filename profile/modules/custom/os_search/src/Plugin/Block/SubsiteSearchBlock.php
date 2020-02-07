<?php

namespace Drupal\os_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\vsite_privacy\Plugin\VsitePrivacyLevelManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Subsite Search Block.
 *
 * @Block(
 *   id = "subsite_filter_block",
 *   admin_label = @Translation("Subsite Search"),
 * )
 */
class SubsiteSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    if (strpos($route_name, 'search_api_page') !== FALSE) {
      $index = Index::load('os_search_index');
      $query = $index->query();
      $query->keys('');
      $query->setOption('search_api_facets', [
        'custom_search_group' => [
          'field' => 'custom_search_group',
          'limit' => 0,
          'operator' => 'OR',
          'min_count' => 1,
          'missing' => FALSE,
        ],
      ]);
      $query->sort('search_api_relevance', 'DESC');
      $query->addTag('global_search_group');
      $results = $query->execute();
      $facets = $results->getExtraData('elasticsearch_response', []);

      // Get indexed bundle types.
      $buckets = isset($facets['aggregations']['custom_search_group']) ? $facets['aggregations']['custom_search_group']['buckets'] : [];
      $items = [];

      $groupStorage = $this->entityTypeManager->getStorage('group');
      $route_parameters = $this->routeMatch->getParameters()->all();
      foreach ($buckets as $bucket) {
        $vsite = $groupStorage->load($bucket['key']);
        if ($vsite) {
          $privacy_level = $vsite->get('field_privacy_level')->first()->getValue()['value'];
          if ($this->privacyManager->checkAccessForPlugin($this->currentUser, $privacy_level)) {
            $parameters = array_merge('vsite' => $bucket['key'], $route_parameters);
            $url = Url::fromRoute($route_name, $parameters);
            $title = $this->t('@app_title (@count)', ['@app_title' => $vsite->get('label')->value, '@count' => $bucket['doc_count']]);
            $items[] = Link::fromTextAndUrl($title, $url)->toString();
          }
        }
      }
    }

    $build['filter-other-sites'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Filter by other sites'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

}
