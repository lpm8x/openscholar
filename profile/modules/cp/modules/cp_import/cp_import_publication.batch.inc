<?php

/**
 * @file
 * Publication Import Batch functions.
 */

/**
 * Batch operation callback.
 *
 * @param array $entry
 *   Parsed entry array.
 * @param string $format_id
 *   Instance of format plugin.
 * @param array $context
 *   The batch context array, passed by reference.
 *
 * @throws \Drupal\Component\Plugin\Exception\PluginException
 */
function cp_import_publication_batch_callback(array $entry, string $format_id, array &$context) {
  if (!isset($context['results'])) {
    $context['results']['success'] = [];
    $context['results']['errors'] = [];
  }

  // Try to save entry as a new publication.
  /** @var \Drupal\cp_import\Helper\CpImportHelper $cpImportHelper */
  $cpImportHelper = \Drupal::service('cp_import.helper');
  $cpImportHelper->savePublicationEntity($entry, $format_id, $context);
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
