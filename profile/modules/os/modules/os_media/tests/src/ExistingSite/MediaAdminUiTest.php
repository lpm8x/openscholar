<?php

namespace Drupal\Tests\os_media\ExistingSite;

use Drupal\file\FileInterface;
use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * MediaAdminUITest.
 *
 * @group functional
 * @group os
 */
class MediaAdminUiTest extends OsExistingSiteTestBase {

  /**
   * Files created in tests.
   *
   * @var \Drupal\file\FileInterface[]
   */
  protected $testFiles;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // User setup.
    $group_admin = $this->createUser();
    $this->addGroupAdmin($group_admin, $this->group);

    // Media setup.
    $pdf_media = $this->createMedia([
      'created' => strtotime('01-01-2020'),
    ], 'pdf');
    $this->group->addContent($pdf_media, 'group_entity:media');
    $tar_media = $this->createMedia([
      'created' => strtotime('02-01-2020'),
      'bundle' => [
        'target_id' => 'executable',
      ],
    ], 'tar');
    $this->group->addContent($tar_media, 'group_entity:media');
    $image_media = $this->createMediaImage([
      'created' => strtotime('03-01-2020'),
    ]);
    $this->group->addContent($image_media, 'group_entity:media');

    // Files setup.
    $this->testFiles = [
      $pdf_media->get('field_media_file')->entity,
      $tar_media->get('field_media_file')->entity,
      $image_media->get('field_media_image')->entity,
    ];

    // Node setup.
    $faq = $this->createNode([
      'type' => 'faq',
      'title' => "Witch's Cauldron",
      'field_attached_media' => [
        'target_id' => $pdf_media->id(),
      ],
    ]);
    $this->group->addContent($faq, 'group_node:faq');
    $presentation = $this->createNode([
      'type' => 'presentation',
      'title' => "Witch's Cauldron",
      'field_presentation_slides' => [
        'target_id' => $pdf_media->id(),
      ],
    ]);
    $this->group->addContent($presentation, 'group_node:presentation');
    $software_project = $this->createNode([
      'type' => 'software_project',
      'title' => 'Horseshoe Overlook',
    ]);
    $this->group->addContent($software_project, 'group_node:software_project');
    $software_release = $this->createNode([
      'type' => 'software_release',
      'field_software_package' => [
        'target_id' => $tar_media->id(),
      ],
      'field_software_project' => [
        'target_id' => $software_project->id(),
      ],
      'field_software_version' => 'Chapter 1',
    ]);
    $this->group->addContent($software_release, 'group_node:software_release');

    // Publication setup.
    $reference1 = $this->createReference([
      'html_title' => "Reverend Swanson's Bible",
      'field_attach_files' => [
        'target_id' => $pdf_media->id(),
      ],
    ]);
    $this->group->addContent($reference1, 'group_entity:bibcite_reference');
    $reference2 = $this->createReference([
      'html_title' => "Adler's Ranch",
      'field_attach_files' => [
        'target_id' => $image_media->id(),
      ],
    ]);
    $this->group->addContent($reference2, 'group_entity:bibcite_reference');

    $this->drupalLogin($group_admin);
  }

  /**
   * Tests altered media type filter.
   *
   * @covers ::os_media_form_views_exposed_form_alter
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testAlteredTypeFilter(): void {
    $this->visitViaVsite('cp/content/browse/files', $this->group);

    $this->assertSession()->elementExists('css', 'select[id="edit-bundle"] option[value="audio"]');
    $this->assertSession()->elementNotExists('css', 'select[id="edit-bundle"] option[value="remote"]');
    $this->assertSession()->elementNotExists('css', 'select[id="edit-bundle"] option[value="video"]');
  }

  /**
   * Tests "used in" filter.
   *
   * @covers \Drupal\os_media\Plugin\views\filter\MediaUsageFilter
   * @covers ::os_media_preprocess_views_view_field
   * @covers ::os_media_media_admin_ui_filter_submit_handler
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testMediaUsage(): void {
    $this->visitViaVsite('cp/content/browse/files', $this->group);
    $media_results_selector = '.view-id-os_media.view-display-id-page_1 table tbody tr';

    // Check if all media usages appear if not filtered by "used in".
    /** @var \Behat\Mink\Element\NodeElement[] $rows */
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertCount(3, $rows);

    $this->assertEquals("Witch's Cauldron, Witch's Cauldron, Reverend Swanson's Bible", $rows[0]->find('css', 'td.views-field-nothing-4')->getText());
    $this->assertEquals('Horseshoe Overlook Chapter 1', $rows[1]->find('css', 'td.views-field-nothing-4')->getText());
    $this->assertEquals("Adler's Ranch", $rows[2]->find('css', 'td.views-field-nothing-4')->getText());

    // Check if "used in" filter is working.
    // Check if only the content matching the "used in" value are only shown.
    // Assert node usages.
    $this->getSession()->getPage()->fillField('os_media_media_usage_filter', 'itch');
    $this->getSession()->getPage()->pressButton('Apply');

    /** @var \Behat\Mink\Element\NodeElement[] $rows */
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertCount(1, $rows);

    $this->assertEquals("Witch's Cauldron, Witch's Cauldron", $rows[0]->find('css', 'td.views-field-nothing-4')->getText());

    $this->getSession()->getPage()->fillField('os_media_media_usage_filter', 'Horse');
    $this->getSession()->getPage()->pressButton('Apply');

    /** @var \Behat\Mink\Element\NodeElement[] $rows */
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertCount(1, $rows);

    $this->assertEquals('Horseshoe Overlook Chapter 1', $rows[0]->find('css', 'td.views-field-nothing-4')->getText());

    // Assert publication usage.
    $this->getSession()->getPage()->fillField('os_media_media_usage_filter', 'Swan');
    $this->getSession()->getPage()->pressButton('Apply');

    /** @var \Behat\Mink\Element\NodeElement[] $rows */
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertCount(1, $rows);

    $this->assertEquals("Reverend Swanson's Bible", $rows[0]->find('css', 'td.views-field-nothing-4')->getText());
  }

  /**
   * @covers \Drupal\os_media\Plugin\views\field\MediaFileName
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testSortableFileName(): void {
    $this->visitViaVsite('cp/content/browse/files', $this->group);
    $media_results_selector = '.view-id-os_media.view-display-id-page_1 table tbody tr';
    $filename_data_selector = 'td.views-field-os-media-file-name';

    // Setup.
    $test_filenames_asc = $this->testFiles;
    $test_filenames_desc = $this->testFiles;

    usort($test_filenames_asc, static function (FileInterface $a, FileInterface $b) {
      return strcmp($a->getFilename(), $b->getFilename());
    });
    usort($test_filenames_desc, static function (FileInterface $a, FileInterface $b) {
      return -(strcmp($a->getFilename(), $b->getFilename()));
    });

    // Test ascending sort.
    $this->getSession()->getPage()->clickLink('Original Name');
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertEqual($rows[0]->find('css', $filename_data_selector)->getText(), $test_filenames_asc[0]->getFilename());
    $this->assertEqual($rows[1]->find('css', $filename_data_selector)->getText(), $test_filenames_asc[1]->getFilename());
    $this->assertEqual($rows[2]->find('css', $filename_data_selector)->getText(), $test_filenames_asc[2]->getFilename());

    // Test descending sort.
    $this->getSession()->getPage()->clickLink('Original Name');
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertEqual($rows[0]->find('css', $filename_data_selector)->getText(), $test_filenames_desc[0]->getFilename());
    $this->assertEqual($rows[1]->find('css', $filename_data_selector)->getText(), $test_filenames_desc[1]->getFilename());
    $this->assertEqual($rows[2]->find('css', $filename_data_selector)->getText(), $test_filenames_desc[2]->getFilename());
  }

  /**
   * @covers \Drupal\os_media\Plugin\views\field\MediaFileSize
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  public function testSortableFileSize(): void {
    $this->visitViaVsite('cp/content/browse/files', $this->group);
    $media_results_selector = '.view-id-os_media.view-display-id-page_1 table tbody tr';
    $file_size_data_selector = 'td.views-field-os-media-file-size';

    // Setup.
    $test_file_sizes_asc = $this->testFiles;
    $test_file_sizes_desc = $this->testFiles;

    usort($test_file_sizes_asc, static function (FileInterface $a, FileInterface $b) {
      return ($a->getSize() > $b->getSize()) ? 1 : -1;
    });
    usort($test_file_sizes_desc, static function (FileInterface $a, FileInterface $b) {
      return ($a->getSize() > $b->getSize()) ? -1 : 1;
    });

    // Test ascending sort.
    $this->getSession()->getPage()->clickLink('Size');
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertEqual($rows[0]->find('css', $file_size_data_selector)->getText(), format_size($test_file_sizes_asc[0]->getSize()));
    $this->assertEqual($rows[1]->find('css', $file_size_data_selector)->getText(), format_size($test_file_sizes_asc[1]->getSize()));
    $this->assertEqual($rows[2]->find('css', $file_size_data_selector)->getText(), format_size($test_file_sizes_asc[2]->getSize()));

    // Test descending sort.
    $this->getSession()->getPage()->clickLink('Size');
    $rows = $this->getSession()->getPage()->findAll('css', $media_results_selector);
    $this->assertEqual($rows[0]->find('css', $file_size_data_selector)->getText(), format_size($test_file_sizes_desc[0]->getSize()));
    $this->assertEqual($rows[1]->find('css', $file_size_data_selector)->getText(), format_size($test_file_sizes_desc[1]->getSize()));
    $this->assertEqual($rows[2]->find('css', $file_size_data_selector)->getText(), format_size($test_file_sizes_desc[2]->getSize()));
  }

}
