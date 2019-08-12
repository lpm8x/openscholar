<?php

namespace Drupal\Tests\os_software\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class OsSoftwareProjectReleaseBlocksTest.
 *
 * @group cp
 * @group kernel
 *
 * @package Drupal\Tests\os_software\ExistingSite
 */
class OsSoftwareProjectReleaseBlocksTest extends OsExistingSiteTestBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  protected $projectNode;
  protected $renderer;
  protected $media;
  protected $nodeViewBuilder;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->projectNode = $this->createNode([
      'type' => 'software_project',
    ]);
    $this->media = $this->createMedia([
      'bundle' => [
        'target_id' => 'executable',
      ],
    ], 'binary');
    $this->renderer = $this->container->get('renderer');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->nodeViewBuilder = $this->entityTypeManager
      ->getViewBuilder('node');
  }

  /**
   * Test release block recommended on projects node.
   */
  public function testProjectNodeReleaseBlockRecommended() {
    $this->createNode([
      'type' => 'software_release',
      'field_software_project' => [
        $this->projectNode->id(),
      ],
      'field_software_version' => 'v1.1.2',
      'field_software_package' => [
        $this->media->id(),
      ],
      'field_is_recommended_version' => TRUE,
    ]);
    $render = $this->nodeViewBuilder->view($this->projectNode, 'full');
    /** @var \Drupal\Core\Render\Markup $markup_array */
    $markup = $this->renderer->renderRoot($render);
    $this->assertContains('Recommended Releases', $markup->__toString());
    $this->assertContains('v1.1.2', $markup->__toString());
    $this->assertNotContains('Recent Releases', $markup->__toString());
  }

  /**
   * Test release block recent on projects node.
   */
  public function testProjectNodeReleaseBlockRecent() {
    $this->createNode([
      'type' => 'software_release',
      'field_software_project' => [
        $this->projectNode->id(),
      ],
      'field_software_version' => 'v1.1.1',
      'field_software_package' => [
        $this->media->id(),
      ],
      'field_is_recommended_version' => FALSE,
    ]);
    $render = $this->nodeViewBuilder->view($this->projectNode, 'full');
    /** @var \Drupal\Core\Render\Markup $markup_array */
    $markup = $this->renderer->renderRoot($render);
    $this->assertContains('Recent Releases', $markup->__toString());
    $this->assertContains('v1.1.1', $markup->__toString());
    $this->assertNotContains('Recommended Releases', $markup->__toString());
  }

}
