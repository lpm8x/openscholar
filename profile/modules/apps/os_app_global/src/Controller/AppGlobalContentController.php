<?php

namespace Drupal\os_app_global\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\vsite\Plugin\AppManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Entity\Index;

/**
 * Controller for the cp_users page.
 *
 * Also invokes the modals.
 */
class AppGlobalContentController extends ControllerBase {

  /**
   * Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManager
   */
  protected $appManager;

  /**
   * Entity Manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Class constructor.
   */
  public function __construct(RequestStack $request_stack, AppManager $app_manager, EntityTypeManagerInterface $entity_manager) {
    $this->requestStack = $request_stack;
    $this->appManager = $app_manager;
    $this->entityManager = $entity_manager;
    $this->nodeStorage = $this->entityManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('vsite.app.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Check if request app url is available or not.
   */
  public function getAppList() {

    $app_requested = $this->requestStack->getCurrentRequest()->attributes->get('app');

    $available_apps = $this->getAvailableApps($app_requested);

    if (in_array($app_requested, $available_apps['app_id'])) {
      $available_bundle_content = $this->loadApp($available_apps['entity_type'], $app_requested);
      ksm($available_bundle_content);
      return $this->redirect('<front>', [], ['absolute' => TRUE]);
    }

    return $this->redirect('<front>', [], ['absolute' => TRUE]);
  }

  /**
   * List of Available apps, with entity type.
   */
  private function getAvailableApps($app_requested) {
    $available_apps = [];
    $app_details = [];
    $app_entity_type = '';

    /** @var \Drupal\vsite\AppInterface[] $apps */
    $apps = $this->appManager->getDefinitions();

    foreach ($apps as $key => $app) {
      $available_apps[$key] = $app['id'];
      if ($app_requested == $app['id']) {
        $app_entity_type = $app['entityType'];
      }
    }
    $app_details['app_id'] = $available_apps;
    $app_details['entity_type'] = $app_entity_type;
    return $app_details;
  }

  /**
   * Load index based on requested app.
   */
  private function loadApp($app_entity_type, $app_requested) {
    $index = Index::load('os_search_index');
    $query = $index->query();
    $query->keys('');
    $query->setOption('custom_search_bundle', [
      'custom_search_bundle' => [
        'field' => 'custom_search_bundle',
        'limit' => 90,
        'operator' => 'OR',
        'min_count' => 1,
        'missing' => FALSE,
      ],
    ]);
    $query->sort('search_api_relevance', 'ASC');
    $results = $query->execute();
    $facets = $results->getExtraData('elasticsearch_response', []);
    $facets_result = $facets['hits']['hits'];
    foreach ($facets_result as $key => $facet) {
      if ($facet['_source']['custom_search_bundle'][0] == $app_requested) {
        $facet_content['title'][$key] = $facet['_source']['custom_title'];
        $facet_content['body'][$key] = '';
        if ($facet['_source']['body'] !== NULL && isset($facet['_source']['body'])) {
          $facet_content['body'][$key] = $facet['_source']['body'];
        }
      }
    }
    return $facet_content;
  }

}
