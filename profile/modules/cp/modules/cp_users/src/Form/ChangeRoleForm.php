<?php

namespace Drupal\cp_users\Form;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\cp_users\CpRolesHelperInterface;
use Drupal\group\Entity\GroupRoleInterface;
use Drupal\vsite\Plugin\VsiteContextManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\cp_users\Access\ChangeOwnershipAccessCheck;

/**
 * Changes role for a member.
 */
final class ChangeRoleForm extends FormBase {

  /**
   * Vsite context manager.
   *
   * @var \Drupal\vsite\Plugin\VsiteContextManagerInterface
   */
  protected $vsiteContextManager;

  /**
   * Active group.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $activeGroup;

  /**
   * Cp Roles Helper service.
   *
   * @var \Drupal\cp_users\CpRolesHelperInterface
   */
  protected $cpRolesHelper;

  /**
   * ChangeOwnershipAccessCheck service.
   *
   * @var \Drupal\cp_users\Access\ChangeOwnershipAccessCheck
   */
  protected $changeOwnershipAccessChecker;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('vsite.context_manager'),
      $container->get('cp_users.cp_roles_helper'),
      $container->get('cp_users.change_ownership_access_check'),
      $container->get('current_user')
    );
  }

  /**
   * Creates a new ChangeRoleForm object.
   *
   * @param \Drupal\vsite\Plugin\VsiteContextManagerInterface $vsite_context_manager
   *   Vsite context manager.
   * @param \Drupal\cp_users\CpRolesHelperInterface $cp_roles_helper
   *   Cp roles helper instacne.
   * @param \Drupal\cp_users\Access\ChangeOwnershipAccessCheck $change_ownership_access_check
   *   Change Ownership access checker.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   */
  public function __construct(VsiteContextManagerInterface $vsite_context_manager, CpRolesHelperInterface $cp_roles_helper, ChangeOwnershipAccessCheck $change_ownership_access_check, AccountInterface $current_user) {
    $this->vsiteContextManager = $vsite_context_manager;
    $this->cpRolesHelper = $cp_roles_helper;
    $this->activeGroup = $vsite_context_manager->getActiveVsite();
    $this->changeOwnershipAccessChecker = $change_ownership_access_check;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cp_users_change_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {
    $current_user = $this->currentUser;
    /** @var \Drupal\group\Entity\GroupRoleInterface[] $roles */
    $roles = $this->activeGroup->getGroupType()->getRoles();
    /** @var \Drupal\group\GroupMembership $group_membership */
    $group_membership = $this->activeGroup->getMember($user);
    /** @var \Drupal\group\Entity\GroupRoleInterface[] $existing_roles */
    $existing_roles = $group_membership->getRoles();
    // It is a requirement that a member can have only one role, therefore we
    // can safely retrieve the first role.
    $existing_role = \reset($existing_roles);
    $options = [];

    $form_state->addBuildInfo('account', $user);

    // Remove unwanted roles for vsites from the options.
    /** @var string[] $non_configurable_roles */
    $non_configurable_roles = $this->cpRolesHelper->getNonConfigurableGroupRoles($this->activeGroup);

    $cpRolesHelper = $this->cpRolesHelper;
    $group_type = $this->activeGroup->getGroupType();
    /** @var \Drupal\group\Entity\GroupRoleInterface[] $allowed_roles */
    $allowed_roles = array_filter($roles, static function (GroupRoleInterface $role) use ($non_configurable_roles, $cpRolesHelper, $current_user, $group_type) {
      return !\in_array($role->id(), $non_configurable_roles, TRUE) && !$role->isInternal() && $cpRolesHelper->accountHasAccessToRestrictedRole($current_user, $group_type, $role->id());
    });

    foreach ($allowed_roles as $role) {
      $options[$role->id()] = cp_users_render_cp_role_label($role);
    }

    $form['roles'] = [
      '#type' => 'radios',
      '#title' => $this->t('Roles'),
      '#options' => $options,
      '#default_value' => $existing_role->id(),
    ];

    $can_change_ownership = ($this->changeOwnershipAccessChecker->access($current_user) instanceof AccessResultAllowed);
    $form['site_owner'] = [
      '#type' => 'checkbox',
      '#access' => $can_change_ownership,
      '#title' => $this->t('Set as site owner'),
      '#description' => $this->t('Make') . " " . $user->getAccountName() . " " . $this->t("the site owner."),
      '#weight' => 100,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account = $form_state->getBuildInfo()['account'];
    /** @var \Drupal\group\GroupMembership $group_membership */
    $group_membership = $this->activeGroup->getMember($account);
    /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
    $group_content = $group_membership->getGroupContent();

    $group_content->set('group_roles', [
      'target_id' => $form_state->getValue('roles'),
    ])->save();

    // Change site owner.
    if ($form_state->getValue('site_owner')) {
      $this->activeGroup->setOwnerId($account->get('uid')->value);
      $this->activeGroup->save();
    }

    $this->messenger()->addMessage($this->t('Role successfully updated.'));

    $form_state->setRedirect('cp.users');
  }

}
