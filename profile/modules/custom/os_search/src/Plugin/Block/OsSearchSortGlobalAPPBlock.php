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

/**
 * Global App Search Block.
 *
 * @Block(
 *   id = "global_app_search_sort",
 *   admin_label = @Translation("Global APP Search Sort"),
 * )
 */
class OsSearchSortGlobalAPPBlock extends BlockBase implements ContainerFactoryPluginInterface {

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

    if (strpos($route_name, 'os_search.app_global') !== FALSE) {
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
      if ($query_params['sort']) {
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
        if ($sort_dir[$link_type]) {
          $query_params['dir'] = $sort_dir[$link_type];
        }
        $url = Url::fromRoute($route_name, $query_params);
        $items[] = Link::fromTextAndUrl($this->t('@text', ['@text' => ucfirst($link_type)]), $url)->toString();
      }
    }
    // ksm($items);
    $build['link-list'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Relevance'),
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return $build;
  }

}
