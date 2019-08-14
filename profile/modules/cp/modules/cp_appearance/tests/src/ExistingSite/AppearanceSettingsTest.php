<?php

namespace Drupal\Tests\cp_appearance\ExistingSite;

/**
 * AppearanceSettingsTest.
 *
 * @group functional
 * @group cp-appearance
 * @coversDefaultClass \Drupal\cp_appearance\Controller\CpAppearanceMainController
 */
class AppearanceSettingsTest extends TestBase {

  /**
   * Test group.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->group = $this->createGroup([
      'path' => [
        'alias' => '/cp-appearance',
      ],
    ]);
    $this->addGroupAdmin($this->groupAdmin, $this->group);

    $this->drupalLogin($this->groupAdmin);
  }

  /**
   * Tests appearance change.
   *
   * @covers ::main
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testSave(): void {
    $this->visit('/cp-appearance/cp/appearance/themes');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Select Theme');

    $this->getCurrentPage()->selectFieldOption('theme', 'hwpi_lamont');
    $this->getCurrentPage()->pressButton('Save Theme');

    $this->visit('/cp-appearance');
    $this->assertSession()->responseContains('/profiles/contrib/openscholar/themes/hwpi_lamont/css/style.css');

    $this->visit('/');
  }

  /**
   * @covers ::setTheme
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSetDefault(): void {
    $this->visit('/cp-appearance/cp/appearance/themes/set/hwpi_college');

    $this->assertSession()->statusCodeEquals(200);

    $this->visit('/cp-appearance');
    $this->assertSession()->responseContains('/profiles/contrib/openscholar/themes/hwpi_college/css/style.css');

    $this->visit('/');
  }

  /**
   * @covers ::previewTheme
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testStartPreview(): void {
    $this->visit('/cp-appearance/cp/appearance/themes/preview/vibrant');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Previewing: Vibrant');

    $this->visit('/cp-appearance/cp/appearance/themes/preview/hwpi_sterling');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Previewing: Sterling');

    $this->visit('/');
  }

}
