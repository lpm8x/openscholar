<?php

namespace Drupal\Tests\cp_users\ExistingSite;

use Drupal\group\Entity\GroupRole;
use Drupal\group\Entity\Group;

/**
 * ChangeRoleForm test.
 *
 * @group functional
 * @group cp
 */
class CpUsersChangeRoleFormTest extends CpUsersExistingSiteTestBase {

  /**
   * Cp Roles helper service.
   *
   * @var \Drupal\cp_users\CpRolesHelperInterface
   */
  protected $cpRolesHelper;

  /**
   * Cp Users helper service.
   *
   * @var \Drupal\cp_users\CpUsersHelper
   */
  protected $cpUsersHelper;

  /**
   * Group member.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $member;

  /**
   * Group administrator.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $groupAdmin;

  /**
   * Entity Type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp() {
    parent::setUp();

    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $this->group);
    $this->createRoleForGroup($this->group, [
      'id' => 'cprolechange',
    ]);
    $this->member = $this->createUser();
    $this->group->addMember($this->member);

    $this->drupalLogin($this->groupAdmin);

    $this->cpRolesHelper = $this->container->get('cp_users.cp_roles_helper');
    $this->cpUsersHelper = $this->container->get('cp_users.cp_users_helper');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

  }

  /**
   * Tests change role functionality.
   *
   * @covers \Drupal\cp_users\Form\ChangeRoleForm
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function test(): void {

    $this->visitViaVsite("cp/users/change-role/{$this->member->id()}", $this->group);

    $this->assertSession()->statusCodeEquals(200);

    // Test Non Configurable roles do not appear.
    $non_configurable_roles = $this->cpRolesHelper->getNonConfigurableGroupRoles($this->group);
    foreach ($non_configurable_roles as $role) {
      $this->assertSession()->elementNotExists('css', '#edit-roles-' . $role);
    }

    $this->assertTrue($this->getSession()->getPage()->find('css', '[value="personal-member"]')->isChecked());

    $this->drupalPostForm(NULL, [
      'roles' => "personal-{$this->group->id()}_cprolechange",
    ], 'Save');

    /** @var \Drupal\group\GroupMembership $group_membership */
    $group_membership = $this->group->getMember($this->member);
    $updated_roles = $group_membership->getRoles();
    $this->assertInstanceOf(GroupRole::class, $updated_roles["personal-{$this->group->id()}_cprolechange"]);
  }

  /**
   * Tests change site owner functionality as vsite owner.
   *
   * @covers \Drupal\cp_users\Form\ChangeRoleForm
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testVsiteRoleChangeSiteOwner(): void {

    $web = $this->assertSession();
    // Create vsite owner.
    $vsite_owner = $this->createUser();
    $this->addGroupAdmin($vsite_owner, $this->group);
    $this->group->setOwner($vsite_owner)->save();

    // Test change site owner using site owner.
    $this->drupalLogin($vsite_owner);
    $this->visitViaVsite('cp/users/change-role/' . $this->member->id(), $this->group);
    $web->pageTextContains('Change role');

    $this->drupalPostForm(NULL, [
      'roles' => "personal-administrator",
      'site_owner' => 1,
    ], 'Save');
    $web->pageTextContains('Manage Users');
    $web->elementContains('css', '.messages--status', 'Role successfully updated.');

    $this->group = Group::load($this->group->id());
    $this->assertTrue($this->cpUsersHelper->isVsiteOwner($this->group, $this->member));
    $this->drupalLogout();

  }

  /**
   * Tests change site owner functionality as super admin.
   *
   * @covers \Drupal\cp_users\Form\ChangeRoleForm
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  public function testSuperRoleChangeSiteOwner(): void {

    $web = $this->assertSession();
    // Test change site owner using super admin.
    $super_account = $this->createUser([
      'bypass group access',
    ]);
    $member_1 = $this->createUser();
    $this->group->addMember($member_1);
    $this->drupalLogin($super_account);
    $this->visitViaVsite('cp/users/change-role/' . $member_1->id(), $this->group);
    $web->pageTextContains('Change role');

    $this->drupalPostForm(NULL, [
      'roles' => "personal-administrator",
      'site_owner' => 1,
    ], 'Save');
    $web->pageTextContains('Manage Users');
    $web->elementContains('css', '.messages--status', 'Role successfully updated.');

    $this->entityTypeManager->getStorage('group')->resetCache([$this->group->id()]);
    $this->group = Group::load($this->group->id());
    $this->assertTrue($this->cpUsersHelper->isVsiteOwner($this->group, $member_1));
    $this->drupalLogout();

  }

  /**
   * Tests whether the cp items are in sync with group role.
   *
   * @covers ::cp_users_preprocess_menu
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testCpItemsSync(): void {
    // Negative test.
    $this->drupalLogin($this->member);

    $this->visitViaVsite('', $this->group);
    $this->assertSession()->responseNotContains("{$this->groupAlias}/cp/appearance");

    $this->drupalLogout();

    // Do changes.
    $this->drupalLogin($this->groupAdmin);
    $this->visitViaVsite("cp/users/change-role/{$this->member->id()}", $this->group);
    $this->drupalPostForm(NULL, [
      'roles' => 'personal-administrator',
    ], 'Save');

    // Positive test.
    $this->drupalLogin($this->member);

    $this->visitViaVsite('', $this->group);
    $this->assertSession()->responseContains("{$this->groupAlias}/cp/appearance");
  }

  /**
   * Test Support User not visible for group admin roles.
   */
  public function testSupportUserRole(): void {
    $web = $this->assertSession();
    // Create vsite owner.
    $groupAdmin = $this->createUser();
    $this->addGroupAdmin($groupAdmin, $this->group);

    // Test using group admin.
    $this->drupalLogin($groupAdmin);
    $this->visitViaVsite('cp/users/change-role/' . $this->member->id(), $this->group);
    $web->statusCodeEquals(200);
    $web->pageTextContains('Change role');

    $web->pageTextContains('Manage Users');
    $web->pageTextNotContains('Support User');
    $this->drupalLogout();

    // Test using site owner.
    $siteOwner = $this->createUser(['manage default group roles']);
    $this->addGroupAdmin($siteOwner, $this->group);
    $this->group->setOwner($siteOwner)->save();
    $this->drupalLogin($siteOwner);
    $this->visitViaVsite('cp/users/change-role/' . $this->member->id(), $this->group);
    $web->statusCodeEquals(200);
    $web->pageTextContains('Change role');
    $web->pageTextContains('Support User');
  }

}
