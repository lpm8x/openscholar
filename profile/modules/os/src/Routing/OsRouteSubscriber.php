<?php

namespace Drupal\os\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to dynamic route events.
 */
class OsRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('system.403');

    if ($route) {
      $route->setDefault('_controller', '\Drupal\os\Controller\Http403Controller::render');
    }

    if ($route = $collection->get('entity.node.version_history')) {
      $route->setDefault('_controller', '\Drupal\os\Controller\OsNodeController::revisionOverview');
    }

    foreach ($collection->all() as $route) {
      $route->setRequirement('_os_private_vsite_guard', 'TRUE');
    }
  }

}
