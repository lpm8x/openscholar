<?php

namespace Drupal\os_mailchimp\Plugin\CpSetting;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\cp_settings\CpSettingBase;

/**
 * CP mailchimp setting.
 *
 * @CpSetting(
 *   id = "os_mailchimp_setting",
 *   title = @Translation("OS Mailchimp"),
 *   group = {
 *    "id" = "mailchimp",
 *    "title" = @Translation("Mailchimp"),
 *    "parent" = "cp.settings.global"
 *   }
 * )
 */
class OsMailChimpSetting extends CpSettingBase {

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return ['mailchimp.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$form, FormStateInterface $formState, ConfigFactoryInterface $configFactory) {
    $config = $configFactory->get('mailchimp.settings');
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => t('MailChimp API key'),
      '#default_value' => $config->get('api_key'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(FormStateInterface $formState, ConfigFactoryInterface $configFactory) {
    $config = $configFactory->getEditable('mailchimp.settings');
    $config->set('api_key', $formState->getValue('api_key'));
    $config->save(TRUE);

    $cache = \Drupal::cache('mailchimp');
    $cache->invalidate('lists');
    Cache::invalidateTags([
      'mailchimp',
    ]);
  }

}
