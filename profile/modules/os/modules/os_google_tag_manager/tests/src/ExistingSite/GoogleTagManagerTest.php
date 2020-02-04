<?php

namespace Drupal\Tests\os_google_tag_manager\ExistingSite;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class GoogleTagManagerTest.
 *
 * @group functional
 * @group analytics
 */
class GoogleTagManagerTest extends OsExistingSiteTestBase {

  /**
   * Group administrator.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $groupAdmin;

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $google_tag_files_path = 'public://google_tag/google_tag/test_container';
    $file_system = $this->container->get('file_system');
    $file_system->prepareDirectory($google_tag_files_path, FileSystemInterface::CREATE_DIRECTORY);
    $file_system->chmod($google_tag_files_path, 0777);

    $group = $this->createGroup([
      'type' => 'personal',
      'path' => [
        'alias' => '/test-vsite1',
      ],
    ]);
    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $group);
    $this->adminUser = $this->createUser([], '', TRUE);
    $this->configFactory = $this->container->get('config.factory');

    $this->drupalLogin($this->groupAdmin);
    $this->drupalGet('test-vsite1/cp/settings/global-settings/tag_manager');
    $edit = [
      'container_id' => 'GTM-VSITE1',
    ];
    $this->submitForm($edit, 'edit-submit');
  }

  /**
   * Test GTM CpSettings form.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testGtmSettingsForm() : void {

    $this->assertSession()->fieldValueEquals('container_id', 'GTM-VSITE1');

    // Test negative case.
    $edit = [
      'container_id' => 'GTM-123',
    ];
    $this->submitForm($edit, 'edit-submit');
    $this->assertSession()->pageTextContains('A valid container ID is case sensitive and formatted like GTM-xxxxxx');
  }

  /**
   * Tests different scenarios of scripts on page. Also with GA.
   */
  public function testVsiteScriptOnly() {
    $this->drupalGet('test-vsite1');
    $this->assertSession()->responseContains('id=GTM-VSITE1');
    $this->assertSession()
      ->responseContains("'script','dataLayer','GTM-VSITE1'");
  }

  /**
   * Test both Global and vsite together.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testGlobalAndVsite() {
    $this->setGlobalId();
    $this->drupalGet('test-vsite1');
    $this->assertSession()->responseContains("'script','dataLayer','GTM-VSITE1'");
    $this->assertSession()->responseContains("id=GTM-GLOBAL");
  }

  /**
   * Test Vsite specific scripts appear only for the one it is meant to.
   */
  public function testTwoVsites() {
    // Create vsite2 for testing.
    $group2 = $this->createGroup([
      'type' => 'personal',
      'path' => [
        'alias' => '/test-vsite2',
      ],
    ]);
    $groupAdmin2 = $this->createUser();
    $this->addGroupAdmin($groupAdmin2, $group2);

    $this->drupalLogin($groupAdmin2);

    $this->setGlobalId();

    // Test if vsite container id is NOT set, Global code appears.
    $this->drupalGet('test-vsite2');
    $this->assertSession()->responseContains("id=GTM-GLOBAL");

    $this->drupalGet('test-vsite2/cp/settings/global-settings/tag_manager');
    $edit = [
      'container_id' => 'GTM-VSITE2',
    ];
    $this->submitForm($edit, 'edit-submit');

    $this->drupalGet('test-vsite1');
    $this->assertSession()->responseContains('id=GTM-VSITE1');
    $this->assertSession()->responseNotContains('id=GTM-VSITE2');
    // Test Global code appears too.
    $this->assertSession()->responseContains('id=GTM-GLOBAL');

    $this->drupalGet('test-vsite2');
    $this->assertSession()->responseContains('id=GTM-VSITE2');
    $this->assertSession()->responseNotContains('id=GTM-VSITE1');
    // Test Global code appears too.
    $this->assertSession()->responseContains('id=GTM-GLOBAL');
  }

  /**
   * Test Google Analytics and GTM both appear if configured.
   */
  public function testGtmWithGa() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('test-vsite1/cp/settings/global-settings/analytics');
    $edit = [
      'edit-web-property-id' => 'UA-111111111-1',
    ];
    $this->submitForm($edit, 'edit-submit');
    $this->drupalGet('test-vsite1');
    $this->assertSession()->responseContains('ga("send", "pageview")');
    $this->assertSession()->responseContains('UA-111111111-1');
    $this->assertSession()->responseContains("'script','dataLayer','GTM-VSITE1'");
    $this->assertSession()->responseContains('id=GTM-VSITE1');
  }

  /**
   * Set Global GTM container id.
   */
  private function setGlobalId() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/system/google-tag/add');
    $edit = [
      'label' => 'test_container',
      'id' => 'test_container',
      'container_id' => 'GTM-GLOBAL',
    ];
    $this->submitForm($edit, 'edit-submit');
  }

  /**
   * Undo the config changes.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function tearDown() {
    $this->configFactory->getEditable('google_tag.container.test_container')->delete();
    parent::tearDown();
  }

}
