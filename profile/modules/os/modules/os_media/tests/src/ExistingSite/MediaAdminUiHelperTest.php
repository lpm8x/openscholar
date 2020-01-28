<?php

namespace Drupal\Tests\os_media\ExistingSite;

use Drupal\node\NodeInterface;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * MediaAdminUiHelperTest.
 *
 * @group kernel
 * @group other-1
 * @coversDefaultClass \Drupal\os_media\MediaAdminUiHelper
 */
class MediaAdminUiHelperTest extends OsExistingSiteTestBase {

  /**
   * Media admin UI helper.
   *
   * @var \Drupal\os_media\MediaAdminUiHelper
   */
  protected $mediaAdminUiHelper;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->mediaAdminUiHelper = $this->container->get('os_media.media_admin_ui_helper');
  }

  /**
   * @covers ::getMediaUsageInNodes
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testGetMediaUsageInNodes(): void {
    $pdf_media = $this->createMedia([], 'pdf');
    $tar_media = $this->createMedia([
      'bundle' => [
        'target_id' => 'executable',
      ],
    ], 'tar');
    $non_existing_media_id = INF;
    $non_matching_title = 'Charles';

    // Negative tests.
    // Assert for unpublished node.
    $faq = $this->createNode([
      'type' => 'faq',
      'title' => "Witch's Cauldron",
      'field_attached_media' => [
        'target_id' => $pdf_media->id(),
      ],
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    $usages = $this->mediaAdminUiHelper->getMediaUsageInNodes($pdf_media->id());
    $this->assertEmpty($usages);

    $faq->setPublished(TRUE)->save();

    // Assert non-existing media.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInNodes($non_existing_media_id);
    $this->assertEmpty($usages);

    // Assert non-matching title.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInNodes($pdf_media->id(), $non_matching_title);
    $this->assertEmpty($usages);

    // Positive tests.
    // Assert for field_attached_media.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInNodes($pdf_media->id());
    $this->assertCount(1, $usages);
    $this->assertEqual($usages[0]->id(), $faq->id());

    // Assert for field_attached_media OR field_presentation_slides.
    $presentation = $this->createNode([
      'type' => 'presentation',
      'title' => "Witch's Cauldron",
      'field_presentation_slides' => [
        'target_id' => $pdf_media->id(),
      ],
    ]);

    $usages = $this->mediaAdminUiHelper->getMediaUsageInNodes($pdf_media->id());
    $this->assertCount(2, $usages);
    $usage_nids = array_map(static function (NodeInterface $node) {
      return $node->id();
    }, $usages);
    $this->assertContains($faq->id(), $usage_nids);
    $this->assertContains($presentation->id(), $usage_nids);

    // Assert for field_software_package.
    $software_project = $this->createNode([
      'type' => 'software_project',
    ]);
    $software_release = $this->createNode([
      'type' => 'software_release',
      'field_software_package' => [
        'target_id' => $tar_media->id(),
      ],
      'field_software_project' => [
        'target_id' => $software_project->id(),
      ],
      'field_software_version' => 'v2',
    ]);

    $usages = $this->mediaAdminUiHelper->getMediaUsageInNodes($tar_media->id());
    $this->assertCount(1, $usages);
    $this->assertEqual($usages[0]->id(), $software_release->id());

    // Assert for title.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInNodes($pdf_media->id(), 'itch');
    $this->assertCount(2, $usages);
    $usage_nids = array_map(static function (NodeInterface $node) {
      return $node->id();
    }, $usages);
    $this->assertContains($faq->id(), $usage_nids);
    $this->assertContains($presentation->id(), $usage_nids);
  }

  /**
   * @covers ::getMediaUsageInPublications
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testGetMediaUsageInPublications(): void {
    $pdf_media = $this->createMedia([], 'pdf');
    $non_existing_media_id = INF;
    $non_matching_title = 'Boah';

    // Negative tests.
    // Assert unpublished publication.
    $reference = $this->createReference([
      'html_title' => 'Cumberland Falls',
      'field_attach_files' => [
        'target_id' => $pdf_media->id(),
      ],
      'status' => 0,
    ]);

    $usages = $this->mediaAdminUiHelper->getMediaUsageInPublications($pdf_media->id());
    $this->assertEmpty($usages);

    $reference->set('status', 1)->save();

    // Assert non-existing media id.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInPublications($non_existing_media_id);
    $this->assertEmpty($usages);

    // Assert non-matching title.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInPublications($pdf_media->id(), $non_matching_title);
    $this->assertEmpty($usages);

    // Positive tests.
    // Assert for field_attach_files.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInPublications($pdf_media->id());
    $this->assertCount(1, $usages);
    $this->assertEqual($usages[0]->id(), $reference->id());

    // Assert with title.
    $usages = $this->mediaAdminUiHelper->getMediaUsageInPublications($pdf_media->id(), 'all');
    $this->assertCount(1, $usages);
    $this->assertEqual($usages[0]->id(), $reference->id());
  }

  /**
   * @covers ::filterNodesUsingMediaByTitle
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFilterNodesUsingMediaByTitle(): void {
    $pdf_media = $this->createMedia([], 'pdf');
    $tar_media = $this->createMedia([
      'bundle' => [
        'target_id' => 'executable',
      ],
    ], 'tar');
    $non_matching_title = 'Charles';

    // Negative tests.
    // Assert unpublished content.
    $faq = $this->createNode([
      'type' => 'faq',
      'title' => "Witch's Cauldron",
      'field_attached_media' => [
        'target_id' => $pdf_media->id(),
      ],
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    $usages = $this->mediaAdminUiHelper->filterNodesUsingMediaByTitle('itch');
    $this->assertEmpty($usages);

    $faq->setPublished(TRUE)->save();

    // Assert non-matching title.
    $usages = $this->mediaAdminUiHelper->filterNodesUsingMediaByTitle($non_matching_title);
    $this->assertEmpty($usages);

    // Positive tests.
    // Assert field_attached_media OR field_presentation_slides.
    $presentation = $this->createNode([
      'type' => 'presentation',
      'title' => "Witch's Cauldron",
      'field_presentation_slides' => [
        'target_id' => $pdf_media->id(),
      ],
    ]);

    $usages = $this->mediaAdminUiHelper->filterNodesUsingMediaByTitle('itch');
    $this->assertCount(2, $usages);
    $usage_nids = array_map(static function (NodeInterface $node) {
      return $node->id();
    }, $usages);
    $this->assertContains($faq->id(), $usage_nids);
    $this->assertContains($presentation->id(), $usage_nids);

    // Assert field_software_package.
    $software_project = $this->createNode([
      'type' => 'software_project',
    ]);
    $software_release = $this->createNode([
      'type' => 'software_release',
      'field_software_package' => [
        'target_id' => $tar_media->id(),
      ],
      'field_software_project' => [
        'target_id' => $software_project->id(),
      ],
      'field_software_version' => "O'Creagh's Run",
    ]);

    $usages = $this->mediaAdminUiHelper->filterNodesUsingMediaByTitle('un');
    $this->assertCount(1, $usages);
    $this->assertEqual($usages[0]->id(), $software_release->id());
  }

  /**
   * @covers ::filterPublicationsUsingMediaByTitle
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFilterPublicationsUsingMediaByTitle(): void {
    $pdf_media = $this->createMedia([], 'pdf');
    $non_matching_title = 'Wolf';

    // Negative tests.
    // Assert unpublished publication.
    $reference = $this->createReference([
      'html_title' => 'Kaer Morhen',
      'field_attach_files' => [
        'target_id' => $pdf_media->id(),
      ],
      'status' => 0,
    ]);

    $usages = $this->mediaAdminUiHelper->filterPublicationsUsingMediaByTitle('hen');
    $this->assertEmpty($usages);

    // Assert non-matching title.
    $reference->set('status', 1)->save();

    $usages = $this->mediaAdminUiHelper->filterPublicationsUsingMediaByTitle($non_matching_title);
    $this->assertEmpty($usages);

    // Positive tests.
    $usages = $this->mediaAdminUiHelper->filterPublicationsUsingMediaByTitle('hen');
    $this->assertCount(1, $usages);
    $this->assertEqual($usages[0]->id(), $reference->id());
  }

}
