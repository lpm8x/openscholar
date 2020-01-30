<?php

namespace Drupal\Tests\os_redirect\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Tests os_search module.
 *
 * @group functional-javascript
 * @group os-search
 */
class OsSearchGlobalAppPageTest extends OsExistingSiteJavascriptTestBase {

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
    $this->appManager = $this->container->get('vsite.app.manager');
  }

  /**
   * Tests block content on vsite creation.
   */
  public function testBlockContentVsiteCreation(): void {
    $enabled_apps = $this->appManager->getDefinitions();
    // Test assertion for page contains widget name.
    foreach ($enabled_apps as $app) {
      // Check form elements load default values.
      $this->drupalGet('browse/' . $app['id']);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->pageTextContains($app['title']->__toString());
    }
  }

}
