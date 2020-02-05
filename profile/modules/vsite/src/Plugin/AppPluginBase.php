<?php

namespace Drupal\vsite\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\cp_import\Helper\CpImportHelper;
use Drupal\file\Entity\File;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\vsite\AppInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for app plugins.
 */
abstract class AppPluginBase extends PluginBase implements AppInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Migration Manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * Cp import helper service.
   *
   * @var \Drupal\cp_import\Helper\CpImportHelper
   */
  protected $cpImportHelper;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Entity Type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * AppPluginBase constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin Definition.
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migrationPluginManager
   *   MigrationPlugin manager.
   * @param \Drupal\cp_import\Helper\CpImportHelper $cpImportHelper
   *   Cp Import helper instance.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   Messenger instance.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   EntityTypeManager instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationPluginManager $migrationPluginManager, CpImportHelper $cpImportHelper, Messenger $messenger, EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migrationManager = $migrationPluginManager;
    $this->cpImportHelper = $cpImportHelper;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupContentTypes() {
    $definition = $this->getPluginDefinition();
    if (isset($definition['bundle'])) {
      return $definition['bundle'];
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    $definition = $this->getPluginDefinition();
    if (isset($definition['title'])) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
      $title = $definition['title'];
      return $title->render();
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCreateLinks() {
    $definition = $this->getPluginDefinition();
    $links = [];

    foreach ($definition['bundle'] as $b) {
      $links[] = [
        'menu_name' => 'control-panel',
        'route_name' => 'node.add',
        'route_parameters' => ['node_type' => $b],
        'parent' => 'cp.content.add',
        'title' => $this->getTitle(),
      ];
    }
    $links[] = [
      'menu_name' => 'control-panel',
      'route_name' => 'cp.content.import',
      'route_parameters' => ['app_name' => $definition['id']],
      'parent' => 'cp.content.import.collection',
      'title' => $this->getTitle(),
    ];
    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportForm(array $form, $type) : array {

    $form['format'] = [
      '#type' => 'item',
      '#title' => $this->t('Format'),
      '#markup' => $this->t('CSV'),
    ];

    $form['template'] = [
      '#type' => 'link',
      '#title' => $this->t('Download a template'),
      '#url' => Url::fromRoute('cp_import.content.download_template', ['app_name' => $type]),
    ];

    $form['import_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Import File'),
      '#description' => $this->t('Note: Import files with more than @rowLimit rows are not permitted. Try creating multiple import files in 100 row increments.', ['@rowLimit' => CpImportHelper::CSV_ROW_LIMIT]),
      '#upload_location' => 'public://importcsv/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $params = [
      '@wikipedia-url' => 'http://en.wikipedia.org/wiki/Character_encoding',
    ];

    $form['app_id'] = [
      '#type' => 'hidden',
      '#value' => $type,
    ];

    $form['encoding'] = [
      '#title' => $this->t('Encoding'),
      '#type' => 'select',
      '#description' => $this->t('Select the encoding of your file. For a full list of encodings you can visit <a href="@wikipedia-url">this</a> Wikipedia page.', $params),
      '#default_value' => 'UTF-8',
      '#options' => [
        'utf-8' => $this->t('UTF-8'),
        'utf-16' => $this->t('UTF-16'),
        'utf-32' => $this->t('UTF-32'),
        'MS-Windows character sets' => [
          'Windows-1250' => $this->t('Central European languages that use Latin script'),
          'Windows-1251' => $this->t('Cyrillic alphabets'),
          'Windows-1252' => $this->t('Western languages'),
          'Windows-1253' => $this->t('Greek'),
          'Windows-1254' => $this->t('Turkish'),
          'WINDOWS-1255' => $this->t('Hebrew'),
          'Windows-1256' => $this->t('Arabic'),
          'Windows-1257' => $this->t('Baltic languages'),
          'Windows-1258' => $this->t('Vietnamese'),
        ],
        'ISO 8859' => [
          'ISO-8859-1' => $this->t('Western Europe'),
          'ISO-8859-2' => $this->t('Western and Central Europe'),
          'ISO-8859-9' => $this->t('Western Europe with amended Turkish character set'),
          'ISO-8859-14' => $this->t('Celtic languages (Irish Gaelic, Scottish, Welsh)'),
        ],
      ],
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
   * Validates import file and shows errors row wise.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The Form State.
   *
   * @return array|bool
   *   Data array or false if any errors are encountered.
   */
  public function validateImportSource(array $form, FormStateInterface $formState) {
    $triggerElement = $formState->getTriggeringElement();
    if ($triggerElement['#name'] === 'import_file_remove_button' || $triggerElement['#name'] === 'import_file_upload_button') {
      return FALSE;
    }

    $fileId = $formState->getValue('import_file');
    $fileId = array_shift($fileId);

    $encoding = $formState->getValue('encoding');

    /** @var \Drupal\file\Entity\File|NULL $file */
    $file = $fileId ? File::load($fileId) : NULL;

    if (!$file) {
      $formState->setError($form['import_file'], $this->t('File not found'));
      return FALSE;
    }

    $data = $this->cpImportHelper->csvToArray($file->getFileUri(), $encoding);

    if (!$data) {
      $formState->setError($form['import_file'], $this->t('Data could not be read from the csv , The structure of your CSV file probably needs to be updated. Please download the template again.'));
      return FALSE;
    }

    if ($data === CpImportHelper::OVER_LIMIT) {
      $formState->setError($form['import_file']);
      $this->messenger->addError($this->t('Exceeded @rowLimit Row limit. Please split the file so that each file has a maximum of @rowLimit rows only and run import for each file one by one.', ['@rowLimit' => CpImportHelper::CSV_ROW_LIMIT]));
      return FALSE;
    }
    // If no errors return data array to child apps custom validation.
    return $data;
  }

  /**
   * Submit import form and execute migration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\migrate\MigrateException
   */
  public function submitImportForm(FormStateInterface $form_state) {
    $fileId = $form_state->getValue('import_file');
    $fileId = array_shift($fileId);
    /** @var \Drupal\file\Entity\File $file */
    $file = $this->entityTypeManager->getStorage('file')->load($fileId);

    $definition = $this->getPluginDefinition();
    // Replace existing source file.
    file_move($file, $definition['cpImportFilePath'], FileSystem::EXISTS_REPLACE);
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $this->migrationManager->createInstance($definition['cpImportId']);
    $executable = new MigrateBatchExecutable($migration, new MigrateMessage());
    $executable->batchImport();
  }

}
