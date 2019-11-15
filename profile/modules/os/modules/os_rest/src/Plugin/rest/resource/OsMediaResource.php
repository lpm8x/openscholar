<?php

namespace Drupal\os_rest\Plugin\rest\resource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    $changedFields = [];
    $fileEntityOrig = [];
    /** @var \Drupal\os_media\MediaEntityHelper $mediaHelper */
    $mediaHelper = \Drupal::service('os_media.media_helper');
    foreach ($entity->_restSubmittedFields as $key => $field_name) {
      // Check for fields which actually exist in File entity to update them.
      if (in_array($field_name, $mediaHelper::FILE_FIELDS)) {
        $field = $mediaHelper->getField($entity->bundle());
        $fileId = $original_entity->get($field)->get(0)->get('target_id')->getValue();
        $fileEntityOrig = \Drupal::entityTypeManager()->getStorage('file')->load($fileId);
        $changedFields[] = $field_name;
        $fileEntityOrig->set($field_name, $data[$field_name]);
        // Unset field so that it does not throw error when parent method
        // is called as they do not exist in the media entity.
        unset($entity->_restSubmittedFields[$key]);
      }
    }

    if ($fileEntityOrig) {
      // Validate the received data before saving.
      $this->validate($fileEntityOrig, $changedFields);
      try {
        $fileEntityOrig->save();
        $this->logger->notice('Updated entity %type with ID %id.', [
          '%type' => $fileEntityOrig->getEntityTypeId(),
          '%id' => $fileEntityOrig->id(),
        ]);
        // Call the parent method to update remaining fields if any.
        return parent::patch($original_entity, $entity);
      }
      catch (EntityStorageException $e) {
        throw new HttpException(500, 'Internal Server Error', $e);
      }
    }
    return parent::patch($original_entity, $entity);
  }

}
