<?php

namespace Drupal\os_publications\Plugin\App;

use Drupal\bibcite\Plugin\BibciteFormatManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\file\Entity\File;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\vsite\Plugin\AppPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

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
   * Serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationPluginManager $migrationPluginManager, CpImportHelper $cpImportHelper, Messenger $messenger, EntityTypeManager $entityTypeManager, BibciteFormatManager $bibciteFormatManager, SerializerInterface $serializer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migrationPluginManager, $cpImportHelper, $messenger, $entityTypeManager);
    $this->formatManager = $bibciteFormatManager;
    $this->serializer = $serializer;
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
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.bibcite_format'),
      $container->get('serializer')
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
      '#description' => $this->t('Note: Import files with more than @rowLimit entries are not permitted. Try creating multiple import files in 100 entry increments.', ['@rowLimit' => CpImportHelper::CSV_ROW_LIMIT]),
      '#upload_location' => 'public://importcsv/',
      '#upload_validators' => [
        'file_validate_extensions' => ['bib'],
      ],
    ];

    $form['app_id'] = [
      '#type' => 'hidden',
      '#value' => $type,
    ];

    $form['format'] = [
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

  /**
   * {@inheritdoc}
   */
  public function validateImportSource(array $form, FormStateInterface $formState) {
    $triggerElement = $formState->getTriggeringElement();
    if ($triggerElement['#name'] === 'import_file_remove_button' || $triggerElement['#name'] === 'import_file_upload_button') {
      return FALSE;
    }

    $fileId = $formState->getValue('import_file');
    $fileId = array_shift($fileId);

    $format = $formState->getValue('format');

    /** @var \Drupal\file\Entity\File|NULL $file */
    $file = $fileId ? File::load($fileId) : NULL;

    if (!$file) {
      $formState->setError($form['import_file'], $this->t('File could not be uploaded.'));
      return FALSE;
    }

    // Read contents of the file as a string.
    $data = file_get_contents($file->getFileUri());
    // Get the data array.
    $decoded = $this->serializer->decode($data, $format);

    if (count($decoded) > 100) {
      $formState->setError($form['import_file'], $this->t('More than 100 entries are not allowed.'));
      return FALSE;
    }

    // Set decoded values to be used in submit later.
    $formState->setValue('decoded', $decoded);
  }

  /**
   * {@inheritdoc}
   */
  public function submitImportForm(FormStateInterface $formState) {
    $format_id = $formState->getValue('format');
    /** @var \Drupal\bibcite\Plugin\BibciteFormatInterface $format */
    $format = $this->formatManager->createInstance($format_id);

    $decoded = $formState->getValue('decoded');

    // Set the batch array for importing publications.
    $batch = [
      'title' => t('Import Publication data'),
      'operations' => [],
      'finished' => 'cp_import_publication_batch_finished',
      'file' => drupal_get_path('module', 'cp_import') . '/cp_import_publication.batch.inc',
    ];

    foreach ($decoded as $entry) {
      $batch['operations'][] = [
        'cp_import_publication_batch_callback', [$entry, $format],
      ];
    }

    batch_set($batch);
  }

}
