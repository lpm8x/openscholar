<?php

namespace Drupal\Tests\os_search\Traits;

use Drupal\group\Entity\Group;
use Behat\Mink\Exception\ExpectationException;

/**
 * Search Test helpers.
 */
trait SearchTestTrait {

  /**
   * Count Earth docs.
   *
   * @var int
   */
  protected $earthDocsCount = 0;

  /**
   * Count Venus docs.
   *
   * @var int
   */
  protected $venusDocsCount = 0;

  /**
   * Count Jupiter docs.
   *
   * @var int
   */
  protected $jupiterDocsCount = 0;

  /**
   * An original search API page.
   *
   * @var \Drupal\search_api_page\Entity\SearchApiPage
   */
  protected $originalPage;

  /**
   * Pause the process for short periods between calling.
   *
   * @param int $seconds
   *   Pause for seconds.
   */
  protected function waitForSeconds($seconds = 5) {
    \sleep($seconds);
  }

  /**
   * Helper function to generate grouped content.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group Entity.
   * @param int $each_type_count
   *   Number of entities to be created for each type.
   * @param string $differentiator
   *   To isolate content from others in way (helps asserting correct texts).
   */
  protected function generateContent(Group $group, $each_type_count = 5, $differentiator = 'searchtest') {
    $group_id = $group->id();

    // Reference title always appeards in ucwords format.
    $ref_differentiator = ucwords($differentiator);

    for ($i = 0; $i < $each_type_count; $i++) {
      $j = $i + 1;
      $news = $this->createNode([
        'type' => 'news',
        'title' => "Earth news {$differentiator} Group{$group_id} {$j}",
      ]);

      $group->addContent($news, 'group_node:news');
      $this->markEntityForCleanup($news);
      if ($news) {
        $this->earthDocsCount++;
      }

      $blog = $this->createNode([
        'type' => 'blog',
        'title' => "Venus blog {$differentiator} Group{$group_id} {$j}",
      ]);

      $group->addContent($blog, 'group_node:blog');
      $this->markEntityForCleanup($blog);
      if ($blog) {
        $this->venusDocsCount++;
      }

      $reference = $this->createReference([
        'html_title' => "Jupiter Reference {$ref_differentiator} Group{$group_id} {$j}",
      ]);

      $group->addContent($reference, 'group_entity:bibcite_reference');

      if ($reference) {
        $this->jupiterDocsCount++;
      }
    }
  }

  /**
   * Checks if elastic server started giving results.
   *
   * @param string $differentiator
   *   To isolate content from others in way (helps asserting correct texts).
   * @param int $repeat
   *   Repeat max 10 times to check search status.
   */
  protected function getIndexQueryStatus($differentiator = 'searchtest', $repeat = 1) {
    $this->waitForSeconds();

    $query_builder = $this->container->get('os_search.os_search_query_builder');
    $query = $this->index->query();
    $query->keys($differentiator);
    $query_builder->queryBuilder($query);
    $count = $query->execute()->getResultCount();

    if ($count < 27 && $repeat <= 10) {
      $this->getIndexQueryStatus($differentiator, ++$repeat);
    }
    elseif ($count < 27 && $repeat > 10) {
      throw new ExpectationException('No response from elastic server or query is not correct.', $this->getSession());
    }
  }

  /**
   * Created duplicate search index to be used by tests.
   */
  protected function setUpSearch() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    $index_storage = $entity_type_manager->getStorage('search_api_index');
    $org_index = $index_storage->load('os_search_index');
    $index_id = strtolower($this->randomMachineName());

    // Create duplicate index to remove fragility when
    // simultaneously two or more search jobs are running.
    $this->index = $org_index->createDuplicate();
    $this->index->set('id', "os_test_index_{$index_id}");
    $this->index->save();

    // Change search_api_page to use duplicated index.
    $search_page_storage = $entity_type_manager->getStorage('search_api_page');
    $this->originalPage = $search_page_storage->load('search');
    $this->originalPage->set('index', $this->index->id());
    $this->originalPage->save();

    $this->markEntityForCleanup($this->index);
  }

}
