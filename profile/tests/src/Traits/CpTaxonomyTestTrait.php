<?php

namespace Drupal\Tests\openscholar\Traits;

use Drupal\cp_taxonomy\CpTaxonomyHelper;
use Drupal\group\Entity\GroupInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides a trait for taxonomy and vocab tests.
 */
trait CpTaxonomyTestTrait {

  /**
   * Create a vocabulary to a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group entity.
   * @param string $vid
   *   Vocabulary id.
   * @param array $allowed_types
   *   Allowed types for entity bundles.
   * @param string $widget_type
   *   Widget type of field node form.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createGroupVocabulary(GroupInterface $group, string $vid, array $allowed_types = [], string $widget_type = CpTaxonomyHelper::WIDGET_TYPE_AUTOCOMPLETE) {
    $this->vsiteContextManager->activateVsite($group);
    $vocab = Vocabulary::create([
      'name' => $vid,
      'vid' => $vid,
    ]);
    $vocab->enforceIsNew();
    $vocab->save();
    if (!empty($allowed_types)) {
      $config_vocab = $this->configFactory->getEditable('taxonomy.vocabulary.' . $vid);
      $config_vocab
        ->set('allowed_vocabulary_reference_types', $allowed_types)
        ->set('widget_type', $widget_type)
        ->set('is_required', FALSE)
        ->set('widget_type_autocomplete', CpTaxonomyHelper::TYPE_AUTOCOMPLETE)
        ->save(TRUE);
    }

    $this->markEntityForCleanup($vocab);
  }

  /**
   * Create a vocabulary to a group on cp taxonomy pages.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group entity.
   * @param string $vid
   *   Vocabulary id.
   * @param array $settings
   *   Taxonomy term settings.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   Created taxonomy term.
   */
  protected function createGroupTerm(GroupInterface $group, string $vid, array $settings = []) {
    $this->vsiteContextManager->activateVsite($group);
    $vocab = Vocabulary::load($vid);
    $term = $this->createTerm($vocab, $settings);
    $group->addContent($term, 'group_entity:taxonomy_term');
    return $term;
  }

  /**
   * Helper function, that will apply the action.
   */
  protected function applyAction($action_id) {
    $web_assert = $this->assertSession();
    $page = $this->getCurrentPage();
    $select = $page->findField('action');
    $select->setValue($action_id);
    $page->pressButton('Apply to selected items');
    $web_assert->statusCodeEquals(200);
  }

  /**
   * Helper function, that will select a vocab and first term in chosen.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param string $term_label
   *   Term label.
   */
  protected function applyVocabularyTerm(string $vocabulary, string $term_label) {
    $web_assert = $this->assertSession();
    $page = $this->getCurrentPage();
    $select = $page->findField('vocabulary');
    $select->setValue($vocabulary);
    $this->waitForAjaxToFinish();
    $this->selectOptionWithSelect2('.form-item-terms', $term_label);
    $page->pressButton('Apply');
    $web_assert->statusCodeEquals(200);
  }

  /**
   * Helper function, that will select a term and remove from selected media.
   *
   * @param string $vocabulary
   *   Vocabulary machine name.
   * @param array $term_labels
   *   Array of term labels.
   */
  protected function removeVocabularyTerms(string $vocabulary, array $term_labels) {
    $web_assert = $this->assertSession();
    $page = $this->getCurrentPage();
    $select = $page->findField('vocabulary');
    $select->setValue($vocabulary);
    $this->waitForAjaxToFinish();
    foreach ($term_labels as $term_label) {
      $this->selectOptionWithSelect2('.form-item-terms', $term_label);
    }
    $page->pressButton('Remove');
    $web_assert->statusCodeEquals(200);
  }

}
