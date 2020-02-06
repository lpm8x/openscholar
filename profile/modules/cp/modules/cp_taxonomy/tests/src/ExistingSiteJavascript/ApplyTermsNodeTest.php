<?php

namespace Drupal\Tests\cp_taxonomy\ExistingSiteJavascript;

use Drupal\node\Entity\Node;

/**
 * Tests taxonomy terms apply to nodes.
 *
 * @group functional-javascript
 * @group cp
 * @covers \Drupal\cp_taxonomy\Form\AddTermsToNodeForm
 */
class ApplyTermsNodeTest extends CpTaxonomyExistingSiteJavascriptTestBase {

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
      'node:faq',
    ];
    $this->createGroupVocabulary($this->group, 'vocab_group_1', $allowed_types);
    $this->term = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
  }

  /**
   * Test functionality of Apply term even if vocabulary not related to blog.
   */
  public function testAppliedAndSkippedNodes() {
    $web_assert = $this->assertSession();
    $blog = $this->createNode([
      'type' => 'blog',
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($blog, 'group_node:blog');
    $faq = $this->createNode([
      'type' => 'faq',
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($faq, 'group_node:faq');
    $this->visitViaVsite('cp/content/browse/node', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('node_bulk_form[0]')->check();
    $page->findField('node_bulk_form[1]')->check();
    $this->applyAction('cp_taxonomy_add_terms_node_action');
    $this->applyVocabularyTerm('vocab_group_1', $this->term->label());
    $web_assert->pageTextContains('Taxonomy term ' . $this->term->label() . ' could not be applied on the content');
    $web_assert->pageTextContains('Taxonomy term ' . $this->term->label() . ' was applied on the content');
    $warning_wrapper = $page->find('css', '.messages--warning');
    $this->assertContains($blog->label(), $warning_wrapper->getHtml());
    $this->assertNotContains($faq->label(), $warning_wrapper->getHtml());
    $status_wrapper = $page->find('css', '.messages--status');
    $this->assertContains($faq->label(), $status_wrapper->getHtml());
    $this->assertNotContains($blog->label(), $status_wrapper->getHtml());

    $saved_faq = Node::load($faq->id());
    $term_value = $saved_faq->get('field_taxonomy_terms')->getString();
    $this->assertEqual($this->term->id(), $term_value);
    $saved_blog = Node::load($blog->id());
    $term_value = $saved_blog->get('field_taxonomy_terms')->getString();
    $this->assertNotContains($this->term->id(), $term_value);
  }

  /**
   * Test add invalid vocabulary.
   */
  public function testApplyInvalidVocabulary() {
    $web_assert = $this->assertSession();
    $blog = $this->createNode([
      'type' => 'blog',
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($blog, 'group_node:blog');
    $allowed_types = [
      'media:*',
    ];
    $media_vocab = $this->randomMachineName();
    $this->createGroupVocabulary($this->group, $media_vocab, $allowed_types);
    $media_term = $this->createGroupTerm($this->group, $media_vocab, ['name' => $this->randomMachineName()]);
    $this->visitViaVsite('cp/content/browse/node', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('node_bulk_form[0]')->check();
    $this->applyAction('cp_taxonomy_add_terms_node_action');
    $this->applyVocabularyTerm($media_vocab, $media_term->label());
    $web_assert->pageTextContains('Selected vocabulary is not handle node entity type.');
  }

}
