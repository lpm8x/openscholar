<?php

namespace Drupal\os_publications\Plugin\App;

use Drupal\bibcite\Plugin\BibciteFormatManager;
use Drupal\Core\Messenger\Messenger;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\vsite\Plugin\AppPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Bibcite Format manager.
   *
   * @var \Drupal\bibcite\Plugin\BibciteFormatManager
   */
  protected $formatManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationPluginManager $migrationPluginManager, CpImportHelper $cpImportHelper, Messenger $messenger, BibciteFormatManager $bibciteFormatManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migrationPluginManager, $cpImportHelper, $messenger);
    $this->formatManager = $bibciteFormatManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migration'),
      $container->get('cp_import.helper'),
      $container->get('messenger'),
      $container->get('plugin.manager.bibcite_format')
    );
  }

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

    $form['encoding'] = [
      '#type' => 'select',
      '#title' => $this->t('File Type'),
      '#options' => array_map(function ($definition) {
        return $definition['label'];
      }, $this->formatManager->getImportDefinitions()),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

}
