<?php

namespace Drupal\Tests\os_publications\ExistingSite;

/**
 * PublicationsCreateTest.
 *
 * @group kernel
 * @group publications
 */
class PublicationsCreateTest extends TestBase {

  /**
   * Tests whether custom title is automatically set.
   *
   * @covers ::os_publications_bibcite_reference_presave
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSetTitleFirstLetterExclPrep() {
    $reference = $this->createReference([
      'html_title' => 'The Velvet Underground',
    ]);

    $this->assertEquals('V', $reference->get('title_first_char_excl_prep')->getValue()[0]['value']);
  }

}
