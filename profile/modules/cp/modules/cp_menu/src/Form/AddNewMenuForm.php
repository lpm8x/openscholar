<?php

namespace Drupal\cp_menu\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\cp_menu\MenuHelperInterface;
use Drupal\vsite\Plugin\VsiteContextManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AddNewMenuForm.
 *
 * @package Drupal\cp_menu\Form
 */
class AddNewMenuForm extends FormBase {


  /**
   * We add menu- prefix and -vsiteid suffix so reserve 8 characters out of 32.
   */
  const MENU_MAX_MENU_NAME_LENGTH = 24;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityManager;

  /**
   * VsiteContextManager service.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManager
   */
  protected $vsiteManager;

  /**
   * Vsite id.
   *
   * @var string
   */
  protected $vsite;

  /**
   * CpMenu helper service.
   *
   * @var \Drupal\cp_menu\MenuHelperInterface
   */
  protected $menuHelper;

  /**
   * AddNewMenuForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory instance.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   EntityTypeManager instance.
   * @param \Drupal\vsite\Plugin\VsiteContextManager $vsite_manager
   *   VsiteContextManager instance.
   * @param \Drupal\cp_menu\MenuHelperInterface $menu_helper
   *   MenuHelperInterface instance.
   */
  public function __construct(ConfigFactory $config_factory, EntityTypeManager $entity_type_manager, VsiteContextManager $vsite_manager, MenuHelperInterface $menu_helper) {
    $this->configFactory = $config_factory;
    $this->entityManager = $entity_type_manager;
    $this->vsiteManager = $vsite_manager;
    $this->vsite = $this->vsiteManager->getActiveVsite();
    $this->menuHelper = $menu_helper;
  }

  /**
   * Inject all services we need.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('vsite.context_manager'),
      $container->get('cp_menu.menu_helper')
    );
  }

  /**
   * Gets the form id.
   *
   * @return string
   *   The form id.
   */
  public function getFormId() : string {
    return 'cp_add_new_menu';
  }

  /**
   * Builds the form.
   *
   * @param array $form
   *   The form to build.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The built form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) : array {

    // Add the core AJAX library.
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#prefix'] = '<div id = "new-menu-modal-form">';
    $form['#suffix'] = '</div>';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];

    $form['menu_name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Menu name'),
      '#maxlength' => self::MENU_MAX_MENU_NAME_LENGTH,
      '#description' => $this->t('A unique name to construct the URL for the menu. It must only contain lowercase letters, numbers and underscores.'),
      '#machine_name' => [
        'exists' => [$this, 'cpMenuExists'],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
        'event' => 'click',
      ],
    ];
    return $form;
  }

  /**
   * Form Submit handler.
   *
   * @param array $form
   *   The form itself.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    $menus = $this->vsite->getContent('group_menu:menu');
    // If first time then create a new menu by replicating shared menu.
    if (!$menus) {
      // Create new menus to maintain it's unifromity with the third one.
      $this->menuHelper->createVsiteMenus($this->vsite);
    }
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax Submit handler.
   *
   * @param array $form
   *   The form itself.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) : AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->getErrors()) {
      $response->addCommand(new ReplaceCommand('#new-menu-modal-form', $form));
      $this->messenger()->deleteAll();
    }
    else {
      $groupMenu = $this->entityManager->getStorage('menu')->create([
        'id' => "menu-" . $form_state->getValue('menu_name') . "-" . $this->vsite->id(),
        'label' => $this->t('@title', ['@title' => $form_state->getValue('title')]),
      ]);
      $groupMenu->save();
      $this->vsite->addContent($groupMenu, 'group_menu:menu');
      $currentURL = Url::fromRoute('cp.build.menu');
      $response->addCommand(new RedirectCommand($currentURL->toString()));
    }
    return $response;
  }

  /**
   * Check to see if that menu already exists.
   *
   * @param string $menu_name
   *   Menu name.
   *
   * @return bool
   *   If menu id already exists.
   */
  public function cpMenuExists($menu_name) {
    $menu_name = "menu-" . $menu_name . "-" . $this->vsite->id();
    $menus = $this->vsite->getContent('group_menu:menu');
    foreach ($menus as $menu) {
      if ($menu_name == $menu->entity_id_str->target_id) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
