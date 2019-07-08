<?php

namespace Drupal\os_wysiwyg\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "os_link" plugin.
 *
 * @CKEditorPlugin(
 *   id = "os_link",
 *   label = @Translation("Link")
 * )
 */
class OsLink extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'os_wysiwyg') . '/js/plugins/os_link/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'core/drupal.ajax',
      'core/views.ajax',
      'os_wysiwyg/os_link',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [
      'osLink_dialogLinkAdd' => $this->t('Add link'),
      'osLink_dialogLinkEdit' => $this->t('Edit link'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $path = drupal_get_path('module', 'os_wysiwyg') . '/js/plugins/os_link';
    return [
      'OsLink' => [
        'label' => $this->t('Link'),
        'image' => $path . '/icons/os_link.png',
      ],
    ];
  }

}
