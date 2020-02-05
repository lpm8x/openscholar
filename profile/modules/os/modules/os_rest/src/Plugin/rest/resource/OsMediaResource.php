<?php

namespace Drupal\os_rest\Plugin\rest\resource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class OsMediaResource.
 *
 * @package Drupal\os_rest\Plugin\rest\resource
 */
class OsMediaResource extends OsEntityResource {

  /**
   * Responds to media entity PATCH requests and overrides base patch method.
   *
   * @param \Drupal\Core\Entity\EntityInterface $original_entity
   *   The original entity object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function patch(EntityInterface $original_entity, EntityInterface $entity = NULL) {
    // Get the payload data from the request.
    $data = json_decode(\Drupal::request()->getContent(), TRUE);
    if ($entity == NULL) {
      throw new BadRequestHttpException('No entity content received.');
    }
    $definition = $this->getPluginDefinition();
    if ($entity->getEntityTypeId() != $definition['entity_type']) {
      throw new BadRequestHttpException('Invalid entity type');
    }

    /** @var \Drupal\os_media\MediaEntityHelper $mediaHelper */
    $mediaHelper = \Drupal::service('os_media.media_helper');
    // Special handle for changing filename, it will move to file.
    if (in_array('filename', $entity->_restSubmittedFields) && !empty($data['filename'])) {
      $field = $mediaHelper->getField($original_entity->bundle());
      $fileId = $original_entity->get($field)->get(0)->get('target_id')->getValue();
      /** @var \Drupal\file\Entity\File $fileEntityOrig */
      $fileEntityOrig = \Drupal::entityTypeManager()->getStorage('file')->load($fileId);
      $this->moveFile($fileEntityOrig, $data['filename']);
    }
    foreach ($entity->_restSubmittedFields as $key => $field_name) {
      if (in_array($field_name, $mediaHelper::FILE_FIELDS)) {
        // Unset field so that it does not throw error when parent method
        // is called as they do not exist in the media entity.
        unset($entity->_restSubmittedFields[$key]);
      }
    }

    return parent::patch($original_entity, $entity);
  }

  /**
   * Helper function that will move file on rename.
   *
   * @param \Drupal\file\FileInterface $fileEntityOrig
   *   File entity.
   * @param string $new_filename
   *   New file name with extension.
   */
  protected function moveFile(FileInterface $fileEntityOrig, string $new_filename) {
    $file_default_scheme = file_default_scheme();
    $purl = \Drupal::service('vsite.context_manager')->getActivePurl();

    $directory = "{$file_default_scheme}://files/";
    if ($purl) {
      $directory = "{$file_default_scheme}://{$purl}/files/";
    }

    if (!file_move($fileEntityOrig, $directory . $new_filename, FileSystemInterface::EXISTS_REPLACE)) {
      throw new BadRequestHttpException('Error moving file. Please contact your server administrator.');
    }
  }

}
