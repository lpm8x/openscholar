<?php

namespace Drupal\os_faq\Plugin\App;

use Drupal\vsite\Plugin\AppPluginBase;

/**
 * FAQ app.
 *
 * @App(
 *   title = @Translation("FAQ"),
 *   canDisable = true,
 *   entityType = "node",
 *   bundle = {
 *    "faq"
 *   },
 *   viewsTabs = {
 *     "os_faq" = {
 *       "page_1",
 *     },
 *   },
 *   id = "faq",
 *   contextualRoute = "view.os_faq.page_1"
 * )
 */
class FAQApp extends AppPluginBase {

}
