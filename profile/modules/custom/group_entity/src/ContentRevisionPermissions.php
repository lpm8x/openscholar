<?php

namespace Drupal\group_entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\NodeType;

/**
 * Class ContentRevisionPermissions.
 */
class ContentRevisionPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of node type permissions.
   *
   * @return array
   *   The node type permissions.
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function nodeTypeRevisionPermissions() {
    $perms = [];
    // Generate node permissions for all node types.
    foreach (NodeType::loadMultiple() as $type) {
      $perms += $this->buildPermissions($type);
    }

    return $perms;
  }

  /**
   * Returns a list of node permissions for a given node type.
   *
   * @param \Drupal\node\Entity\NodeType $type
   *   The node type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(NodeType $type) {
    $type_id = $type->id();
    $label = $type->label();
    $plugin_id = "group_node:$type_id";

    return [
      "view $plugin_id revisions" => [
        'title' => "$label: View revisions",
        'description' => 'To view a revision, you also need permission to view the content item.',
        'section' => 'Entity Revision',
      ],
      "revert $plugin_id revisions" => [
        'title' => "$label: Revert revisions",
        'description' => 'To revert a revision, you also need permission to edit the content item.',
        'section' => 'Entity Revision',
      ],
      "delete $plugin_id revisions" => [
        'title' => "$label: Delete revisions",
        'description' => 'To delete a revision, you also need permission to delete the content item.',
        'section' => 'Entity Revision',
      ],
    ];
  }

}
