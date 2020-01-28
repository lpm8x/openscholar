<?php

namespace Drupal\Tests\os_search\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * OsSearchHelperTest Contains methods required for search customizations.
 *
 * @group kernel
 * @group os-search
 * @covers \Drupal\os_search\OsSearchHelper
 */
class OsSearchHelperTest extends OsExistingSiteTestBase {


  /**
   * Group.
   *
   * @var \Drupal\group\Entity\Group
   */
  protected $entity;

  /**
   * Search helper.
   *
   * @var \Drupal\os_search\OsSearchHelper
   */
  protected $searchHelper;

  /**
   * Search API fields.
   *
   * @var array
   */
  protected $fields;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->searchHelper = $this->container->get('os_search.os_search_helper');
  }

  /**
   * Block creation service test.
   */
  public function testCreateGroupBlockWidget() {
    $this->searchHelper->createGroupBlockWidget($this->group);
    $allowed_fields = $this->searchHelper->getAllowedFacetIds();
    unset($allowed_fields['custom_title']);
    $allowed_fields = array_values($allowed_fields);

    $group_label = $this->group->label();
    $added_widgets = $this->group->getContent('group_entity:block_content');
    $added_widget_labels = [];

    foreach ($added_widgets as $widget) {
      $added_widget_labels[] = str_replace($group_label . ':', '', $widget->getEntity()->label());
    }
<<<<<<< HEAD

    foreach ($allowed_fields as $key => $field) {
      $widget_label[$key] = $group_label . ' | Faceted Search: Filter By ' . $field;
    }
    $widget_label[] = $group_label . ' | Search Sort';
=======
    foreach ($allowed_fields as $key => $field) {
      $widget_label[$key] = $group_label . ' | Search Sort';
      if (strpos($field, 'Sort:') === FALSE) {
        $widget_label[$key] = $group_label . ' | Faceted Search: Filter By ' . $field;
      }
    }
>>>>>>> Issue #12637 - Added changes in the test.
    $added_widget_labels = array_unique($added_widget_labels);

    $this->assertEquals($added_widget_labels, $widget_label);
  }

}
