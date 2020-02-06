<?php

namespace Drupal\Tests\cp_taxonomy\ExistingSiteJavascript;

use Drupal\node\Entity\Node;

/**
 * Tests taxonomy terms remove from nodes.
 *
 * @group functional-javascript
 * @group cp
 * @covers \Drupal\cp_taxonomy\Form\RemoveTermsFromNodeForm
 */
class RemoveTermsNodeTest extends CpTaxonomyExistingSiteJavascriptTestBase {

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
  public function testRemovedAndSkippedNodes() {
    $web_assert = $this->assertSession();
    $faq = $this->createNode([
      'type' => 'faq',
      'uid' => $this->groupAdmin->id(),
      'field_taxonomy_terms' => [
        $this->term->id(),
      ],
    ]);
    $this->group->addContent($faq, 'group_node:faq');
    $blog = $this->createNode([
      'type' => 'blog',
      'uid' => $this->groupAdmin->id(),
    ]);
    $this->group->addContent($blog, 'group_node:blog');
    $this->visitViaVsite('cp/content/browse/node', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('node_bulk_form[0]')->check();
    $page->findField('node_bulk_form[1]')->check();
    $this->applyAction('cp_taxonomy_remove_terms_node_action');
    $this->removeVocabularyTerms('vocab_group_1', [$this->term->label()]);
    $web_assert->pageTextContains('No term was removed from the content');
    $web_assert->pageTextContains('Taxonomy term ' . $this->term->label() . ' was removed from the content');
    $warning_wrapper = $page->find('css', '.messages--warning');
    $this->assertContains($blog->label(), $warning_wrapper->getHtml());
    $this->assertNotContains($faq->label(), $warning_wrapper->getHtml());
    $status_wrapper = $page->find('css', '.messages--status');
    $this->assertContains($faq->label(), $status_wrapper->getHtml());
    $this->assertNotContains($blog->label(), $status_wrapper->getHtml());

    $saved_faq = Node::load($faq->id());
    $term_value = $saved_faq->get('field_taxonomy_terms')->getString();
    $this->assertEmpty($term_value);
  }

  /**
   * Test remove multiple terms.
   */
  public function testRemoveMultipleTerms() {
    $web_assert = $this->assertSession();
    $term1 = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
    $term2 = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
    $term3 = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
    $term4 = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
    $term5 = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
    $term6 = $this->createGroupTerm($this->group, 'vocab_group_1', ['name' => $this->randomMachineName()]);
    $blog = $this->createNode([
      'type' => 'blog',
      'uid' => $this->groupAdmin->id(),
      'field_taxonomy_terms' => [
        $term1->id(),
        $term3->id(),
        $term5->id(),
      ],
    ]);
    $this->group->addContent($blog, 'group_node:blog');
    $this->visitViaVsite('cp/content/browse/node', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->findField('node_bulk_form[0]')->check();
    $web_assert = $this->assertSession();
    $this->applyAction('cp_taxonomy_remove_terms_node_action');
    $page = $this->getCurrentPage();
    // Add all 6 terms.
    $terms_to_remove = [
      $term1->label(),
      $term2->label(),
      $term3->label(),
      $term4->label(),
      $term5->label(),
      $term6->label(),
    ];
    $this->removeVocabularyTerms('vocab_group_1', $terms_to_remove);
    $web_assert->statusCodeEquals(200);

    $status_wrapper = $page->find('css', '.messages--status');
    $this->assertContains($blog->label(), $status_wrapper->getHtml());

    $saved_blog = Node::load($blog->id());
    $term_value = $saved_blog->get('field_taxonomy_terms')->getString();
    $this->assertEmpty($term_value);
  }

}
