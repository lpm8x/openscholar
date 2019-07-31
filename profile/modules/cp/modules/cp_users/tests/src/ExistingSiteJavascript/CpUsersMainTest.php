<?php

namespace Drupal\Tests\cp_users\ExistingSiteJavascript;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Class CpUsersMainTests.
 *
 * @group functional-javascript
 * @group cp
 * @package Drupal\Tests\cp_users\ExistingSite
 */
class CpUsersMainTest extends OsExistingSiteJavascriptTestBase {

  use AssertMailTrait;

  /**
   * The group tests are being run in.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * The mail interface we're replacing. We need to put it back when we're done.
   *
   * @var string
   */
  protected $oldMailHandler;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The group modifier in use.
   *
   * @var string
   */
  protected $modifier;

  /**
   * Group administrator.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $groupAdmin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $this->configFactory = $this->container->get('config.factory');
    $config = $this->configFactory->getEditable('system.mail');
    $this->oldMailHandler = $config->get('interface.default');
    $config->set('interface.default', 'test_mail_collector')->save();

    $this->modifier = $this->randomMachineName();
    $this->group = $this->createGroup([
      'type' => 'personal',
      'path' => [
        'alias' => '/' . $this->modifier,
      ],
    ]);
    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $this->group);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $this->container->get('config.factory');
    $config = $configFactory->getEditable('system.mail');
    $config->set('interface.default', $this->oldMailHandler)->save();

    parent::tearDown();
  }

  /**
   * Tests for adding and removing users.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAddExistingUser(): void {
    $this->drupalLogin($this->groupAdmin);
    $username = $this->randomMachineName();
    $group_member = $this->createUser([], $username, FALSE);
    $this->group->addMember($group_member);

    $this->visit('/' . $this->modifier . '/cp/users');
    $this->assertContains('/' . $this->modifier . '/cp/users', $this->getSession()->getCurrentUrl(), "First url check, on " . $this->getSession()->getCurrentUrl());
    $page = $this->getCurrentPage();
    $link = $page->findLink('+ Add a member');
    $this->assertContains('/' . $this->modifier . '/cp/users/add', $link->getAttribute('href'), "Add link is not in the vsite.");
    $page->clickLink('+ Add a member');
    $this->assertSession()->waitForElement('css', '#drupal-modal--content');
    $page->find('css', '#existing-member-fieldset summary.seven-details__summary')->click();
    $page->fillField('member-entity', substr($username, 0, 3));
    $this->assertSession()->waitOnAutocomplete();
    $this->assertSession()->responseContains($username);
    $this->getSession()->getPage()->find('css', 'ul.ui-autocomplete li:first-child')->click();

    $page->selectFieldOption('role', 'personal-member');
    $page->pressButton("Save");
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertContains('/' . $this->modifier . '/cp/users', $this->getSession()->getCurrentUrl(), "Not on the correct page, on " . $this->getSession()->getCurrentUrl());
    $this->assertTrue($page->hasContent($username), "Username $username not found on page.");

    $remove = $page->find('xpath', '//tr/td[contains(.,"' . $username . '")]/following-sibling::td/a[contains(.,"Remove")]');
    $this->assertNotNull($remove, "Remove link for $username not found.");
    $remove->click();
    $this->assertSession()->waitForElement('css', '#drupal-modal--content');
    $page->pressButton('Confirm');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertTrue($page->hasContent('Member ' . $username . ' has been removed from ' . $this->group->label()), "Username $username has not removed.");
  }

  /**
   * Tests for adding a user new to the site.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   * @throws \Behat\Mink\Exception\DriverException
   */
  public function testNewUser(): void {
    $settings = $this->configFactory->getEditable('cp_users.settings');

    $existing_mail = $this->randomMachineName() . '@mail.com';
    $existing_username = $this->randomMachineName();
    $existing_mail_account = $this->createUser();
    $existing_mail_account->setEmail($existing_mail)->save();
    $this->createUser([], $existing_username);

    $this->assertFalse($settings->get('disable_user_creation'), "User creation setting is wrong.");

    $this->drupalLogin($this->groupAdmin);

    $this->visit('/' . $this->modifier . '/cp/users');
    $this->assertContains('/' . $this->modifier . '/cp/users', $this->getSession()->getCurrentUrl());
    $page = $this->getCurrentPage();
    $page->clickLink('+ Add a member');
    $this->assertSession()->waitForElement('css', '#drupal-modal--content');
    $page->find('css', '#new-user-fieldset summary.seven-details__summary')->click();

    // Negative tests.
    $page->fillField('Username', $existing_username);
    $page->fillField('E-mail Address', $this->randomMachineName() . '@mail.com');
    $page->selectFieldOption('role', 'personal-member');
    $page->pressButton('Save');
    $this->waitForAjaxToFinish();
    $this->assertNotNull($this->getSession()->getPage()->find('css', '.form-item-username.form-item--error'));

    $page->fillField('Username', $this->randomMachineName());
    $page->fillField('E-mail Address', $existing_mail);
    $page->selectFieldOption('role', 'personal-member');
    $page->pressButton('Save');
    $this->waitForAjaxToFinish();
    $this->assertNotNull($this->getSession()->getPage()->find('css', '.form-item-email.form-item--error'));

    // Positive tests.
    $page->fillField('First Name', 'test');
    $page->fillField('Last Name', 'user');
    $page->fillField('Username', 'test-user');
    $page->fillField('E-mail Address', 'test-user@localhost.com');
    $page->selectFieldOption('role', 'personal-member');
    $page->pressButton('Save');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertContains('/' . $this->modifier . '/cp/users', $this->getSession()->getCurrentUrl(), "Not on correct page after redirect.");
    $this->assertTrue($page->hasContent('test-user'), "Test-user not added to site.");

    $settings->set('disable_user_creation', 1);
    $settings->save();

    $page->clickLink('+ Add a member');
    $this->assertSession()->waitForElement('css', '#drupal-modal--content');
    $this->assertSession()->linkNotExists('Add New User', "Add New User is still on page.");
    $page->find('css', '#drupal-modal')->click();

    // Cleanup.
    $user = \user_load_by_name('test-user');
    if ($user) {
      $this->markEntityForCleanup($user);
    }

    $settings->set('disable_user_creation', 0);
    $settings->save();
  }

  /**
   * Checks user access to change vsite ownership.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testChangeOwnership(): void {
    // Setup.
    $member = $this->createUser();
    $this->group->addMember($member);

    // Negative tests.
    $this->drupalLogin($this->groupAdmin);
    $this->visitViaVsite('cp/users', $this->group);

    /** @var \Behat\Mink\Element\NodeElement|null $change_owner_link */
    $change_owner_link = $this->getSession()->getPage()->find('css', "a[href=\"/{$this->modifier}/cp/users/owner?user={$member->id()}\"]");
    $this->assertNull($change_owner_link);
    $this->visitViaVsite('cp/users/owner', $this->group);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogout();

    // Config changes for positive tests.
    $this->group->setOwner($this->groupAdmin)->save();

    // Positive tests.
    $this->drupalLogin($this->groupAdmin);
    $this->visitViaVsite('cp/users', $this->group);

    /** @var \Behat\Mink\Element\NodeElement|null $change_owner_link */
    $change_owner_link = $this->getSession()->getPage()->find('css', "a[href=\"/{$this->modifier}/cp/users/owner?user={$member->id()}\"]");
    $this->assertNotNull($change_owner_link);
    $this->visitViaVsite('cp/users/owner', $this->group);
    $this->assertSession()->statusCodeEquals(200);
  }

}
