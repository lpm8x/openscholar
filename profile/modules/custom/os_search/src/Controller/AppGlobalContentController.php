<?php

namespace Drupal\os_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\vsite\Plugin\AppManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Access\AccessResult;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api_page\SearchApiPageInterface;
use Drupal\search_api\Query\ResultSetInterface;

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
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Class constructor.
   */
  public function __construct(RequestStack $request_stack, AppManager $app_manager, EntityTypeManagerInterface $entity_manager, RendererInterface $renderer) {
    $this->requestStack = $request_stack;
    $this->appManager = $app_manager;
    $this->entityManager = $entity_manager;
    $this->nodeStorage = $this->entityManager->getStorage('node');
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('vsite.app.manager'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Check if request app url is available or not.
   */
  public function getAppList(Request $request) {

    $build = [];

    $app_requested = $this->requestStack->getCurrentRequest()->attributes->get('app');

    $available_bundle_content = $this->loadApp($app_requested);

    $search_api_page_name = 'search';
    $build['#theme'] = 'search_api_page';
    /* @var $search_api_page \Drupal\search_api_page\SearchApiPageInterface */
    $search_api_page = $this->entityManager()
      ->getStorage('search_api_page')
      ->load($search_api_page_name);

    $result = $available_bundle_content;

    /* @var $items \Drupal\search_api\Item\ItemInterface[] */
    $items = $result->getResultItems();

    $results = [];
    foreach ($items as $item) {
      $rendered = $this->createItemRenderArray($item, $search_api_page);
      if ($rendered === []) {
        continue;
      }
      $results[] = $rendered;
    }

    return $this->finishBuildWithResults($build, $result, $results, $search_api_page);
  }

  /**
   * Load index based on requested app.
   */
  private function loadApp($app_requested) {

    /** @var \Drupal\vsite\AppInterface[] $apps */
    $enabled_apps = $this->appManager->getDefinitions();

    $index = Index::load('os_search_index');
    $query = $index->query();
    $query->keys('');
    $enabled_apps_list = [];

    if (isset($enabled_apps[$app_requested]['bundle'])) {
      $enabled_apps_list = array_merge($enabled_apps_list, $enabled_apps[$app_requested]['bundle']);
    }
    else {
      $enabled_apps_list[] = $enabled_apps[$app_requested]['entityType'];
    }

    if ($enabled_apps_list) {
      // $query->addCondition('custom_entity_app_status', 1);.
      $query->addCondition('custom_search_bundle', $enabled_apps_list, 'IN');
    }

    $results = $query->execute();

    return $results;
  }

  /**
   * Checks access for page /browse/{app}.
   */
  public function access() {

    $app_requested = $this->requestStack->getCurrentRequest()->attributes->get('app');

    /** @var \Drupal\vsite\AppInterface[] $apps */
    $apps = $this->appManager->getDefinitions();
    if ($apps[$app_requested]['id'] == $app_requested) {
      return AccessResult::allowed();
    }

    // Return 403 Access Denied page.
    return AccessResult::forbidden();
  }

  /**
   * Creates a render array for the given result item.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item to render.
   * @param \Drupal\search_api_page\SearchApiPageInterface $search_api_page
   *   The search api page.
   *
   * @return array
   *   A render array for the given result item.
   */
  protected function createItemRenderArray(ItemInterface $item, SearchApiPageInterface $search_api_page) {
    try {
      $originalObject = $item->getOriginalObject();
      if ($originalObject === NULL) {
        return [];
      }
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $originalObject->getValue();
    }
    catch (SearchApiException $e) {
      return [];
    }

    if (!$entity) {
      return [];
    }

    $viewedResult = [];
    if ($search_api_page->renderAsViewModes()) {
      $datasource_id = 'entity:' . $entity->getEntityTypeId();
      $bundle = $entity->bundle();
      $viewMode = $search_api_page->getViewModeConfig()->getViewMode($datasource_id, $bundle);
      $viewedResult = $this->entityTypeManager()->getViewBuilder($entity->getEntityTypeId())->view($entity, $viewMode);
    }
    if ($search_api_page->renderAsSnippets()) {
      $viewedResult = [
        '#theme' => 'search_api_page_result',
        '#item' => $item,
        '#entity' => $entity,
      ];
    }

    $metadata = CacheableMetadata::createFromRenderArray($viewedResult);
    $metadata->addCacheTags(['search_api_page.style']);
    $metadata->applyTo($viewedResult);
    return $viewedResult;
  }

  /**
   * Adds the results to the given build and then finishes it.
   *
   * @param array $build
   *   The build.
   * @param \Drupal\search_api\Query\ResultSetInterface $result
   *   Search API result.
   * @param array $results
   *   The result item render arrays.
   * @param \Drupal\search_api_page\SearchApiPageInterface $search_api_page
   *   The search api page.
   *
   * @return array
   *   The finished build.
   */
  protected function finishBuildWithResults(array $build, ResultSetInterface $result, array $results, SearchApiPageInterface $search_api_page) {

    $build['#search_title'] = [
      '#markup' => $this->t('Search results'),
    ];

    $build['#no_of_results'] = [
      '#markup' => $this->formatPlural($result->getResultCount(), '1 result found', '@count results found'),
    ];

    $build['#results'] = $results;

    pager_default_initialize($result->getResultCount(), $search_api_page->getLimit());
    $build['#pager'] = [
      '#type' => 'pager',
    ];

    return $this->finishBuild($build, $search_api_page, $result);
  }

  /**
   * Finishes the build.
   *
   * @param array $build
   *   An array containing all page elements.
   * @param \Drupal\search_api_page\SearchApiPageInterface $searchApiPage
   *   The Search API page entity.
   * @param \Drupal\search_api\Query\ResultSetInterface $result
   *   Search API result.
   *
   * @return array
   *   An array containing all page elements.
   */
  protected function finishBuild(array $build, SearchApiPageInterface $searchApiPage, ResultSetInterface $result = NULL) {
    $this->moduleHandler()->alter('search_api_page', $build, $result, $searchApiPage);

    // TODO caching dependencies.
    // @see https://www.drupal.org/project/search_api_page/issues/2754411.
    return $build;
  }

  /**
   * Title callback.
   */
  public function getTitle() {
    // Provide a dynamic title.
    $app_requested = $this->requestStack->getCurrentRequest()->attributes->get('app');

    /** @var \Drupal\vsite\AppInterface[] $apps */
    $apps = $this->appManager->getDefinitions();
    return $apps[$app_requested]['title']->__toString();
  }

}
