<?php

namespace Drupal\Tests\os_search\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Tests Subsite Search Block.
 *
 * @group os-search
 * @group kernel
 * @covers \Drupal\os_search\Plugin\Block\OsSearchTaxonomyGlobalAPPBlock
 */
class OsSearchTaxonomyGlobalAPPBlockTest extends OsExistingSiteTestBase {

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

    $web_assert = $this->assertSession();
    $this->visit("/browse/*");
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $is_exists = $page->hasContent('Filter By Taxonomy');
    $this->assertTrue($is_exists, 'Region not contains subsite search block.');
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
