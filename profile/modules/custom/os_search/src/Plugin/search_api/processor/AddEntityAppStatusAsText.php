<?php

namespace Drupal\os_search\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\vsite\Plugin\AppManager;
use Drupal\os_app_access\Access\AppAccess;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Adds the item's Bundle to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "add_entity_app_status_as_text",
 *   label = @Translation("Entity App Status field"),
 *   description = @Translation("Adds the app status to the indexed data."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class AddEntityAppStatusAsText extends ProcessorPluginBase {

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManager
   */
  protected $appManager;

  /**
   * App access.
   *
   * @var \Drupal\os_app_access\Access\AppAccess
   */
  protected $appAccess;

  /**
   * The current_user service used by this plugin.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->getAppManager($container->get('vsite.app.manager'));
    $processor->getAppAccess($container->get('os_app_access.app_access'));
    $processor->setCurrentUser($container->get('current_user'));

    return $processor;
  }

  /**
   * Get the App Manager for app details.
   *
   * @param \Drupal\vsite\Plugin\AppManager $app_manager
   *   App manager.
   *
   * @return $this
   */
  public function getAppManager(AppManager $app_manager) {
    $this->appManager = $app_manager;
    return $this;
  }

  /**
   * Get App Access.
   *
   * @param \Drupal\os_app_access\Access\AppAccess $database
   *   The new database connection.
   *
   * @return $this
   */
  public function getAppAccess(AppAccess $app_access) {
    $this->appAccess = $app_access;
    return $this;
  }

  /**
   * Retrieves the current user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user.
   */
  public function getCurrentUser() {
    return $this->currentUser ?: \Drupal::currentUser();
  }

  /**
   * Sets the current user.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   *
   * @return $this
   */
  public function setCurrentUser(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
    return $this;
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
    $groups = GroupContent::loadByEntity($object);
    $app_name = $object->bundle();
    $status = -1;
    $app_access_check = '';
    if ($groups) {
      $app_access_check = $this->appAccess->access($this->currentUser, $app_name);
      if ($app_access_check->isAllowed() || $app_access_check->isNeutral()) {
        $status = 1;
      }
      else {
        $status = 0;
      }
      $fields = $this->getFieldsHelper()->filterForPropertyPath($item->getFields(), NULL, 'custom_entity_app_status');
    }
    foreach ($fields as $field) {
      $field->addValue($status);
    }
  }

}
