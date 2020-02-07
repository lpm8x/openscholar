<?php

namespace Drupal\cp_import\Helper;

use Drupal\bibcite_entity\Entity\Reference;
use Drupal\media\Entity\Media;

/**
 * CpImportHelperInterface.
 */
interface CpImportHelperInterface {

  /**
   * Get the media to be attached to the node.
   *
   * @param string $media_val
   *   The media value entered in the csv.
   * @param string $contentType
   *   Content type.
   *
   * @return \Drupal\media\Entity\Media|null
   *   Media entity/Null when not able to fetch/download media.
   */
  public function getMedia(string $media_val, string $contentType) : ?Media;

  /**
   * Adds the newly imported node to Vsite.
   *
   * @param string $id
   *   Entity to be added to the vsite.
   * @param string $pluginId
   *   Plugin id.
   * @param string $entityType
   *   Entity type id.
   */
  public function addContentToVsite(string $id, string $pluginId, $entityType): void;

  /**
   * Handles content path to uniquify or create aliases if needed.
   *
   * @param string $entityType
   *   Entity type id.
   * @param int $id
   *   Entity id in context for which to update alias.
   */
  public function handleContentPath(string $entityType, int $id): void;

  /**
   * Helper method to convert csv to array.
   *
   * @param string $filename
   *   File uri.
   * @param string $encoding
   *   Encoding of the file.
   *
   * @return array|string
   *   Data as an array or error string.
   */
  public function csvToArray($filename, $encoding);

  /**
   * Map fields and save entity.
   *
   * @param \Drupal\bibcite_entity\Entity\Reference $entity
   *   Bibcite Reference entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function mapPublicationHtmlFields(Reference $entity): void;

}
