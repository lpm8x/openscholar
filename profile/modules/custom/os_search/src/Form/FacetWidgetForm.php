<?php

namespace Drupal\os_search\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Index;

/**
 * Configure example settings for this site.
 */
class FacetWidgetForm extends ConfigFormBase {
  // @var string Config settings
  const SETTINGS = 'os.search.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_api_index_facets_widgets';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */

  public function buildForm(array $form, FormStateInterface $form_state, $search_api_index = NULL) {
    $config = $this->config(static::SETTINGS);

    $index = Index::load($search_api_index);

    $form['description']['#markup'] = $this->t('<p>Please check below facet widgets if you want to use for Filter.</p>');

    $form['_general']['#title'] = $this->t('General');
    $options = [];
    $config_values = [];
    $fields = $index->getFieldsByDatasource(NULL);
    foreach ($fields as $key => $field) {
      $options[$key] = $field->getLabel();
      $config_values[$key] = $config->get('facet_widget')[$key];
    }

    $form['facet_widget'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Facet Widgets'),
      '#options' => $options,
      '#default_value' => $config_values,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $os_search = $this->configFactory->getEditable(static::SETTINGS);
    $os_search->set('facet_widget', $form_state->getValue('facet_widget'));
    $os_search->save();

    parent::submitForm($form, $form_state);
  }

}
