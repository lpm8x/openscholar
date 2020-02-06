<?php

namespace Drupal\Tests\os_search\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Tests Subsite Search Block.
 *
 * @group os-search
 * @group functional
 * @covers \Drupal\os_search\Plugin\Block\OsFilterByPostGlobalAPPBlock
 */
class OsFilterByPostGlobalAPPBlockTest extends OsExistingSiteTestBase {

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->configFactory = $this->container->get('config.factory');
    $themeHandler = $this->container->get('theme_handler');
    $this->defaultTheme = $themeHandler->getDefault();
    $this->appManager = $this->container->get('vsite.app.manager');
  }

  /**
   * Tests block visibility for subsite search.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function test() {
    // This test relies on a test block that is only enabled for os_base.
    /** @var \Drupal\Core\Config\Config $theme_setting */
    $theme_setting = $this->configFactory->getEditable('system.theme');
    $theme_setting->set('default', 'os_base');
    $theme_setting->save();

    $enabled_apps = $this->appManager->getDefinitions();
    // Test assertion for page contains widget name.
    foreach ($enabled_apps as $app) {
      $this->drupalGet("/browse/" . $app['id']);
      $this->assertSession()->statusCodeEquals(200);
      $page = $this->getCurrentPage();
      $is_exists = $page->hasContent('Filter By Post');
      $this->assertTrue($is_exists, 'Region not contains subsite search block.');
    }

  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    /** @var \Drupal\Core\Config\Config $theme_setting */
    $theme_setting = $this->configFactory->getEditable('system.theme');
    $theme_setting->set('default', $this->defaultTheme);
    $theme_setting->save();

    parent::tearDown();
  }

}
