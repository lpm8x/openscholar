<?php

namespace Drupal\os;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the node revision access check service.
 */
class OsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('access_check.node.revision')) {
      $definition = $container->getDefinition('access_check.node.revision');
      $definition->setClass('Drupal\os\Access\OsNodeRevisionAccessCheck');
      $definition->setArguments([
        new Reference('entity_type.manager'),
        new Reference('os.access_helper'),
        new Reference('vsite.context_manager'),
      ]);
    }
  }

}
