<?php

namespace Drupal\Tests\os_metatag\ExistingSiteJavascript;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Vsite metatag tests.
 *
 * @group functional-javascript
 * @group metatag
 */
class VsiteMetatagTest extends OsExistingSiteTestBase {

  /**
   * Test group.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * Test file logo.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $fileLogo;

  /**
   * A test user with group creation rights.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupMember;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    /** @var \Drupal\vsite\Plugin\VsiteContextManagerInterface $vsite_context_manager */
    $vsite_context_manager = $this->container->get('vsite.context_manager');

    $this->fileLogo = $this->createFile('image');
    $this->group = $this->createGroup([
      'path' => [
        'alias' => '/test-alias',
      ],
      'field_site_logo' => [
        'target_id' => $this->fileLogo->id(),
        'alt' => 'lorem',
      ],
      'field_site_description' => '<p>Lorem ipsum dolor</p>',
    ]);
    $vsite_context_manager->activateVsite($this->group);

    $this->groupMember = $this->createUser();
    $this->group->addMember($this->groupMember);
    $this->drupalLogin($this->groupMember);
  }

  /**
   * Test metatags is exists on vsite frontpage.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMetatagsOnVsiteFrontPage(): void {
    $web_assert = $this->assertSession();

    $this->visitViaVsite('test-alias', $this->group);
    $web_assert->statusCodeEquals(200);
    $expectedHtmlValue = '<meta name="twitter:image" content="http://apache/sites/default/files/styles/large/public/' . $this->fileLogo->getFilename();
    $this->assertContains($expectedHtmlValue, $this->getCurrentPageContent(), 'HTML head not contains twitter image.');
    $expectedHtmlValue = '<meta property="og:image" content="http://apache/sites/default/files/styles/large/public/' . $this->fileLogo->getFilename();
    $this->assertContains($expectedHtmlValue, $this->getCurrentPageContent(), 'HTML head not contains og image.');
    $expectedHtmlValue = '<meta property="og:type" content="personal" />';
    $this->assertContains($expectedHtmlValue, $this->getCurrentPageContent(), 'HTML head not contains og type.');
    $expectedHtmlValue = '<meta name="twitter:description" content="Lorem ipsum dolor" />';
    $this->assertContains($expectedHtmlValue, $this->getCurrentPageContent(), 'HTML head not contains og type.');

  }

}
