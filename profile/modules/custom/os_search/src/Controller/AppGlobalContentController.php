<?php

namespace Drupal\os_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\vsite\Plugin\AppManager;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api_page\SearchApiPageInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\os_search\OsSearchQueryBuilder;

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
  public function __construct(RequestStack $request_stack, AppManager $app_manager, OsSearchQueryBuilder $os_search_query_builder, RendererInterface $renderer) {
    $this->requestStack = $request_stack;
    $this->appManager = $app_manager;
    $this->searchQueryBuilder = $os_search_query_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('vsite.app.manager'),
      $container->get('os_search.os_search_query_builder'),
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

    $build['#theme'] = 'search_api_page';
    /* @var $search_api_page \Drupal\search_api_page\SearchApiPageInterface */
    $search_api_page = $this->entityTypeManager()
      ->getStorage('search_api_page')
      ->load('search');

    /* @var $items \Drupal\search_api\Item\ItemInterface[] */
    $items = $available_bundle_content->getResultItems();

    $results = [];
    foreach ($items as $item) {
      $rendered = $this->createItemRenderArray($item, $search_api_page);
      if ($rendered) {
        $results[] = $rendered;
      }
    }

    return $this->finishBuildWithResults($build, $available_bundle_content, $results, $search_api_page);
  }

  /**
   * Load index based on requested app.
   */
  private function loadApp($app_requested) {
    $searchApiIndexStorage = $this->entityTypeManager()->getStorage('search_api_index');
    $index = $searchApiIndexStorage->load('os_search_index');
    $query = $index->query();
    $query->keys('');
    $query->addTag('get_taxonomy');
    // Dependent filters.
    $this->searchQueryBuilder->queryBuilder($query);
    return $query->execute();
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

    $this->moduleHandler()->alter('search_api_page', $build, $result, $search_api_page);
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
