<?php

namespace Drupal\Tests\cp_import\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class CpImportPublicationsTest.
 *
 * @group kernel
 * @group cp-1
 *
 * @coversDefaultClass \Drupal\cp_import\Helper\CpImportHelper
 */
class CpImportPublicationsTest extends OsExistingSiteTestBase {

  /**
   * CpImport helper service.
   *
   * @var \Drupal\cp_import\Helper\CpImportHelper
   */
  protected $cpImportHelper;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->vsiteContextManager->activateVsite($this->group);
    $this->cpImportHelper = $this->container->get('cp_import.helper');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Tests Saving a Bibtex entry works.
   *
   * @covers ::savePublicationEntity
   * @covers ::mapPublicationHtmlFields
   */
  public function testCpImportHelperSavePublicationBibtex() {

    $storage = $this->entityTypeManager->getStorage('bibcite_reference');
    $title = $this->randomString();

    // Test Negative.
    $pubArr = $storage->loadByProperties(['title' => $title]);
    $this->assertEmpty($pubArr);

    // Prepare data entry array.
    $abstract = 'This paper presents measurements of spectrally';
    $entry = [
      'type' => 'article',
      'journal' => 'Proceeding of the Combustion Institute',
      'title' => $title,
      'volume' => '32',
      'year' => '2009',
      'pages' => '963-970',
      'chapter' => '963',
      'abstract' => $abstract,
      'author' => ['F. Goulay', 'L. Nemes'],
    ];
    $context = [];
    $this->cpImportHelper->savePublicationEntity($entry, 'bibtex', $context);

    $pubArr = $storage->loadByProperties([
      'type' => 'journal_article',
      'title' => $title,
    ]);

    // Test Positive.
    // Assert Saving Bibtex entry worked.
    $this->assertNotEmpty($pubArr);
    $this->assertArrayHasKey('success', $context['results']);
    /** @var \Drupal\bibcite_entity\Entity\Reference $pubEntity */
    $pubEntity = array_values($pubArr)[0];
    $this->markEntityForCleanup($pubEntity);
    // Assert values directly from the loaded entity to be sure.
    $this->assertEquals($title, $pubEntity->get('title')->getValue()[0]['value']);
    $this->assertEquals(32, $pubEntity->get('bibcite_volume')->getValue()[0]['value']);

    // Test Mapping worked.
    $this->assertEquals($title, $pubEntity->get('html_title')->getValue()[0]['value']);
    $this->assertEquals($abstract, $pubEntity->get('html_abstract')->getValue()[0]['value']);
  }

}
