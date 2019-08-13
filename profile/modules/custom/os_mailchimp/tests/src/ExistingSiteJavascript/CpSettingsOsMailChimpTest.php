<?php

namespace Drupal\Tests\os_mailchimp\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Tests os_mailchimp module.
 *
 * @group functional-javascript
 * @group mailchimp
 */
class CpSettingsOsMailChimpTest extends OsExistingSiteJavascriptTestBase {

  /**
   * Admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $groupAdmin;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

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
    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $this->group);
    $this->configFactory = $this->container->get('config.factory');
    $this->themeHandler = $this->container->get('theme_handler');
    $this->defaultTheme = $this->themeHandler->getDefault();
  }

  /**
   * Tests os_mailchimp cp settings form submit and default value.
   */
  public function testCpSettingsFormSave() {
    $web_assert = $this->assertSession();
    $this->drupalLogin($this->groupAdmin);

    $this->visit("{$this->group->get('path')->first()->getValue()['alias']}/cp/settings/global-settings/mailchimp");
    $web_assert->statusCodeEquals(200);

    $edit = [
      'api_key' => 'test1234',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');
    $page = $this->getCurrentPage();
    $check_html_value = $page->hasContent('The configuration options have been saved.');
    $this->assertTrue($check_html_value, 'The form did not write the correct message.');

    // Check form elements load default values.
    $this->visit("{$this->group->get('path')->first()->getValue()['alias']}/cp/settings/global-settings/mailchimp");
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $field_value = $page->findField('api_key')->getValue();
    $this->assertSame('test1234', $field_value, 'Form is not loaded api key value.');
  }

  /**
   * Tests block visibility and modal popup.
   *
   * @throws \Behat\Mink\Exception\DriverException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testBlockVisibilityInContentRegion() {
    // This test relies on a test block that is only enabled for os_base.
    /** @var \Drupal\Core\Config\Config $theme_setting */
    $theme_setting = $this->configFactory->getEditable('system.theme');
    $theme_setting->set('default', 'os_base');
    $theme_setting->save();

    $web_assert = $this->assertSession();
    $this->visit("/");
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $is_exists = $page->hasContent('Mailchimp subscribe');
    $this->assertTrue($is_exists, 'Region not contains mailchimp block.');

    // Subscribe link is visible and press it.
    $submit_button = $page->findLink('Subscribe to list!');
    $submit_button->press();

    // Check modal is appeared.
    $this->waitForAjaxToFinish();
    $result = $web_assert->waitForElementVisible('css', '.ui-dialog');
    $this->assertNotNull($result);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    parent::tearDown();

    /** @var \Drupal\Core\Config\Config $theme_setting */
    $theme_setting = $this->configFactory->getEditable('system.theme');
    $theme_setting->set('default', $this->defaultTheme);
    $theme_setting->save();
  }

}
