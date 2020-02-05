<?php

namespace Drupal\os\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;
use Drupal\os\Access\OsNodeRevisionAccessCheck;
use Drupal\vsite\Plugin\VsiteContextManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class OsNodeController.
 *
 * @package Drupal\os\Controller
 */
class OsNodeController extends NodeController {

  /**
   * Vsite Context Manager service.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManager
   */
  protected $vsiteManager;

  /**
   * Os Node revision access check.
   *
   * @var \Drupal\os\Access\OsNodeRevisionAccessCheck
   */
  protected $osNodeRevisionAccessCheck;

  /**
   * Constructs a NodeController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\vsite\Plugin\VsiteContextManager $vsite_context_manager
   *   Vsite manager instance.
   * @param \Drupal\os\Access\OsNodeRevisionAccessCheck $os_node_revision_access_check
   *   Node revision access check service.
   */
  public function __construct(DateFormatterInterface $date_formatter, RendererInterface $renderer, EntityRepositoryInterface $entity_repository, VsiteContextManager $vsite_context_manager, OsNodeRevisionAccessCheck $os_node_revision_access_check) {
    parent::__construct($date_formatter, $renderer, $entity_repository);
    $this->vsiteManager = $vsite_context_manager;
    $this->osNodeRevisionAccessCheck = $os_node_revision_access_check;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('renderer'),
      $container->get('entity.repository'),
      $container->get('vsite.context_manager'),
      $container->get('access_check.node.revision')
    );
  }

  /**
   * Generates an overview table of older revisions of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   An array as expected by \Drupal\Core\Render\RendererInterface::render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function revisionOverview(NodeInterface $node) {
    // If not in vsite context use core's controller.
    if (!$this->vsiteManager->getActiveVsite()) {
      return parent::revisionOverview($node);
    }
    $account = $this->currentUser();
    $langcode = $node->language()->getId();
    $langname = $node->language()->getName();
    $languages = $node->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $node_storage = $this->entityTypeManager()->getStorage('node');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $node->label()]) : $this->t('Revisions for %title', ['%title' => $node->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    // Use overridden service to check access.
    $revert_permission = $this->osNodeRevisionAccessCheck->checkAccess($node, $account, 'update') && $node->access('update');
    $delete_permission = $this->osNodeRevisionAccessCheck->checkAccess($node, $account, 'delete') && $node->access('delete');

    $rows = [];
    $default_revision = $node->getRevisionId();
    $current_revision_displayed = FALSE;

    foreach ($this->getRevisionIds($node, $node_storage) as $vid) {
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $node_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->revision_timestamp->value, 'short');

        // We treat also the latest translation-affecting revision as current
        // revision, if it was the default revision, as its values for the
        // current language will be the same of the current default revision in
        // this case.
        $is_current_revision = $vid == $default_revision || (!$current_revision_displayed && $revision->wasDefaultRevision());
        if (!$is_current_revision) {
          $link = Link::fromTextAndUrl($date, Url::fromRoute('entity.node.revision', ['node' => $node->id(), 'node_revision' => $vid]))->toString();
        }
        else {
          $link = $node->toLink($date)->toString();
          $current_revision_displayed = TRUE;
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => ['#markup' => $revision->revision_log->value, '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $this->renderer->addCacheableDependency($column['data'], $username);
        $row[] = $column;

        if ($is_current_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];

          $rows[] = [
            'data' => $row,
            'class' => ['revision-current'],
          ];
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $vid < $node->getRevisionId() ? $this->t('Revert') : $this->t('Set as current revision'),
              'url' => $has_translations ?
              Url::fromRoute('node.revision_revert_translation_confirm', [
                'node' => $node->id(),
                'node_revision' => $vid,
                'langcode' => $langcode,
              ]
              ) :
              Url::fromRoute('node.revision_revert_confirm', [
                'node' => $node->id(),
                'node_revision' => $vid,
              ]
              ),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('node.revision_delete_confirm', [
                'node' => $node->id(),
                'node_revision' => $vid,
              ]
              ),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];

          $rows[] = $row;
        }
      }
    }

    $build['node_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
      '#attached' => [
        'library' => ['node/drupal.node.admin'],
      ],
      '#attributes' => ['class' => 'node-revision-table'],
    ];

    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

}
