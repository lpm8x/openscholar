<?php

namespace Drupal\os_links\Plugin\App;

use Drupal\vsite\Plugin\AppPluginBase;

/**
 * Plugin for the Links App.
 *
 * @App(
 *   title = @Translation("Links"),
 *   canDisable = true,
 *   entityType = "node",
 *   bundle = {
 *     "link",
 *   },
 *   viewsTabs = {
 *     "links" = {
 *       "page_1",
 *     },
 *   },
 *   id = "links"
 * )
 */
class LinksApp extends AppPluginBase {

}
