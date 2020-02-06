<?php

namespace Drupal\Tests\cp_taxonomy\ExistingSiteJavascript;

use Drupal\bibcite_entity\Entity\Reference;

/**
 * Tests taxonomy terms apply to publication.
 *
 * @group functional-javascript
 * @group cp
 * @covers \Drupal\cp_taxonomy\Form\AddTermsToPublicationForm
 */
class ApplyTermsPublicationTest extends CpTaxonomyExistingSiteJavascriptTestBase {

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
   * Test functionality of Apply term to publication.
   */
  public function testAppliedTermMedia() {
    $web_assert = $this->assertSession();
    $publication = $this->createReference([
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($publication, 'group_entity:bibcite_reference');
    $this->visitViaVsite('cp/content/browse/publications', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('bibcite_reference_bulk_form[0]')->check();
    $this->applyAction('cp_taxonomy_add_terms_bibcite_reference_action');
    $this->applyVocabularyTerm('vocab_group_1', $this->term->label());
    $web_assert->pageTextContains('Taxonomy term ' . $this->term->label() . ' was applied on the content');
    $status_wrapper = $page->find('css', '.messages--status');
    $this->assertContains($publication->label(), $status_wrapper->getHtml());

    $saved_publication = Reference::load($publication->id());
    $term_value = $saved_publication->get('field_taxonomy_terms')->getString();
    $this->assertEqual($this->term->id(), $term_value);
  }

}
