<?php

/**
 * @file
 * Publication Import Batch functions.
 */

use Drupal\bibcite_entity\Entity\Reference;
use Drupal\bibcite\Plugin\BibciteFormatInterface;

/**
 * Batch operation callback. Denormalize entries and try to save entity.
 *
 * @param array $entry
 *   Parsed entry array.
 * @param \Drupal\bibcite\Plugin\BibciteFormatInterface $format
 *   Instance of format plugin.
 * @param array $context
 *   The batch context array, passed by reference.
 */
function cp_import_publication_batch_callback(array $entry, BibciteFormatInterface $format, array &$context) {
  if (!isset($context['results'])) {
    $context['results']['success'] = [];
    $context['results']['errors'] = [];
  }

  /** @var \Symfony\Component\Serializer\Serializer $serializer */
  $serializer = \Drupal::service('serializer');

  $config = \Drupal::config('bibcite_import.settings');
  $denormalize_context = [
    'contributor_deduplication' => $config->get('settings.contributor_deduplication'),
    'keyword_deduplication' => $config->get('settings.keyword_deduplication'),
  ];

  /** @var \Drupal\Core\Entity\EntityInterface $entity */
  try {
    $entity = $serializer->denormalize($entry, Reference::class, $format->getPluginId(), $denormalize_context);
  }
  catch (UnexpectedValueException $e) {
    // Skip import for this row.
  }

  if (!empty($entity)) {
    try {
      if ($entity->save()) {
        $context['results']['success'][] = $entity->id() . ' : ' . $entity->label();
        /** @var \Drupal\cp_import\Helper\CpImportHelper $cpImportHelper */
        $cpImportHelper = \Drupal::service('cp_import.helper');
        // Map Title and Abstract fields.
        $cpImportHelper->mapPublicationHtmlFields($entity);
        // Add newly saved entity to the group in context.
        $cpImportHelper->addPublicationToVsite($entity->id(), 'group_entity:bibcite_reference');
      }
    }
    catch (Exception $e) {
      $message = [
        t('Entity can not be saved.'),
        t('Label: @label', ['@label' => $entity->label()]),
        '<pre>',
        $e->getMessage(),
        '</pre>',
      ];
      \Drupal::logger('bibcite_import')->error(implode("\n", $message));
      $context['results']['errors'][] = $entity->label();
    }

    $context['message'] = $entity->label();
  }
}

/**
 * Complete a batch process.
 *
 * @param bool $success
 *   A boolean indicating whether the batch has completed successfully.
 * @param array $results
 *   The value set in $context['results'] by callback_batch_operation().
 * @param array|bool $operations
 *   If $success is FALSE, contains the operations that remained unprocessed.
 */
function cp_import_publication_batch_finished($success, array $results, $operations) {
  $messenger = \Drupal::messenger();
  if ($success) {
    $message = \Drupal::translation()->formatPlural(count($results['success']), 'One entity processed.', '@count entities processed.');

    if (!empty($results['errors'])) {
      $error_message = \Drupal::translation()->formatPlural(count($results['errors']), 'One entry has not been processed.', '@count entries has not been processed.');
      $messenger->addError($error_message);
    }
  }
  else {
    $message = t('Finished with an error.');
  }

  $messenger->addStatus($message);
}
