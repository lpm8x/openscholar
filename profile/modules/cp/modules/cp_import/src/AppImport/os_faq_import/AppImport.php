<?php

namespace Drupal\cp_import\AppImport\os_faq_import;

use DateTime;
use Drupal\Component\Utility\UrlHelper;
use Drupal\cp_import\AppImport\Base;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;

/**
 * Class AppImport.
 *
 * @package Drupal\cp_import\AppImport\os_faq_import
 */
class AppImport extends Base {

  /**
   * Bundle type.
   *
   * @var string
   */
  protected $type = 'faq';

  /**
   * Group plugin id.
   *
   * @var string
   */
  protected $groupPluginId = 'group_node:faq';

  /**
   * {@inheritdoc}
   */
  public function preRowSaveActions(MigratePreRowSaveEvent $event) {
    $row = $event->getRow();
    $dest_val = $row->getDestination();
    $media_val = $dest_val['field_attached_media']['target_id'];
    // If not a valid url return and don't do anything , this reduces the risk
    // of malicious scripts as we do not want to support HTML media from here.
    if (!UrlHelper::isValid($media_val)) {
      return;
    }
    // Get the media.
    $media_entity = $this->cpImportHelper->getMedia($media_val, $this->type);
    if ($media_entity) {
      $row->setDestinationProperty('field_attached_media/target_id', $media_entity->id());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function migratePostRowSaveActions(MigratePostRowSaveEvent $event) {
    $ids = $event->getDestinationIdValues();
    foreach ($ids as $id) {
      $this->cpImportHelper->addContentToVsite($id, $this->groupPluginId, 'node');
    }
    parent::migratePostRowSaveActions($event);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRowActions(MigratePrepareRowEvent $event) {
    $source = $event->getRow()->getSource();
    $createdDate = $source['Created date'];
    if ($createdDate) {
      $date = DateTime::createFromFormat('Y-m-d', $createdDate);
      if ($date->format('Y-n-j') == $createdDate) {
        $event->getRow()->setSourceProperty('Created date', $date->format('Y-m-d'));
      }
    }
    else {
      $date = new DateTime();
      $event->getRow()->setSourceProperty('Created date', $date->format('Y-m-d'));
    }
    parent::prepareRowActions($event);
  }

  /**
   * Validates headers from csv file array.
   *
   * @param array $data
   *   Array derived from csv file.
   *
   * @return array
   *   Missing errors or empty if no errors.
   */
  public function validateHeaders(array $data): array {
    $headerMissing = FALSE;
    $faqHeaders = ['Title', 'Body', 'Files', 'Created date', 'Path'];
    $missing = [
      '@Title' => '',
      '@Body' => '',
      '@Files' => '',
      '@Created date' => '',
      '@Path' => '',
    ];

    foreach ($data as $row) {
      $columnHeaders = array_keys($row);
      foreach ($faqHeaders as $faqHeader) {
        if (!in_array($faqHeader, $columnHeaders)) {
          $missing['@' . $faqHeader] = $this->t('<li> @column </li>', ['@column' => $faqHeader]);
          $headerMissing = TRUE;
        }
      }
    }
    return $headerMissing ? $missing : [];
  }

  /**
   * Validates Rows for csv import.
   *
   * @param array $data
   *   Array derived from csv file.
   *
   * @return array
   *   Missing errors or empty if no errors.
   */
  public function validateRows(array $data) : array {
    $hasError = FALSE;
    $titleRows = '';
    $bodyRows = '';
    $fileRows = '';
    $dateRows = '';
    $message = [
      '@title' => '',
      '@file' => '',
      '@date' => '',
    ];

    foreach ($data as $delta => $row) {
      $row_number = ++$delta;
      // Validate Title.
      if (!$row['Title']) {
        $titleRows .= $row_number . ',';
      }
      // Validate Body.
      if (!$row['Body']) {
        $bodyRows .= $row_number . ',';
      }
      // Validate File url.
      if ($url = $row['Files']) {
        $headers = get_headers($url, 1);
        if (strpos($headers[0], '200') === FALSE) {
          $fileRows .= $row_number . ',';
        }
      }
      // Validate Date.
      if ($createdDate = $row['Created date']) {
        $date = DateTime::createFromFormat('Y-m-d', $createdDate);
        if (!$date || !($date->format('Y-m-d') == $createdDate || $date->format('Y-n-j') == $createdDate)) {
          $dateRows .= $row_number . ',';
        }
      }
    }
    $titleRows = rtrim($titleRows, ',');
    if ($titleRows) {
      $message['@title'] = $this->t('Title is required for row/rows @titleRows</br>', ['@titleRows' => $titleRows]);
      $hasError = TRUE;
    }
    $bodyRows = rtrim($bodyRows, ',');
    if ($bodyRows) {
      $message['@body'] = $this->t('Body is required for row/rows @bodyRows</br>', ['@bodyRows' => $bodyRows]);
      $hasError = TRUE;
    }
    $fileRows = rtrim($fileRows, ',');
    if ($fileRows) {
      $message['@file'] = $this->t('File url is invalid for row/rows @fileRows</br>', ['@fileRows' => $fileRows]);
      $hasError = TRUE;
    }
    $dateRows = rtrim($dateRows, ',');
    if ($dateRows) {
      $message['@date'] = $this->t('Date/Date Format is invalid for row/rows @dateRows</br>', ['@dateRows' => $dateRows]);
      $hasError = TRUE;
    }
    return $hasError ? $message : [];
  }

}
