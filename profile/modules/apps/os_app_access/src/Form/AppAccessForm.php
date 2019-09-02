<?php

namespace Drupal\os_app_access\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\os_app_access\AppAccessLevels;
use Drupal\vsite\Plugin\AppManangerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AppAccessForm.
 */
class AppAccessForm extends ConfigFormBase {

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManangerInterface
   */
  protected $appManager;

  /**
   * Creates new AppAccessForm object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, AppManangerInterface $app_manager) {
    parent::__construct($config_factory);
    $this->appManager = $app_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('vsite.app.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['os_app_access.access'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'app_access';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $app_access = $this->config('os_app_access.access');
    /** @var \Drupal\vsite\AppInterface[] $apps */
    $apps = $this->appManager->getDefinitions();

    $enabled = [];
    $disabled = [];
    foreach ($apps as $name => $app) {
      $level = $app_access->get($name);
      if (is_int($level)) {
        if ($app_access->get($name) != AppAccessLevels::DISABLED) {
          $enabled[] = $name;
        }
        else {
          $disabled[] = $name;
        }
      }
      else {
        $enabled[] = $name;
      }
    }

    $header_en = [
      'name' => $this->t('Name'),
      'privacy' => $this->t('Visibility'),
      'disable' => $this->t('Disable'),
    ];

    $header_dis = [
      'name' => $this->t('Name'),
      'enable' => $this->t('Enable'),
    ];

    $form['enabled'] = [
      '#type' => 'table',
      '#header' => $header_en,
      '#empty' => $this->t('No Apps Enabled'),
    ];

    $form['disabled'] = [
      '#type' => 'table',
      '#header' => $header_dis,
      '#empty' => $this->t('No Apps Disabled'),
    ];

    foreach ($enabled as $k) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
      $title = $apps[$k]['title'];
      $form['enabled'][$k] = [
        'name' => [
          '#markup' => $title->render(),
        ],
        'privacy' => [
          '#type' => 'select',
          '#options' => [
            AppAccessLevels::PUBLIC => $this->t('Everyone'),
            AppAccessLevels::PRIVATE => $this->t('Site Members Only'),
          ],
          '#default_value' => $app_access->get($k),
        ],
        'disable' => [
          '#type' => 'checkbox',
          '#default_value' => FALSE,
        ],
      ];
    }

    foreach ($disabled as $k) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $title */
      $title = $apps[$k]['title'];
      $form['disabled'][$k] = [
        'name' => [
          '#markup' => $title->render(),
        ],
        'enable' => [
          '#type' => 'checkbox',
          '#default_value' => FALSE,
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $app_access = $this->config('os_app_access.access');
    $values = $form_state->getValues();

    if (is_array($values['enabled'])) {
      foreach ($values['enabled'] as $app => $v) {
        if ($v['disable']) {
          $app_access->set($app, AppAccessLevels::DISABLED);
        }
        else {
          $app_access->set($app, (int) $v['privacy']);
        }
      }
    }

    if (is_array($values['disabled'])) {
      foreach ($values['disabled'] as $app => $v) {
        if ($v['enable']) {
          $app_access->set($app, AppAccessLevels::PUBLIC);
        }
      }
    }

    $app_access->save(TRUE);
    Cache::invalidateTags(['app:access_changed', 'config:system.menu.main']);

    parent::submitForm($form, $form_state);
  }

}
