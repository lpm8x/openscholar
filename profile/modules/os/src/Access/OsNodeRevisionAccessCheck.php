<?php

namespace Drupal\os\Access;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Access\NodeRevisionAccessCheck;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\os\AccessHelperInterface;
use Drupal\vsite\Plugin\VsiteContextManager;

/**
 * Provides an access checker for node revisions.
 *
 * @ingroup node_access
 */
class OsNodeRevisionAccessCheck extends NodeRevisionAccessCheck {

  /**
   * Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Access check helper service.
   *
   * @var \Drupal\os\AccessHelperInterface
   */
  protected $accessHelper;

  /**
   * Vsite Manager service.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManager
   */
  protected $vsiteManager;

  /**
   * OsNodeRevisionAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager instance.
   * @param \Drupal\os\AccessHelperInterface $access_helper
   *   Access Helper.
   * @param \Drupal\vsite\Plugin\VsiteContextManager $vsite_manager
   *   Vsite Context Manager instance.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccessHelperInterface $access_helper, VsiteContextManager $vsite_manager) {
    parent::__construct($entity_type_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->accessHelper = $access_helper;
    $this->vsiteManager = $vsite_manager;
  }

  /**
   * Checks node revision access.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   * @param string $op
   *   (optional) The specific operation being checked. Defaults to 'view.'.
   *
   * @return bool
   *   TRUE if the operation may be performed, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkAccess(NodeInterface $node, AccountInterface $account, $op = 'view') {
    /** @var \Drupal\group\Entity\Group $active_vsite */
    $active_vsite = $this->vsiteManager->getActiveVsite();
    // If not in vsite context use core's access check.
    if (!$active_vsite) {
      return parent::checkAccess($node, $account, $op);
    }

    $map = [
      'view' => 'view all revisions',
      'update' => 'revert all revisions',
      'delete' => 'delete all revisions',
    ];
    $bundle = $node->bundle();
    $type_map = [
      'view' => "view group_node:$bundle revisions",
      'update' => "revert group_node:$bundle revisions",
      'delete' => "delete group_node:$bundle revisions",
    ];

    if (!$node || !isset($map[$op]) || !isset($type_map[$op])) {
      // If there was no node to check against, or the $op was not one of the
      // supported ones, we return access denied.
      return FALSE;
    }

    // Statically cache access by revision ID, language code, user account ID,
    // and operation.
    $langcode = $node->language()->getId();
    $cid = $node->getRevisionId() . ':' . $langcode . ':' . $account->id() . ':' . $op;

    if (!isset($this->access[$cid])) {
      // Perform basic permission checks first.
      if (!$active_vsite->hasPermission($map[$op], $account) && !$active_vsite->hasPermission($type_map[$op], $account)) {
        $this->access[$cid] = FALSE;
        return FALSE;
      }
      // If the revisions checkbox is selected for the content type, display the
      // revisions tab.
      $bundle_entity_type = $node->getEntityType()->getBundleEntityType();
      $bundle_entity = $this->entityTypeManager->getStorage($bundle_entity_type)->load($bundle);
      if ($bundle_entity->shouldCreateNewRevision() && $op === 'view') {
        $this->access[$cid] = TRUE;
      }
      else {
        // Check if the user has one of the revision permissions and if also
        // the user has update/delete access based on the operation.
        $this->access[$cid] = ($active_vsite->hasPermission($map[$op], $account) || $active_vsite->hasPermission($type_map[$op], $account)) && $this->accessHelper->checkAccess($node, $op, $account)->isAllowed();
      }
    }
    return $this->access[$cid];
  }

}
