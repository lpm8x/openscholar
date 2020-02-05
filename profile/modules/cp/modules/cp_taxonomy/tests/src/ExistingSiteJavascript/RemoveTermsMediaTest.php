<?php

namespace Drupal\Tests\cp_taxonomy\ExistingSiteJavascript;

use Drupal\media\Entity\Media;

/**
 * Tests taxonomy terms remove from media.
 *
 * @group functional-javascript
 * @group cp
 * @covers \Drupal\cp_taxonomy\Form\RemoveTermsFromMediaForm
 */
class RemoveTermsMediaTest extends CpTaxonomyExistingSiteJavascriptTestBase {

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
   * Test functionality of Apply term even if vocabulary not related to blog.
   */
  public function testRemovedAndSkippedMedia() {
    $web_assert = $this->assertSession();
    $media1 = $this->createMedia([
      'uid' => $this->groupAdmin->id(),
      'field_taxonomy_terms' => [
        $this->term->id(),
      ],
    ]);
    $this->group->addContent($media1, 'group_entity:media');
    $media2 = $this->createMedia([
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($media2, 'group_entity:media');
    $this->visitViaVsite('cp/content/browse/files', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('media_bulk_form[0]')->check();
    // TODO: Hotfix for media are duplicated at admin list.
    $page->findField('media_bulk_form[2]')->check();
    $this->removeTermWithAction('cp_taxonomy_remove_terms_media_action');
    $web_assert->pageTextContains('No term was removed from the content');
    $web_assert->pageTextContains('Taxonomy term ' . $this->term->label() . ' was removed from the content');
    $warning_wrapper = $page->find('css', '.messages--warning');
    $this->assertContains($media2->label(), $warning_wrapper->getHtml());
    $this->assertNotContains($media1->label(), $warning_wrapper->getHtml());
    $status_wrapper = $page->find('css', '.messages--status');
    $this->assertContains($media1->label(), $status_wrapper->getHtml());
    $this->assertNotContains($media2->label(), $status_wrapper->getHtml());

    $saved_media = Media::load($media1->id());
    $term_value = $saved_media->get('field_taxonomy_terms')->getString();
    $this->assertEmpty($term_value);
  }

}
