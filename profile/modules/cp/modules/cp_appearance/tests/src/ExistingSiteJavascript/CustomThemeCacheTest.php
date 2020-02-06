<?php

namespace Drupal\Tests\cp_appearance\ExistingSiteJavascript;

use Drupal\cp_appearance\Entity\CustomTheme;

/**
 * Tests whether custom theme cache invalidation is working as expected.
 *
 * @group functional-javascript
 * @group cp-appearance
 */
class CustomThemeCacheTest extends CpAppearanceExistingSiteJavascriptTestBase {

  /**
   * Test group admin.
   *
   * @var \Drupal\user\UserInterface
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
  }

  /**
   * Tests whether the custom theme cached data is invalidated when updated.
   *
   * @covers ::cp_appearance_cp_custom_theme_update
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function testThemeDataCacheInvalidation(): void {
    /** @var \Drupal\Core\Cache\CacheBackendInterface $cache */
    $cache = $this->container->get('cache.bootstrap');
    /** @var \Drupal\Core\Theme\ThemeInitializationInterface $theme_initializer */
    $theme_initializer = $this->container->get('theme.initialization');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $custom_theme_storage */
    $custom_theme_storage = $entity_type_manager->getStorage('cp_custom_theme');

    // Create the custom theme.
    $this->visitViaVsite('cp/appearance/themes/custom-themes/add', $this->group);
    $this->getSession()->getPage()->fillField('Custom Theme Name', 'RDR2');
    $this->assertSession()->waitForElementVisible('css', '.machine-name-value');
    $this->getSession()->getPage()->selectFieldOption('Parent Theme', 'documental');
    $this->getSession()->getPage()->findField('styles')->setValue('body { color: black; }');
    $this->getSession()->getPage()->findField('scripts')->setValue('alert("Hello World")');
    $this->getSession()->getPage()->pressButton('Save and set as default theme');
    $this->getSession()->getPage()->pressButton('Confirm');

    /** @var \Drupal\vsite\Plugin\VsiteContextManagerInterface $vsite_context_manager */
    $vsite_context_manager = $this->container->get('vsite.context_manager');
    $vsite_context_manager->activateVsite($this->group);
    /** @var \Drupal\cp_appearance\Entity\CustomThemeInterface $custom_theme */
    $custom_theme = $custom_theme_storage->load(CustomTheme::CUSTOM_THEME_ID_PREFIX . $this->group->id() . '_' . 'rdr2');
    $this->assertNotNull($custom_theme);

    $theme_initializer->initTheme($custom_theme->id());
    /** @var object $original_cached_data */
    $original_cached_data = $cache->get("theme.active_theme.{$custom_theme->id()}");

    // Do changes.
    $this->visitViaVsite("cp/appearance/themes/custom-themes/{$custom_theme->id()}/edit", $this->group);
    $this->getSession()->getPage()->selectFieldOption('Parent Theme', 'hwpi_classic');
    $this->getSession()->getPage()->pressButton('Save');

    // Check whether the cache has been invalidated.
    $new_cached_data = $cache->get("theme.active_theme.{$custom_theme->id()}");
    $this->assertNotSame($original_cached_data->expire, $new_cached_data->expire);

    // Cleanup.
    $custom_theme->delete();
    /** @var \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler */
    $theme_handler = $this->container->get('theme_handler');
    $theme_handler->refreshInfo();
  }

}
