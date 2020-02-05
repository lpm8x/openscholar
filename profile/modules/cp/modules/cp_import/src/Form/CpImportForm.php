<?php

namespace Drupal\cp_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\vsite\Plugin\AppManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CpImportForm.
 */
class CpImportForm extends FormBase {

  /**
   * App Plugin Manager.
   *
   * @var \Drupal\vsite\Plugin\AppManager
   */
  protected $vsiteAppManager;


  /**
   * App plugin base.
   *
   * @var \Drupal\vsite\Plugin\AppPluginBase
   */
  protected $appPlugin;

  /**
   * {@inheritdoc}
   */
  public function __construct(AppManager $vsite_app_manager) {
    $this->vsiteAppManager = $vsite_app_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('vsite.app.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cp_content_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $app_name = NULL) {
    $this->appPlugin = $this->vsiteAppManager->createInstance($app_name);
    return $this->appPlugin->getImportForm($form, $app_name);
  }

  /**
   * Validate import source.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $this->appPlugin->validateImportSource($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->appPlugin->submitImportForm($form_state);
  }

  /**
   * Returns the App title.
   *
   * @param string $app_name
   *   App id.
   *
   * @return string
   *   Form title.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTitle($app_name = NULL) : string {
    $plugin = $this->vsiteAppManager->getDefinition($app_name);
    return isset($plugin['title']) ? $plugin['title'] : $this->t('Import');
  }

}
