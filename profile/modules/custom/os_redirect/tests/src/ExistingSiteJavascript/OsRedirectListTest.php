<?php

namespace Drupal\Tests\os_redirect\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Tests os_redirect module.
 *
 * @group functional-javascript
 * @group redirect
 */
class OsRedirectListTest extends OsExistingSiteJavascriptTestBase {

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
    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $this->group);
  }

  /**
   * Restrict os_redirect settings access.
   */
  public function testOsRedirectCreateAndRedirect() {
    $web_assert = $this->assertSession();
    $this->drupalLogin($this->groupAdmin);
    $this->visitViaVsite('cp/redirects/add', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->fillField('redirect_source[0][path]', $this->randomMachineName());
    $uri = '/' . $this->randomMachineName();
    $page->fillField('redirect_redirect[0][uri]', $uri);
    $page->pressButton('Save');
    $web_assert->statusCodeEquals(200);

    // Do cleanup.
    $this->cleanUpRedirectByUri('internal:' . $uri);

    // Assert redirect.
    $url = $this->getUrl();
    $this->assertContains('cp/redirects/list', $url);
  }

}
