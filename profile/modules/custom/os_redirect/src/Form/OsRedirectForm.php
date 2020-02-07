<?php

namespace Drupal\os_redirect\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\redirect\Form\RedirectForm;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class OsRedirectForm.
 *
 * @package Drupal\os_redirect\Form
 */
class OsRedirectForm extends RedirectForm {

  /**
   * Vsite Context Manager.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManagerInterface
   */
  private $vsiteContextManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, ModuleHandlerInterface $moduleHandler, EntityTypeManagerInterface $entity_type_manager, VsiteContextManagerInterface $vsite_context_manager) {
    $this->setEntity($entity_type_manager->getStorage('redirect')->create());
    $this->setModuleHandler($moduleHandler);
    $this->vsiteContextManager = $vsite_context_manager;
    if (!$this->vsiteContextManager->getActivePurl() && !$this->currentUser()->hasPermission('administer redirects')) {
      // User without 'administer redirects' permission on global site
      // can't create redirects.
      throw new AccessDeniedHttpException();
    }
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('vsite.context_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['status_code']['#access'] = FALSE;
    $form['language']['widget']['#access'] = FALSE;
    // Remove ajax call due to Save validation error.
    // https://www.drupal.org/project/redirect/issues/3057250
    unset($form["redirect_source"]["widget"][0]["path"]["#ajax"]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // $this->vsiteContextManager is NULL?
    /** @var \Drupal\vsite\Plugin\VsiteContextManagerInterface $vsiteContext */
    $vsite_context = \Drupal::service('vsite.context_manager');

    $source = $form_state->getValue(['redirect_source', 0]);
    /** @var \Drupal\group\Entity\GroupInterface $group */
    if ($group = $vsite_context->getActiveVsite()) {
      $config = $this->config('os_redirect.settings');
      $maximum_number = $config->get('maximum_number');
      $redirects = $group->getContentEntities('group_entity:redirect');
      if (count($redirects) >= $maximum_number) {
        $form_state->setErrorByName('redirect_source', $this->t('Maximum number of redirects (@count) is reached.', ['@count' => $maximum_number]));
      }
      $form_state->setValue('redirect_source', [['path' => '[vsite:' . $group->id() . ']/' . $source['path']]]);
    }

    parent::validateForm($form, $form_state);
    // Replace "exists" error message.
    $errors = $form_state->getErrors();
    foreach ($errors as $error_name => $error) {
      if ($error_name != 'redirect_source' || $error->getUntranslatedString() != 'The source path %source is already being redirected. Do you want to <a href="@edit-page">edit the existing redirect</a>?') {
        continue;
      }
      $t_arg = [
        '%source' => '/' . rtrim($source['path']),
      ];
      $this->overrideErrorMessage($form_state, $error_name, $this->t('The source path %source is already being redirected.', $t_arg));
    }
  }

  /**
   * Able to override an exists error message in form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $exists_name
   *   New exists.
   * @param string|\Drupal\Component\Render\MarkupInterface $new_error
   *   New error message, could be Translatable markup.
   */
  protected function overrideErrorMessage(FormStateInterface $form_state, string $exists_name, $new_error) {
    $errors = $form_state->getErrors();;
    $errors[$exists_name] = $new_error;
    $form_state->clearErrors();
    foreach ($errors as $name => $error) {
      $form_state->setErrorByName($name, $error);
    }
  }

}
