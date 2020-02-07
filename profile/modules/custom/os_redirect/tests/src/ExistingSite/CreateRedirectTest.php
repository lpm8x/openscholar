<?php

namespace Drupal\Tests\os_redirect\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Tests os_redirect module.
 *
 * @group redirect
 * @group functional
 *
 * @coversDefaultClass \Drupal\os_redirect\Form\OsRedirectForm
 */
class CreateRedirectTest extends OsExistingSiteTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $site_user = $this->createUser();
    $this->addGroupAdmin($site_user, $this->group);
    $this->drupalLogin($site_user);
    // Prevent to set global config.
    $this->container->get('vsite.context_manager')->activateVsite($this->group);
  }

  /**
   * Tests add redirect.
   */
  public function testAddRedirectInVsiteSuccess() {
    $web_assert = $this->assertSession();

    $this->visitViaVsite("cp/redirects/add", $this->group);
    $web_assert->statusCodeEquals(200);

    $test_uri = 'http://' . $this->randomMachineName() . '.com';
    $add_values = [
      'redirect_source[0][path]' => 'lorem1-new',
      'redirect_redirect[0][uri]' => $test_uri,
    ];
    $this->drupalPostForm(NULL, $add_values, 'Save');
    $this->assertContains('The redirect has been saved.', $this->getCurrentPageContent());

    $this->cleanUpRedirectByUri($test_uri);

    // Check new content on list page.
    $this->visitViaVsite("cp/redirects/list", $this->group);
    $web_assert->statusCodeEquals(200);
    $this->assertContains('lorem1-new', $this->getCurrentPageContent(), 'Test redirect is source not visible.');

  }

  /**
   * Tests add redirect.
   */
  public function testAddRedirectGlobal() {
    $web_assert = $this->assertSession();

    // Normal site user should not access global page.
    $this->visit("/cp/redirects/add");
    $web_assert->statusCodeEquals(403);
  }

  /**
   * Test add redirect with limit.
   */
  public function testAddRedirectInVsiteLimit() {
    $web_assert = $this->assertSession();

    // Set vsite maximum number.
    $configFactory = $this->container->get('config.factory');
    $config = $configFactory->getEditable('os_redirect.settings');
    $config->set('maximum_number', 0);
    $config->save(TRUE);

    $this->visit($this->group->get('path')->getValue()[0]['alias'] . "/cp/redirects/add");
    $web_assert->statusCodeEquals(200);

    $add_values = [
      'redirect_source[0][path]' => $this->randomMachineName(),
      'redirect_redirect[0][uri]' => 'http://example.com',
    ];
    $this->drupalPostForm(NULL, $add_values, 'Save');
    $web_assert->statusCodeEquals(200);
    $this->assertContains('Maximum number of redirects', $this->getCurrentPageContent());
    $this->assertNotContains('The redirect has been saved.', $this->getCurrentPageContent());
  }

  /**
   * Test redirect creation when source is exists.
   */
  public function testCreateExistsRedirect() {
    $web_assert = $this->assertSession();
    $path = $this->randomMachineName();
    $redirect = $this->createRedirect([
      'redirect_source' => [
        'path' => '[vsite:' . $this->group->id() . ']/' . $path,
      ],
      'redirect_redirect' => [
        'uri' => 'http://example.com',
      ],
    ]);
    $this->group->addContent($redirect, 'group_entity:redirect');
    $this->visitViaVsite('cp/redirects/add', $this->group);
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->fillField('redirect_source[0][path]', $path);
    $page->fillField('redirect_redirect[0][uri]', '/' . $this->randomMachineName());
    $page->pressButton('Save');
    $web_assert->statusCodeEquals(200);
    // Error message should printed without vsite and edit link.
    $web_assert->pageTextContains('The source path /' . $path . ' is already being redirected.');
  }

}
