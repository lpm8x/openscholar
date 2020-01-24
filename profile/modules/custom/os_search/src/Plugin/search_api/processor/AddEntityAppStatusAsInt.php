<?php

namespace Drupal\os_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;
use Drupal\vsite\Plugin\AppManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the item's Bundle to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "add_entity_app_status_as_int",
 *   label = @Translation("Entity App Status field"),
 *   description = @Translation("Adds the app status to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AddEntityAppStatusAsInt extends ProcessorPluginBase {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Vsite context manager.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManagerInterface
   */
  protected $vsiteContextManager;

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManagerInterface
   */
  protected $appManager;

  /**
   * Check app access.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\vsite\Plugin\VsiteContextManagerInterface $vsite_context_manager
   *   Vsite context manager.
   * @param \Drupal\vsite\Plugin\AppManagerInterface $app_mananger
   *   App manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, VsiteContextManagerInterface $vsite_context_manager, AppManagerInterface $app_mananger) {
    $this->configFactory = $config_factory;
    $this->vsiteContextManager = $vsite_context_manager;
    $this->appManager = $app_mananger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('config.factory'),
      $container->get('vsite.context_manager'),
      $container->get('vsite.app.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Entity App Status'),
        'description' => $this->t('Custom Bundle for entity app status.'),
        'type' => 'integer',
        'is_list' => FALSE,
        'processor_id' => $this->getPluginId(),
      ];
      $properties['custom_entity_app_status'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {

    $object = $item->getOriginalObject()->getValue();
    $custom_bundle = $object->getEntityTypeId();
    $app_name = $object->label();

    /** @var array $group_permissions */
    $group_permissions = $this->appManager->getViewContentGroupPermissionsForApp($app_name);

    if ($custom_bundle == 'node') {
      $custom_bundle = $object->bundle();
    }

    if ($custom_bundle) {
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, 'custom_entity_app_status');

      foreach ($fields as $field) {
        $field->addValue($group_permissions);
      }
    }
  }

}
