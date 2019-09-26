<?php

namespace Drupal\os_profiles\Plugin\CpSetting;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\cp_settings\CpSettingBase;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\image_widget_crop\ImageWidgetCropInterface;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * OS Profiles Setting.
 *
 * @CpSetting(
 *   id = "profiles_setting",
 *   title = @Translation("Profiles"),
 *   group = {
 *    "id" = "profiles",
 *    "title" = @Translation("Profiles"),
 *    "parent" = "cp.settings.app"
 *   }
 * )
 */
class ProfilesSetting extends CpSettingBase {

  /**
   * Entity Display Repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * File usage interface to configurate an file object.
   *
   * @var Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * Instance of API ImageWidgetCropManager.
   *
   * @var \Drupal\image_widget_crop\ImageWidgetCropInterface
   */
  protected $imageWidgetCropManager;

  /**
   * ProfilesSetting constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\vsite\Plugin\VsiteContextManagerInterface $vsite_context_manager
   *   Vsite context manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   Entity Display Repository Interface.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   File usage service.
   * @param \Drupal\image_widget_crop\ImageWidgetCropInterface $iwc_manager
   *   The ImageWidgetCrop manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VsiteContextManagerInterface $vsite_context_manager, EntityDisplayRepositoryInterface $entity_display_repository, RendererInterface $renderer, FileUsageInterface $file_usage, ImageWidgetCropInterface $iwc_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $vsite_context_manager);
    $this->entityDisplayRepository = $entity_display_repository;
    $this->renderer = $renderer;
    $this->fileUsage = $file_usage;
    $this->imageWidgetCropManager = $iwc_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('vsite.context_manager'),
      $container->get('entity_display.repository'),
      $container->get('renderer'),
      $container->get('file.usage'),
      $container->get('image_widget_crop.manager')
    );
  }

  /**
   * ImageWidget process call without parent call.
   *
   * @param array $element
   *   Image element.
   *
   * @return mixed
   *   Modified element.
   */
  protected static function processImageFile(array &$element) {
    $element['#theme'] = 'image_widget';

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);
      $variables = [
        'style_name' => $element['#preview_image_style'],
        'uri' => $file->getFileUri(),
      ];

      // Determine image dimensions.
      if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
        $variables['width'] = $element['#value']['width'];
        $variables['height'] = $element['#value']['height'];
      }
      else {
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $variables['width'] = $image->getWidth();
          $variables['height'] = $image->getHeight();
        }
        else {
          $variables['width'] = $variables['height'] = NULL;
        }
      }

      $element['preview'] = [
        '#weight' => -10,
        '#theme' => 'image_style',
        '#width' => $variables['width'],
        '#height' => $variables['height'],
        '#style_name' => $variables['style_name'],
        '#uri' => $variables['uri'],
      ];

      // Store the dimensions in the form so the file doesn't have to be
      // accessed again. This is important for remote files.
      $element['width'] = [
        '#type' => 'hidden',
        '#value' => $variables['width'],
      ];
      $element['height'] = [
        '#type' => 'hidden',
        '#value' => $variables['height'],
      ];
    }
    elseif (!empty($element['#default_image'])) {
      $default_image = $element['#default_image'];
      $file = File::load($default_image['fid']);
      if (!empty($file)) {
        $element['preview'] = [
          '#weight' => -10,
          '#theme' => 'image_style',
          '#width' => $default_image['width'],
          '#height' => $default_image['height'],
          '#style_name' => $element['#preview_image_style'],
          '#uri' => $file->getFileUri(),
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return ['os_profiles.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$form, ConfigFactoryInterface $configFactory) {
    $form['#attached']['library'][] = 'os_profiles/settings_hover';
    $config = $configFactory->get('os_profiles.settings');
    $default_fid = $config->get('default_image_fid');

    $view_modes = $this->entityDisplayRepository->getViewModeOptionsByBundle('node', 'person');

    $profile_styles = [];
    foreach ($view_modes as $name => $label) {
      if ($name == 'default') {
        // Display only display modes the user turned on.
        continue;
      }

      $profile_styles[$name] = $label;
    }

    $profile_styles_hover = [];
    $counter = 0;

    $hover_image = $this->getExampleImage($default_fid);
    // Create markup for style examples when hovering over each style.
    foreach ($profile_styles as $name => $label) {
      $counter++;
      $build_hover = [
        '#theme' => 'os_profiles_example_hover_popup',
        '#counter' => $counter,
        '#label' => Html::escape($label),
        '#profile_example' => [
          '#theme' => 'os_profiles_example_' . $name,
          '#image' => $hover_image,
        ],
      ];
      $profile_styles_hover[$name] = $this->renderer->renderRoot($build_hover);
    }
    $display_types_order = [
      'teaser',
      'sidebar_teaser',
      'title',
      'slide_teaser',
      'no_image_teaser',
    ];

    $form['display_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display types'),
      '#options' => $this->sortDisplayTypes($profile_styles_hover, $display_types_order),
      '#default_value' => $config->get('display_type'),
    ];

    $form['display_type_description'] = [
      '#type' => 'item',
      '#description' => $this->t('Choose the display type of a person in the "/people" page.'),
    ];

    // Form element for disabling the use of a default image.
    $form['default_image'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default Image'),
      '#attributes' => [
        'class' => 'node-form',
      ],
    ];

    $form['default_image']['disable_default_image'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable default image for people profiles'),
      '#default_value' => $config->get('disable_default_image'),
      '#description' => $this->t('If checked no image will be used when viewing the "/people" page.'),
      '#weight' => -1,
    ];

    $suffix = '';
    if (empty($default_fid)) {
      $suffix .= $this->getDefaultImage() . '<br />';
    }
    else {
      $suffix .= $this->getExampleImage($default_fid, 'crop_photo_person_full') . '<br />';
    }
    $suffix .= $this->t('The default image will be used if a profile photo is not available. Instead, you can upload your own default image.<br/>Position the cropping tool over it if necessary. Allowed media types: <strong>image</strong>');
    $upload_location = 'public://' . $this->activeVsite->id() . '/files';
    $allowed_file_types = 'gif png jpg jpeg';
    $field_layout = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.person.default');
    $content = $field_layout->get('content');
    $settings = $content['field_photo_person']['settings'];
    $form['default_image']['default_image_fid'] = [
      '#type' => 'managed_file',
      '#description' => $suffix,
      '#upload_location' => $upload_location,
      '#upload_validators' => [
        'file_validate_extensions' => [$allowed_file_types],
      ],
      '#multiple' => FALSE,
      '#progress_indicator' => 'throbber',
      '#process' => [
        [get_class($this), 'processFile'],
      ],
      '#theme' => 'image_widget',
      '#crop_type_list' => $settings['crop_list'],
      '#preview_image_style' => $settings['preview_image_style'],
      '#crop_preview_image_style' => $settings['crop_preview_image_style'],
      '#show_default_crop' => $settings['show_default_crop'],
      '#show_crop_area' => $settings['show_crop_area'],
      '#warn_mupltiple_usages' => $settings['warn_multiple_usages'],
    ];
    if ($default_fid = $config->get('default_image_fid')) {
      $form['default_image']['default_image_fid']['#default_value'] = [$default_fid];
    }
  }

  /**
   * Custom process for image.
   */
  public static function processFile(&$element, FormStateInterface $form_state, &$complete_form) {
    ManagedFile::processManagedFile($element, $form_state, $complete_form);

    self::processImageFile($element);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(FormStateInterface $form_state, ConfigFactoryInterface $configFactory) {
    $config = $configFactory->getEditable('os_profiles.settings');
    $display_type_changed = $config->get('display_type') != $form_state->getValue('display_type');
    $config->set('display_type', $form_state->getValue('display_type'));
    $config->set('disable_default_image', (bool) $form_state->getValue('disable_default_image'));
    $config->set('image_crop', $form_state->getValue('image_crop'));

    $deletable_fid = 0;
    $form_file = $form_state->getValue('default_image_fid', 0);
    if (!empty($form_file[0])) {
      $file = File::load($form_file[0]);
      $file_changed = $config->get('default_image_fid') != $form_file[0];
      if ($file_changed) {
        $this->fileUsage->add($file, 'os_profiles', 'form', $file->id());
        // Checking is there any exists file and delete.
        // Use case: remove exists file and upload immediately a new one.
        if ($exists_fid = $config->get('default_image_fid')) {
          $deletable_fid = $exists_fid;
        }
      }
      $file->setPermanent();
      $file->save();
      $form_state->getFormObject()->setEntity($file);
      $config->set('default_image_fid', $file->id());
    }
    else {
      // Checking is there any exists file and delete.
      if ($exists_fid = $config->get('default_image_fid')) {
        $deletable_fid = $exists_fid;
      }
      $config->set('default_image_fid', NULL);
    }
    if ($deletable_fid) {
      File::load($deletable_fid)->delete();
    }

    $config->save(TRUE);
    if (!empty($form_state->getValue('image_crop')) && !empty($file)) {
      // Call IWC manager to attach crop defined into image file.
      $this->imageWidgetCropManager->buildCropToForm($form_state);
    }
    if ($group = $this->vsiteContextManager->getActiveVsite()) {
      Cache::invalidateTags(['node-person-without-image:' . $group->id()]);
      if ($display_type_changed) {
        Cache::invalidateTags(['view:people:page:' . $group->id()]);
      }
    }
  }

  /**
   * Get image markup for example hover.
   */
  public function getExampleImage($default_image_fid = NULL, $image_style = 'crop_photo_person') {
    // Use custom default image if available.
    if (!empty($default_image_fid)) {
      $image_file = File::load($default_image_fid);
      $path = $image_file->getFileUri();
      // $alt = $media->get('field_media_image')->getValue()[0]['alt'];
      $build = [
        '#theme' => 'image_style',
        '#uri' => $path,
        '#style_name' => $image_style,
        // '#alt' => $alt,.
      ];
      return $this->renderer->renderRoot($build);
    }
    else {
      // Use default image.
      $build = [
        '#theme' => 'image',
        '#uri' => file_create_url(drupal_get_path('theme', 'os_base') . '/images/person-default-image.png'),
        '#alt' => t('default-image'),
      ];
      return $this->renderer->renderRoot($build);
    }
  }

  /**
   * Get default image.
   */
  public function getDefaultImage() {
    $build = [
      '#theme' => 'image',
      '#uri' => file_create_url(drupal_get_path('theme', 'os_base') . '/images/person-default-image-big.png'),
      '#alt' => t('default-image'),
    ];
    return $this->renderer->renderRoot($build);
  }

  /**
   * Short display types as order array.
   *
   * @param array $display_types
   *   Original array.
   * @param array $order
   *   Desired sorting with listed keys.
   *
   * @return array
   *   Ordered array.
   */
  protected function sortDisplayTypes(array $display_types, array $order) {
    $ordered_display_types = [];
    foreach ($order as $key) {
      if (isset($display_types[$key])) {
        $ordered_display_types[$key] = $display_types[$key];
        unset($display_types[$key]);
      }
    }
    // Merge the rest of array.
    $ordered_display_types = array_merge($ordered_display_types, $display_types);
    return $ordered_display_types;
  }

}
