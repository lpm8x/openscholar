<?php

namespace Drupal\Tests\cp_taxonomy\ExistingSiteJavascript;

use Drupal\group\Entity\GroupInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests taxonomy_terms fields functionality.
 *
 * @group functional-javascript
 * @group cp
 */
class TaxonomyTermsFieldTest extends CpTaxonomyExistingSiteJavascriptTestBase {
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Test group 1.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group1;

  /**
   * Test group 2.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group2;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Vsite Context Manager.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManagerInterface
   */
  protected $vsiteContextManager;

  /**
   * Group administrator.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupAdmin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->config = $this->container->get('config.factory');
    $this->vsiteContextManager = $this->container->get('vsite.context_manager');

    $this->group1 = $this->createGroup([
      'path' => [
        'alias' => '/' . $this->randomMachineName(),
      ],
    ]);
    $this->group2 = $this->createGroup([
      'path' => [
        'alias' => '/' . $this->randomMachineName(),
      ],
    ]);

    $this->groupAdmin = $this->createUser([
      'create taxonomy_test_1 content',
      'create taxonomy_test_2 content',
      'create taxonomy_test_file media',
    ]);
    $this->addGroupAdmin($this->groupAdmin, $this->group1);
    $this->addGroupAdmin($this->groupAdmin, $this->group2);
    $this->drupalLogin($this->groupAdmin);
    $this->createGroupVocabulary($this->group1, 'vocab_group_1', ['node:taxonomy_test_1']);
    $this->createGroupVocabulary($this->group2, 'vocab_group_2', ['node:taxonomy_test_1']);
  }

  /**
   * Test node taxonomy terms field autocomplete.
   */
  public function testNodeTaxonomyTermsFieldAutocompleteSuccess() {
    $this->createGroupTerm($this->group1, 'vocab_group_1', 'Term 1 group 1 vid1');
    $this->createGroupTerm($this->group1, 'vocab_group_1', 'Term 2 group 1 vid1');
    $this->createGroupTerm($this->group2, 'vocab_group_2', 'Term 1 group 2 vid2');

    $this->visit($this->group1->get('path')->getValue()[0]['alias'] . "/node/add/taxonomy_test_1");
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $is_exists = $page->hasContent('Tag with Terms');
    $this->assertTrue($is_exists, 'Tag with Terms field is not visible.');
    $tags = $page->findField('field_taxonomy_terms[0][target_id]');
    $tags->setValue('Ter');
    $tags->keyDown('m');
    /** @var \Behat\Mink\Element\NodeElement $result */
    $result = $web_assert->waitForElementVisible('css', 'ul.ui-autocomplete');
    $this->assertNotNull($result, 'Autocomplete is not came up.');
    $list_markup = $result->getHtml();
    $this->assertContains('Term 1 group 1 vid1', $list_markup);
    $this->assertContains('Term 2 group 1 vid1', $list_markup);
    $this->assertNotContains('Term 1 group 2 vid2', $list_markup);
  }

  /**
   * Test media taxonomy terms field autocomplete.
   */
  public function testMediaTaxonomyTermsFieldAutocompleteSuccess() {
    $this->createGroupVocabulary($this->group1, 'vocab_media_group_1', ['media:*']);
    $this->createGroupTerm($this->group1, 'vocab_media_group_1', 'Term 1 group 1 vid1');
    $this->createGroupTerm($this->group1, 'vocab_media_group_1', 'Term 2 group 1 vid1');

    $this->visit($this->group1->get('path')->getValue()[0]['alias'] . "/media/add/taxonomy_test_file");
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $is_exists = $page->hasContent('Tag with Terms');
    $this->assertTrue($is_exists, 'Tag with Terms field is not visible.');
    $tags = $page->findField('field_taxonomy_terms[0][target_id]');
    $tags->setValue('Ter');
    $tags->keyDown('m');
    /** @var \Behat\Mink\Element\NodeElement $result */
    $result = $web_assert->waitForElementVisible('css', 'ul.ui-autocomplete');
    $this->assertNotNull($result, 'Autocomplete is not came up.');
    $list_markup = $result->getHtml();
    $this->assertContains('Term 1 group 1 vid1', $list_markup);
    $this->assertContains('Term 2 group 1 vid1', $list_markup);
  }

  /**
   * Test node taxonomy hidden field on node add page.
   */
  public function testNodeTaxonomyHiddenField() {
    $this->visit($this->group1->get('path')->getValue()[0]['alias'] . "/node/add/taxonomy_test_2");
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $is_exists = $page->hasContent('Tag with Terms');
    $this->assertFalse($is_exists, 'Tag with Terms field is visible.');
  }

  /**
   * Creates a taxonomy_test_1.
   *
   * @param array $values
   *   The values used to create the taxonomy_test_1.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node entity.
   */
  protected function createTaxonomyTest1(array $values = []) : NodeInterface {
    $event = $this->createNode($values + [
      'type' => 'taxonomy_test_1',
      'title' => $this->randomString(),
    ]);

    return $event;
  }

  /**
   * Creates a taxonomy_test_2.
   *
   * @param array $values
   *   The values used to create the taxonomy_test_2.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node entity.
   */
  protected function createTaxonomyTest2(array $values = []) : NodeInterface {
    $event = $this->createNode($values + [
      'type' => 'taxonomy_test_2',
      'title' => $this->randomString(),
    ]);

    return $event;
  }

  /**
   * Creates a taxonomy_test_file Media.
   *
   * @param array $values
   *   The values used to create the taxonomy_test_file.
   *
   * @return \Drupal\media\MediaInterface
   *   The created media entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTaxonomyTestFile(array $values = []) : MediaInterface {
    $media = $this->entityTypeManager->getStorage('media')->create($values + [
      'type' => 'taxonomy_test_file',
      'name' => $this->randomMachineName(),
    ]);
    $media->enforceIsNew();
    $media->save();

    $this->markEntityForCleanup($media);

    return $media;
  }

  /**
   * Create a vocabulary to a group.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   Group entity.
   * @param string $vid
   *   Vocabulary id.
   * @param array $allowed_types
   *   Allowed types for entity bundles.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createGroupVocabulary(GroupInterface $group, string $vid, array $allowed_types = []) {
    $this->vsiteContextManager->activateVsite($group);
    $vocab = Vocabulary::create([
      'name' => $vid,
      'vid' => $vid,
    ]);
    $vocab->enforceIsNew();
    $vocab->save();
    if (!empty($allowed_types)) {
      $config_vocab = $this->config->getEditable('taxonomy.vocabulary.' . $vid);
      $config_vocab
        ->set('allowed_vocabulary_reference_types', $allowed_types)
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
   * @param string $name
   *   Taxonomy term name.
   */
  protected function createGroupTerm(GroupInterface $group, string $vid, string $name) {
    $this->vsiteContextManager->activateVsite($group);
    $vocab = Vocabulary::load($vid);
    $term = $this->createTerm($vocab, [
      'name' => $name,
    ]);
    $group->addContent($term, 'group_entity:taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->vsiteContextManager->activateVsite($this->group1);
    $vocabulary_1 = Vocabulary::load('vocab_group_1');
    $vocabulary_1->delete();
    $this->vsiteContextManager->activateVsite($this->group2);
    $vocabulary_2 = Vocabulary::load('vocab_group_2');
    $vocabulary_2->delete();
    parent::tearDown();
  }

}
