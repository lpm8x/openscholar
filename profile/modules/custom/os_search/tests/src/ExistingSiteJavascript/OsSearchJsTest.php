<?php

namespace Drupal\Tests\os_search\ExistingSiteJavascript;

/**
 * OsSearchJsTest.
 *
 * @group functional-javascript
 * @group os-search
 */
class OsSearchJsTest extends SearchJavascriptTestBase {

  /**
   * Tests if search is working.
   */
  public function testSearch(): void {
    $web_assert = $this->assertSession();

    $this->visit('/search');
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $page->fillField('keys', 'jstestsearch');

    // Find and press Search button.
    $preview_button = $page->findButton('Search');
    $preview_button->press();
    $web_assert->statusCodeEquals(200);

    // Assert that page contains results.
    $page = $this->getCurrentPage();
    $this->assertContains('27 results found', $page->getHtml());
  }

  /**
   * Tests if search with group is working.
   */
  public function testSearchByGroup(): void {
    $web_assert = $this->assertSession();

    $this->visitViaVsite('search', $this->group);
    $web_assert->statusCodeEquals(200);

    $page = $this->getCurrentPage();
    $this->assertContains('15 results found', $page->getHtml());

    $this->visitViaVsite('search', $this->anotherGroup);
    $web_assert->statusCodeEquals(200);

    $page = $this->getCurrentPage();
    $this->assertContains('12 results found', $page->getHtml());
  }

  /**
   * Tests if search with post type is working.
   */
  public function testSearchByType(): void {
    $post_type = 'f[0]=custom_search_bundle:blog';

    $web_assert = $this->assertSession();
    $this->visit("/search/jstestsearch?{$post_type}");
    $web_assert->statusCodeEquals(200);

    $page = $this->getCurrentPage();
    $this->assertContains('Current search', $page->getHtml());
    $this->assertSession()->linkByHrefExists('/search/jstestsearch?');
    $this->assertSession()->linkExists('(-)');
    $this->assertContains('9 results found', $page->getHtml());
    $this->assertContains('blog jstestsearch', $page->getHtml());
    $this->assertNotContains('news jstestsearch', $page->getHtml());
    $this->assertNotContains('reference Jstestsearch', $page->getHtml());

    $this->visitViaVsite("search/jstestsearch?{$post_type}", $this->group);
    $this->assertContains('5 results found', $page->getHtml());
    $this->assertContains("Venus blog jstestsearch Group{$this->group->id()} 1", $page->getHtml());
    $this->assertNotContains("Earth news jstestsearch Group{$this->group->id()} 1", $page->getHtml());
    $this->assertNotContains("Jupiter Reference Jstestsearch Group{$this->group->id()} 1", $page->getHtml());

    $year = (int) date('Y', time());
    $month = (string) date('M Y', time());

    $post_type = "f[0]=custom_search_bundle:bibcite_reference&f[1]=custom_date:year-{$year}";
    $this->visitViaVsite("search/Jstestsearch?{$post_type}", $this->anotherGroup);
    $remove_filter = "{$this->anotherGroupAlias}/search/Jstestsearch?f%5B1%5D=custom_date%3Ayear-{$year}";

    $page = $this->getCurrentPage();
    $this->assertSession()->linkExists("{$month} (4)");
    $this->assertSession()->linkByHrefExists($remove_filter);
    $this->assertContains('4 results found', $page->getHtml());
    $this->assertContains("Jupiter Reference Jstestsearch Group{$this->anotherGroup->id()}", $page->getHtml());
    $this->assertNotContains("Earth News jstestsearch", $page->getHtml());
    $this->assertNotContains("Venus Blog jstestsearch", $page->getHtml());
  }

  /**
   * Tests if search sort is working.
   */
  public function testSearchSortsAsc(): void {
    $sort_type = 'sort=title&dir=ASC';

    $web_assert = $this->assertSession();
    $this->visit("/search/Jstestsearch?{$sort_type}");
    $web_assert->statusCodeEquals(200);

    $links = $this->getCurrentPage()
      ->findAll('named', [
        'link',
        'Earth news Searchjstest',
      ]);

    $i = 1;
    $group_id = $this->group->id();
    foreach ($links as $link) {
      if ($i > 5) {
        $i = 1;
        $group_id = $this->anotherGroup->id();
      }

      $this->assertEquals("Earth news Jstestsearch Group{$group_id} {$i}", $link->getText());
      $i++;
    }
  }

}
