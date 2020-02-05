<?php

namespace Drupal\Tests\cp_taxonomy\ExistingSiteJavascript;

use Drupal\media\Entity\Media;

/**
 * Tests taxonomy terms apply to media.
 *
 * @group functional-javascript
 * @group cp
 * @covers \Drupal\cp_taxonomy\Form\AddTermsToMediaForm
 */
class ApplyTermsMediaTest extends CpTaxonomyExistingSiteJavascriptTestBase {

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
      'media:*',
    ];
    $this->createGroupVocabulary($this->group, 'vocab_group_1', $allowed_types);
    $this->term = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
  }

  /**
   * Test functionality of Apply term to media.
   */
  public function testAppliedTermMedia() {
    $web_assert = $this->assertSession();
    $media = $this->createMedia([
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($media, 'group_entity:media');
    $this->visitViaVsite('cp/content/browse/files', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('media_bulk_form[0]')->check();
    $this->applyAction('cp_taxonomy_add_terms_media_action');
    $this->applyVocabularyFirstTerm('vocab_group_1');
    $web_assert->pageTextContains('Taxonomy term ' . $this->term->label() . ' was applied on the content');
    $status_wrapper = $page->find('css', '.messages--status');
    $this->assertContains($media->label(), $status_wrapper->getHtml());

    $saved_media = Media::load($media->id());
    $term_value = $saved_media->get('field_taxonomy_terms')->getString();
    $this->assertEqual($this->term->id(), $term_value);
  }

}
