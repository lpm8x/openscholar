<?php

namespace Drupal\bibcite_revision_delete\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Class BibciteRevisionDeleteTest.
 *
 * @group kernel
 * @group other-1
 * @covers \Drupal\bibcite_revision_delete\BibciteRevisionDelete
 */
class BibciteRevisionDeleteTest extends OsExistingSiteTestBase {

  /**
   * Test canditates revisions deletion.
   */
  public function testCandidatesRevisionsDelete() {
    // Create 15 revisions.
    $reference = $this->createReference();
    $this->group->addContent($reference, 'group_entity:bibcite_reference');
    for ($i = 0; $i < 15; $i++) {
      $reference->set('html_title', $this->randomMachineName());
      $reference->setNewRevision();
      $reference->save();
    }
    $service = $this->container->get('bibcite_revision_delete');
    $bibcites = $service->getCandidatesBibcites();
    $this->assertCount(1, $bibcites);
    $bibcite_revisions = $service->getCandidatesRevisions();
    $this->assertCount(5, $bibcite_revisions);

    bibcite_revision_delete_cron();
    $bibcite_revisions = $service->getCandidatesRevisions();
    $this->assertCount(0, $bibcite_revisions);
    $storage = $this->container->get('entity_type.manager')->getStorage($reference->getEntityTypeId());
    $revision_ids = $storage->getQuery()
      ->allRevisions()
      ->condition($reference->getEntityType()->getKey('id'), $reference->id())
      ->execute();
    // Count current version too.
    $this->assertCount(11, $revision_ids);
  }

}
