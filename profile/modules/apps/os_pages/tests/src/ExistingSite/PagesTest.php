<?php

namespace Drupal\Tests\os_pages\ExistingSite;

/**
 * Tests book pages.
 *
 * @group kernel
 * @group other-2
 */
class PagesTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->groupAdmin = $this->createUser();
    $this->addGroupAdmin($this->groupAdmin, $this->group);
  }

  /**
   * Tests alias.
   */
  public function testAlias() {
    /** @var \Drupal\Core\Path\AliasManagerInterface $alias_manager */
    $alias_manager = $this->container->get('path.alias_manager');

    /** @var \Drupal\node\NodeInterface $book */
    $book = $this->createBookPage([
      'title' => 'First book',
    ]);

    $this->assertEquals($alias_manager->getAliasByPath("/node/{$book->id()}"), '/first-book');
  }

  /**
   * Tests book outline.
   */
  public function testOutline() {
    /** @var \Drupal\book\BookOutlineStorageInterface $book_outline_storage */
    $book_outline_storage = $this->container->get('book.outline_storage');

    /** @var \Drupal\node\NodeInterface $book */
    $book = $this->createBookPage([
      'title' => 'First book manager',
    ]);

    /** @var \Drupal\node\NodeInterface $page1 */
    $page1 = $this->createBookPage([], $book->id());

    /** @var \Drupal\node\NodeInterface $page11 */
    $page11 = $this->createBookPage([], $book->id(), $page1->id());

    /** @var \Drupal\node\NodeInterface $page2 */
    $page2 = $this->createBookPage([], $book->id());

    // Assert book has no parent and has correct number of children.
    $this->assertEquals(0, $book->book['pid']);
    $this->assertCount(2, $book_outline_storage->loadBookChildren($book->id()));

    // Assert page1 is placed correctly in the hierarchy.
    $this->assertEquals($book->id(), $page1->book['pid']);
    $this->assertEquals($book->id(), $page1->book['bid']);
    $this->assertCount(1, $book_outline_storage->loadBookChildren($page1->id()));

    // Assert page11 is placed correctly in the hierarchy.
    $this->assertEquals($page1->id(), $page11->book['pid']);
    $this->assertEquals($book->id(), $page11->book['bid']);
    $this->assertCount(0, $book_outline_storage->loadBookChildren($page11->id()));

    // Assert page2 is placed correctly in the hierarchy.
    $this->assertEquals($book->id(), $page2->book['pid']);
    $this->assertEquals($book->id(), $page2->book['bid']);
    $this->assertCount(0, $book_outline_storage->loadBookChildren($page2->id()));
  }

}
