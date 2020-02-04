<?php

namespace Drupal\os_search;

use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\vsite\Plugin\AppManager;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Helper class for search.
 */
class OsSearchAppAccess implements AccessInterface {

  /**
   * Request Stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * App manager.
   *
   * @var \Drupal\vsite\Plugin\AppManager
   */
  protected $appManager;

  /**
   * Class constructor.
   */
  public function __construct(AppManager $app_manager, RequestStack $request_stack) {
    $this->appManager = $app_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * Method checks to see whether this access check should apply.
   */
  public function appliesTo() {
    return '_global_app_access';
  }

  /**
   * Checks access for page /browse/{app}.
   */
  public function access() {
    // Return AccessResult::forbidden();
    $app_requested = $this->requestStack->getCurrentRequest()->attributes->get('app');
    /** @var \Drupal\vsite\AppInterface[] $apps */
    $apps = $this->appManager->getDefinitions();
    if (isset($apps[$app_requested]['id']) && $apps[$app_requested]['id'] == $app_requested) {
      return AccessResult::allowed();
    }

    // Return 403 Access Denied page.
    return AccessResult::forbidden();
  }

}
