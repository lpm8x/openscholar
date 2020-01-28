<?php

namespace Drupal\Tests\os_classes\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;
use Drupal\views\Views;

/**
 * Class ClassesOrderTest.
 *
 * @group kernel
 * @group other-2
 *
 * @package Drupal\Tests\os_classes\ExistingSite
 */
class ClassesOrderTest extends OsExistingSiteTestBase {

  /**
   * Test semester and year special order.
   */
  public function testClassesPageSemesterAndYearOrder() {
    $this->container->get('vsite.context_manager')->activateVsite($this->group);

    // Create nodes with special expected order: 2, 1, 4, 3, 6, 5, 7.
    $class1 = $this->createNode([
      'type' => 'class',
      'field_year_offered' => 2010,
    ]);
    $this->group->addContent($class1, "group_node:class");
    $class2 = $this->createNode([
      'type' => 'class',
      'field_year_offered' => 2011,
    ]);
    $this->group->addContent($class2, "group_node:class");
    $class3 = $this->createNode([
      'type' => 'class',
      'field_semester' => 'fall',
      'field_year_offered' => 2010,
    ]);
    $this->group->addContent($class3, "group_node:class");
    $class4 = $this->createNode([
      'type' => 'class',
      'field_semester' => 'winter',
      'field_year_offered' => 2010,
    ]);
    $this->group->addContent($class4, "group_node:class");
    $class5 = $this->createNode([
      'type' => 'class',
      'field_semester' => 'spring',
      'field_year_offered' => 2010,
    ]);
    $this->group->addContent($class5, "group_node:class");
    $class6 = $this->createNode([
      'type' => 'class',
      'field_semester' => 'summer',
      'field_year_offered' => 2010,
    ]);
    $this->group->addContent($class6, "group_node:class");
    $class7 = $this->createNode([
      'type' => 'class',
      'field_year_offered' => 2009,
    ]);
    $this->group->addContent($class7, "group_node:class");

    $view = Views::getView('os_classes');
    $view->setDisplay('page_1');
    $build = $view->render();
    $rows = $build['#rows'][0]['#rows'];
    $this->assertSame($class2->getTitle(), $rows[0]['#node']->getTitle());
    $this->assertSame($class1->getTitle(), $rows[1]['#node']->getTitle());
    $this->assertSame($class4->getTitle(), $rows[2]['#node']->getTitle());
    $this->assertSame($class3->getTitle(), $rows[3]['#node']->getTitle());
    $this->assertSame($class6->getTitle(), $rows[4]['#node']->getTitle());
    $this->assertSame($class5->getTitle(), $rows[5]['#node']->getTitle());
    $this->assertSame($class7->getTitle(), $rows[6]['#node']->getTitle());
  }

}
