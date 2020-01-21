<?php

namespace Drupal\cp_taxonomy\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\cp_taxonomy\Plugin\Action\RemoveTermsMediaAction;
use Drupal\cp_taxonomy\Plugin\Action\RemoveTermsPublicationAction;

/**
 * Remove terms from bibcite reference entities form.
 */
class RemoveTermsFromPublicationForm extends ManageTermsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'remove_terms_to_bibcite_reference_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL) {
    $this->tempStore = $this->tempStoreFactory->get(RemoveTermsPublicationAction::TEMPSTORE_KEY);
    $form['#title'] = $this->t('Remove Terms from Content');
    $form = parent::buildForm($form, $form_state, $entity_type_id);
    $form['entities']['#title'] = $this->t('The selected terms above will be removed from the following content:');
    $form['actions']['submit']['#value'] = $this->t('Remove');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    if ($button['#type'] == 'submit' && !empty($this->entityInfo)) {
      $this->removeTermsSubmit($form_state);
      $this->tempStore->delete($this->currentUser->id());
    }
  }

}
