<?php

namespace Drupal\Tests\cp_taxonomy\ExistingSiteJavascript;

use Drupal\bibcite_entity\Entity\Reference;

/**
 * Tests taxonomy terms remove from publication.
 *
 * @group functional-javascript
 * @group cp
 * @covers \Drupal\cp_taxonomy\Form\RemoveTermsFromPublicationForm
 */
class RemoveTermsPublicationTest extends CpTaxonomyExistingSiteJavascriptTestBase {

  /**
   * Test term entity.
   *
   * @var \Drupal\taxonomy\Entity\Term
   *   Taxonomy term.
   */
  protected $term;

  /**
   * Test group admin.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $groupAdmin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $this->group);
    $this->drupalLogin($this->groupAdmin);
    $allowed_types = [
      'bibcite_reference:*',
    ];
    $this->createGroupVocabulary($this->group, 'vocab_group_1', $allowed_types);
    $this->term = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
  }

  /**
   * Test functionality of Apply term even if vocabulary not related to blog.
   */
  public function testRemovedAndSkippedPublications() {
    $web_assert = $this->assertSession();
    $publication1 = $this->createReference([
      'uid' => $this->groupAdmin->id(),
      'field_taxonomy_terms' => [
        $this->term->id(),
      ],
    ]);
    $this->group->addContent($publication1, 'group_entity:bibcite_reference');
    $publication2 = $this->createReference([
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($publication2, 'group_entity:bibcite_reference');
    $this->visitViaVsite('cp/content/browse/publications', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('bibcite_reference_bulk_form[0]')->check();
    $page->findField('bibcite_reference_bulk_form[1]')->check();
    $this->applyAction('cp_taxonomy_remove_terms_bibcite_reference_action');
    $this->removeVocabularyTerms('vocab_group_1', [$this->term->label()]);
    $web_assert->pageTextContains('No term was removed from the content');
    $web_assert->pageTextContains('Taxonomy term ' . $this->term->label() . ' was removed from the content');
    $warning_wrapper = $page->find('css', '.messages--warning');
    $this->assertContains($publication2->label(), $warning_wrapper->getHtml());
    $this->assertNotContains($publication1->label(), $warning_wrapper->getHtml());
    $status_wrapper = $page->find('css', '.messages--status');
    $this->assertContains($publication1->label(), $status_wrapper->getHtml());
    $this->assertNotContains($publication2->label(), $status_wrapper->getHtml());

    $saved_publication = Reference::load($publication1->id());
    $term_value = $saved_publication->get('field_taxonomy_terms')->getString();
    $this->assertEmpty($term_value);
  }

}
