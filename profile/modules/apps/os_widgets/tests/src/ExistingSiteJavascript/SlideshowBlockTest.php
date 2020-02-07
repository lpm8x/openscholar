<?php

namespace Drupal\Tests\os_widgets\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Tests os_widgets module.
 *
 * @group functional-javascript
 * @group widgets
 */
class SlideshowBlockTest extends OsExistingSiteJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $group_admin = $this->createUser();
    $this->addGroupAdmin($group_admin, $this->group);
    $this->drupalLogin($group_admin);
  }

  /**
   * Tests os_widgets slideshow overlay new form.
   */
  public function testSlideshowOverlayNewForm() {
    $web_assert = $this->assertSession();

    $this->visitViaVsite("?block-place=1", $this->group);
    $web_assert->statusCodeEquals(200);

    $page = $this->getCurrentPage();
    $page->pressButton('Create New Widget');
    $page->find('xpath', '//li[contains(@class, "slideshow")]/a')->press();
    $web_assert->waitForText('Add new "Slideshow" Widget');

    // Check default form selection.
    $default_value = $page->findField('field_slideshow_layout')->getValue();
    $this->assertSame('3_1_overlay', $default_value);

    // We should see all 4 options on widget creation.
    $web_assert->pageTextContains('Widescreen Overlay');
    $web_assert->pageTextContains('Standard Overlay');
    $web_assert->pageTextContains('Standard Below');
    $web_assert->pageTextContains('Standard Side');
  }

}
