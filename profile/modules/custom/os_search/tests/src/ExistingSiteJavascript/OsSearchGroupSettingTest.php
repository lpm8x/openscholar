<?php

namespace Drupal\Tests\os_search\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Tests os_search module.
 *
 * @group functional-javascript
 * @group os-search
 */
class OsSearchGroupSettingTest extends OsExistingSiteJavascriptTestBase {

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $groupAdmin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->searchHelper = $this->container->get('os_search.os_search_helper');
    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $this->group);
    $this->drupalLogin($this->groupAdmin);
    $this->fields = [
      'custom_date' => 'Post Date',
      'custom_search_bundle' => 'Post Type',
      'custom_search_group' => 'Other Sites',
      'custom_taxonomy' => 'Taxonomy',
      'search_sort' => 'Search Sort',
    ];
  }

  /**
   * Tests block content on vsite creation.
   */
  public function testBlockContentVsiteCreation(): void {
    $this->searchHelper->createGroupBlockWidget($this->group);
    // Test assertion for page contains widget name.
    foreach ($this->fields as $field_info) {
      $this->visitViaVsite('node?block-place=1', $this->group);
      $this->assertSession()->pageTextContains($field_info);
    }
  }

}
