<?php

namespace Drupal\Tests\os\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Class NodeRevisionAccessTest.
 *
 * @group functional-javascript
 * @group os
 *
 * @package Drupal\Tests\os\ExistingSiteJavascript
 */
class NodeRevisionAccessTest extends OsExistingSiteJavascriptTestBase {

  /**
   * Node id.
   *
   * @var int
   */
  protected $nid;

  /**
   * User account.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->group = $this->createGroup();
    $this->user = $this->createUser();
    $node = $this->createNode([
      'type' => 'class',
      'title' => 'Test Class Node',
      'uid' => $this->user->id(),
    ]);
    $this->group->addContent($node, 'group_node:class');
    $this->nid = $node->id();
    // Create a new Revision.
    $node->title->value = 'Test Class Node Rev';
    $node->setNewRevision(TRUE);
    $node->save();
  }

  /**
   * Test Revision Access for Group Admin role.
   */
  public function testRevisionsAsVsiteAdmin(): void {
    $this->addGroupAdmin($this->user, $this->group);

    // Test negative without Login.
    $this->visitViaVsite('node/' . $this->nid, $this->group);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Revisions');
    $this->assertSession()->linkByHrefNotExists("/node/$this->nid/revisions");
    $this->visitViaVsite("node/$this->nid/revisions", $this->group);
    $this->assertSession()->statusCodeEquals(403);

    // Test Positive with GroupAdmin login.
    $this->drupalLogin($this->user);
    $this->makeCommonAssertions();
  }

  /**
   * Test Revision Access for Content Editor role.
   */
  public function testRevisionsAsContentEditor(): void {
    $this->addGroupContentEditor($this->user, $this->group);

    // Test with Content Editor login.
    $this->drupalLogin($this->user);
    $this->makeCommonAssertions();

  }

  /**
   * Test Revision Access for Basic Member role.
   */
  public function testRevisionsAsBasicMember(): void {
    $this->addGroupEnhancedMember($this->user, $this->group);

    // Node created before login to test for not owned entity.
    $node2 = $this->createNode([
      'type' => 'class',
      'title' => 'Node 2',
    ]);
    $this->group->addContent($node2, 'group_node:class');
    $nid2 = $node2->id();
    // Create a new Revision.
    $node2->title->value = 'Node 2 Rev';
    $node2->setNewRevision(TRUE);
    $node2->save();

    // Test with Basic Member login.
    $this->drupalLogin($this->user);
    $this->visitViaVsite('node/' . $this->nid, $this->group);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Revisions');
    $this->assertSession()->linkByHrefExists("/node/$this->nid/revisions");

    // Test Revision overview/list page.
    $this->visitViaVsite("node/$this->nid/revisions", $this->group);
    $this->assertSession()->statusCodeEquals(200);

    // Test revert/delete links are accessible for own content.
    $this->assertSession()->elementExists('css', '.revert');
    $this->assertSession()->elementExists('css', '.delete');
    // Test Reverting route is accessible.
    $this->getCurrentPage()->clickLink('Revert');
    $this->assertSession()->statusCodeEquals(200);

    // Test No revert/delete links are accessible for not owned content
    // means access is denied for revert/delete operations.
    $this->visitViaVsite("node/$nid2/revisions", $this->group);
    $this->assertSession()->elementNotExists('css', '.revert');
    $this->assertSession()->elementNotExists('css', '.delete');

  }

  /**
   * Common assertion for GroupAdmin and Content Editor.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function makeCommonAssertions() {
    $this->visitViaVsite('node/' . $this->nid, $this->group);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Revisions');
    $this->assertSession()->linkByHrefExists("/node/$this->nid/revisions");

    // Test Revision overview/list page.
    $this->visitViaVsite("node/$this->nid/revisions", $this->group);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Revert');
    $this->assertSession()->linkExists('Delete');

    // Test Reverting route is accessible.
    $this->getCurrentPage()->clickLink('Revert');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '#edit-submit');

    // Test Reverting a revision works.
    $this->submitForm([], 'Revert');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('has been reverted to the revision from');

    // Test Deleting route is accessible.
    $this->getCurrentPage()->clickLink('Delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '#edit-submit');

    // Test Deleting a revision works.
    $this->submitForm([], 'Delete');
    $this->assertSession()->statusCodeEquals(200);
  }

}
