<?php

namespace Drupal\os_publications\Plugin\App;

use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\vsite\Plugin\AppPluginBase;

/**
 * Publications app.
 *
 * @App(
 *   title = @Translation("Publication"),
 *   canDisable = true,
 *   entityType = "bibcite_reference",
 *   viewsTabs = {
 *     "publications" = {
 *       "page_1",
 *       "page_2",
 *       "page_3",
 *       "page_4",
 *     },
 *   },
 *   id = "publications",
 *   contextualRoute = "view.publications.page_1"
 * )
 */
class PublicationsApp extends AppPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getGroupContentTypes() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreateLinks() {

    $links[] = [
      'menu_name' => 'control-panel',
      'route_name' => 'cp.content.import',
      'route_parameters' => ['app_name' => $this->getPluginId()],
      'parent' => 'cp.content.import.collection',
      'title' => $this->getTitle(),
    ];

    $links[] = [
      'menu_name' => 'control-panel',
      'route_name' => 'os_publications.redirect_bibcite_reference_bundles_form',
      'parent' => 'cp.content.add',
      'title' => $this->getTitle(),
    ];

    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportForm(array $form, $type) : array {

    $form['import_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Import File'),
      '#description' => $this->t('Note: Import files with more than @rowLimit rows are not permitted. Try creating multiple import files in 100 row increments.', ['@rowLimit' => CpImportHelper::CSV_ROW_LIMIT]),
      '#upload_location' => 'public://importcsv/',
      '#upload_validators' => [
        'file_validate_extensions' => ['bib'],
      ],
    ];

    $form['app_id'] = [
      '#type' => 'hidden',
      '#value' => $type,
    ];

    $form['encoding'] = [];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

}
