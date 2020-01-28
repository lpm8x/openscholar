<?php

namespace Drupal\Tests\os_office_embed\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class OsOfficeEmbedFormatterTest.
 *
 * @group kernel
 * @group other-2
 * @covers \Drupal\os_office_embed\Plugin\Field\FieldFormatter\OsOfficeEmbedFormatter
 */
class OsOfficeEmbedFormatterTest extends OsExistingSiteTestBase {

  /**
   * Testing document.
   *
   * @var \Drupal\media\Entity\Media
   */
  protected $document;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->document = $this->createMedia();
  }

  /**
   * Test build embed office values.
   */
  public function testBuildEmbedOffice() {
    $media_files = $this->document->get('field_media_file');
    $entities = $media_files->referencedEntities();
    $build = $media_files->view([
      'type' => 'os_office_embed',
    ]);
    $this->assertSame('os_office_embed', $build[0]['#theme']);
    $this->assertSame($entities[0]->getFileName(), $build[0]['#filename']);
  }

  /**
   * Test build embed office values with pdf file.
   */
  public function testBuildEmbedOfficePdfFile() {
    // Create pdf file.
    $filename = $this->randomMachineName() . '.pdf';
    $file = File::create([
      'uri' => 'public://' . $filename,
      'status' => 1,
    ]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);
    // Create media.
    $media = Media::create([
      'name' => [
        'value' => $this->randomMachineName(),
      ],
      'bundle' => [
        'target_id' => 'document',
      ],
      'field_media_file' => [
        'target_id' => $file->id(),
        'display' => 1,
      ],
    ]);
    $media->enforceIsNew();
    $media->save();
    $this->markEntityForCleanup($media);

    $media_files = $media->get('field_media_file');
    $entities = $media_files->referencedEntities();
    $build = $media_files->view([
      'type' => 'os_office_embed',
    ]);
    $this->assertSame('os_office_embed', $build[0]['#theme']);
    $this->assertSame($entities[0]->getFileName(), $build[0]['#filename']);
    $this->assertContains('sites/default/files/' . $filename, $build[0]['#iframe_url']);
    $this->assertNotContains('https://view.officeapps.live.com/op/embed.aspx', $build[0]['#iframe_url']);
  }

}
