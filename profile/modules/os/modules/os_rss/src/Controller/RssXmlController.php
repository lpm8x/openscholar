<?php

namespace Drupal\os_rss\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\os_app_access\AppLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Controller\TitleResolver;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Path\AliasManager;

/**
 * Controller that renders rss feed.
 */
class RssXmlController extends ControllerBase {

  /**
   * App Loader service.
   *
   * @var \Drupal\os_app_access\AppLoader
   */
  protected $appLoader;

  /**
   * Serialization service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Current Path service.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Route match object.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Route match object.
   *
   * @var \Drupal\Core\Controller\TitleResolver
   */
  protected $titleResolver;

  /**
   * Route match object.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Route match object.
   *
   * @var \Drupal\Core\Path\AliasManager
   */
  protected $aliasManager;

  /**
   * Term Storage variable.
   *
   * @var \Drupal\taxonomy\TermStorage
   */
  protected $termStorage;

  /**
   * User Storage variable.
   *
   * @var \Drupal\user\UserStorage
   */
  protected $userStorage;

  /**
   * Node Storage variable.
   *
   * @var \Drupal\node\NodeStorage
   */
  protected $nodeStorage;

  /**
   * Node Storage variable.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $publicationStorage;

  /**
   * Site Base Url variable.
   *
   * @var string|null
   */
  protected $baseUrl;

  /**
   * The xml elements metadata used in RSS Feeds.
   */
  const DCELEMENTS = 'http://purl.org/dc/elements/1.1/';

  /**
   * The xml style version used in RSS Feeds.
   */
  const DCVERSION = '2.0';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('os_app_access.app_loader'),
      $container->get('serializer'),
      $container->get('request_stack'),
      $container->get('path.current'),
      $container->get('current_route_match'),
      $container->get('title_resolver'),
      $container->get('date.formatter'),
      $container->get('path.alias_manager')
    );
  }

  /**
   * Constructor to get this object.
   */
  public function __construct(AppLoader $app_loader, Serializer $serializer, RequestStack $request_stack, CurrentPathStack $current_path, CurrentRouteMatch $route_match, TitleResolver $title_resolver, DateFormatter $date_formatter, AliasManager $alias_manager) {
    $this->appLoader = $app_loader;
    $this->serializer = $serializer;
    $this->requestStack = $request_stack;
    $this->currentPath = $current_path;
    $this->routeMatch = $route_match;
    $this->titleResolver = $title_resolver;
    $this->dateFormatter = $date_formatter;
    $this->aliasManager = $alias_manager;
    $this->termStorage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $this->userStorage = $this->entityTypeManager()->getStorage('user');
    $this->nodeStorage = $this->entityTypeManager()->getStorage('node');
    $this->publicationStorage = $this->entityTypeManager()->getStorage('bibcite_reference');
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $item = [];

    // Get query parameters.
    $request = $this->requestStack->getCurrentRequest();
    $this->baseUrl = $request->getSchemeAndHttpHost();

    // Load all content of particular type.
    $type = $request->query->get('type');
    if ($type) {
      $item = $this->prepareNodes($type);
    }

    // Load all terms of particular vocabulary.
    $machine_name = $request->query->get('term');
    if ($machine_name) {
      $item = array_merge($item, $this->prepareTerms($machine_name));
    }

    // Return publication items required by RSS Feeds.
    $pub_type = $request->query->get('type');
    if ($pub_type) {
      $item = array_merge($item, $this->preparePublications($pub_type));
    }

    $path = $this->currentPath->getPath();
    $page_title = $this->titleResolver->getTitle($request, $this->routeMatch->getRouteObject());
    $xml_data = [
      '@xmlns:dc' => self::DCELEMENTS,
      '@version' => self::DCVERSION,
      '@xml:base' => $this->baseUrl . $path,
      'channel' => [
        'title' => $page_title,
        'link' => $this->baseUrl . $path,
        'description' => $this->t('Openscholar RSS Feeds will show latest apps, publications and terms on the site.'),
        'language' => $this->languageManager()->getDefaultLanguage()->getId(),
        'item' => $item,
      ],
    ];
    $output = $this->serializer->serialize($xml_data, 'xml', ['xml_root_node_name' => 'rss']);

    $response = new Response();
    $response->setContent($output);
    $response->headers->set('Content-Type', 'text/xml');
    $response->setMaxAge(0);
    $response->expire();

    return $response;
  }

  /**
   * Return an array of node items required by RSS Feed.
   *
   * @param string $type
   *   The app content type name.
   *
   * @return array
   *   Array containing list of terms in that vocabulary.
   */
  private function prepareNodes($type) {
    $item = [];
    $values = [
      'type' => $type,
    ];
    $nodes = $this->nodeStorage->loadByProperties($values);

    foreach ($nodes as $node) {
      $uid = $node->getOwnerId();
      $user = $this->userStorage->load($uid);
      $item[] = [
        'title' => $node->get('title')->value,
        'link' => $node->toUrl()->setAbsolute()->toString(),
        'description' => htmlspecialchars($node->get('body')->value),
        'pubdate' => $this->dateFormatter->format($node->get('created')->value, 'long'),
        'dc:creator' => $user->get('name')->getValue()[0]['value'],
        'guid' => $node->id() . " " . $this->t("at") . " " . $this->baseUrl,
      ];
    }

    return $item;
  }

  /**
   * Return an array of term items required by RSS Feed.
   *
   * @param string $machine_name
   *   The vocabulary machine name.
   *
   * @return array
   *   Array containing list of terms in that vocabulary.
   */
  private function prepareTerms($machine_name) {
    $item = [];
    $terms = $this->termStorage->loadTree($machine_name);
    foreach ($terms as $term) {
      $term_obj = $this->termStorage->load($term->tid);

      $item[] = [
        'title' => $term->name,
        'link' => $term_obj->toUrl()->setAbsolute()->toString(),
        'description' => htmlspecialchars($term_obj->get('description')->value),
        'pubdate' => $this->dateFormatter->format($term->changed, 'long'),
        'guid' => $term->tid . " " . $this->t("at") . " " . $this->baseUrl,
      ];
    }

    return $item;
  }

  /**
   * Return an array of publication items required by RSS Feed.
   *
   * @param string $pub_type
   *   The publication type name.
   *
   * @return array
   *   Array containing list of terms in that vocabulary.
   */
  private function preparePublications($pub_type) {
    $item = [];
    $values = [
      'type' => $pub_type,
    ];
    $publications = $this->publicationStorage->loadByProperties($values);

    foreach ($publications as $publication) {
      $uid = $publication->get('uid')->getValue()[0]['target_id'];

      $user = $this->userStorage->load($uid);
      $item[] = [
        'title' => $publication->get('html_title')->value,
        'link' => $publication->toUrl()->setAbsolute()->toString(),
        'dc:creator' => $user->get('name')->getValue()[0]['value'],
        'pubdate' => $this->dateFormatter->format($publication->get('created')->value, 'long'),
        'guid' => $publication->id() . " " . $this->t("at") . " " . $this->baseUrl,
      ];
    }

    return $item;
  }

}