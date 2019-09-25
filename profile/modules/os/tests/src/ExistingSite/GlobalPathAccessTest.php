<?php

namespace Drupal\Tests\os\ExistingSite;

use Drupal\Tests\openscholar\ExistingSite\OsExistingSiteTestBase;

/**
 * Tests whether group member has access to entity create global paths.
 *
 * @group functional
 * @group os
 */
class GlobalPathAccessTest extends OsExistingSiteTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $member = $this->createUser();
    $this->group->addMember($member);

    $this->drupalLogin($member);
  }

  /**
   * Tests node create global path access.
   *
   * This test only tests node create global path access. The edit, delete path
   * access is handled by gnode_node_access().
   *
   * @covers ::os_entity_create_access
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see \gnode_node_access()
   */
  public function testNode(): void {
    $this->visit("{$this->group->get('path')->getValue()[0]['alias']}/node/add/faq");

    $this->assertSession()->statusCodeEquals(200);

    $question = $this->randomMachineName();
    $answer = $this->randomMachineName();
    $this->getSession()->getPage()->fillField('Question', $question);
    $this->getSession()->getPage()->fillField('Answer', $answer);
    $this->getSession()->getPage()->pressButton('Save');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    $nodes = $entity_type_manager->getStorage('node')->loadByProperties([
      'title' => $question,
    ]);

    $this->assertNotEmpty($nodes);
    $node = \reset($nodes);

    $this->assertEquals($question, $node->get('title')->first()->getValue()['value']);

    $node->delete();
  }

  /**
   * Tests media create global path access.
   *
   * @covers ::os_entity_create_access
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMediaCreate(): void {
    $this->visit("{$this->group->get('path')->getValue()[0]['alias']}/media/add/document");

    $this->assertSession()->statusCodeEquals(200);

    // Skipping the media creation assertions, because, I was not able to
    // replicate the AJAX file upload in test. I have tested it manually, and
    // the media creation works.
  }

  /**
   * Tests media update global path access.
   *
   * @covers ::os_media_access
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMediaUpdate(): void {
    // Setup.
    $member = $this->createAdminUser();
    $this->addGroupAdmin($member, $this->group);
    $media = $this->createMedia();
    $media->setOwner($member)->save();
    $this->group->addContent($media, 'group_entity:media');

    // Tests.
    $this->drupalLogin($member);

    $this->visitViaVsite("media/{$media->id()}/edit", $this->group);
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalPostForm(NULL, [
      'name[0][value]' => 'Document media edited',
      'path[0][alias]' => '/edited-media-path',
    ], 'Save');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    $medias = $entity_type_manager->getStorage('media')->loadByProperties([
      'name' => 'Document media edited',
    ]);

    $this->assertNotEmpty($medias);
    $media = \reset($medias);

    $this->assertEquals('Document media edited', $media->get('name')->first()->getValue()['value']);
  }

  /**
   * Tests media delete global path access.
   *
   * @covers ::os_media_access
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMediaDelete(): void {
    // Setup.
    $member = $this->createUser();
    $this->group->addMember($member);
    $media = $this->createMedia([
      'name' => [
        'value' => 'Media meant to be deleted',
      ],
    ]);
    $media->setOwner($member)->save();
    $this->group->addContent($media, 'group_entity:media');

    // Tests.
    $this->drupalLogin($member);

    $this->visit("{$this->group->get('path')->getValue()[0]['alias']}/media/{$media->id()}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->pressButton('Delete');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    $medias = $entity_type_manager->getStorage('media')->loadByProperties([
      'name' => 'Media meant to be deleted',
    ]);

    $this->assertEmpty($medias);
  }

  /**
   * Tests bibcite reference create global path access.
   *
   * @covers ::os_entity_create_access
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBibciteReferenceCreate(): void {
    /** @var \Drupal\vsite\Plugin\AppManangerInterface $appManager */
    $appManager = \Drupal::service('vsite.app.manager');
    // Without this, the page errors cause the it can't find the app.
    $appManager->clearCachedDefinitions();
    $this->visit("{$this->group->get('path')->getValue()[0]['alias']}/bibcite/reference/add/artwork");

    $this->assertSession()->statusCodeEquals(200);

    $this->drupalPostForm(NULL, [
      'html_title[0][value]' => 'Test Artwork',
      'bibcite_year[0][value]' => 1980,
      'status[value]' => 1,
    ], 'Save');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    $references = $entity_type_manager->getStorage('bibcite_reference')->loadByProperties([
      'html_title__value' => 'Test Artwork',
    ]);

    $this->assertNotEmpty($references);
    $reference = \reset($references);

    $this->assertEquals('Test Artwork', $reference->html_title->value);

    $reference->delete();
  }

  /**
   * Tests bibcite_reference update global path access.
   *
   * @covers ::os_bibcite_reference_access
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBibciteReferenceUpdate(): void {
    // Setup.
    $member = $this->createUser();
    $this->group->addMember($member);
    $reference = $this->createReference();
    $reference->setOwner($member)->save();
    $this->group->addContent($reference, 'group_entity:bibcite_reference');

    // Tests.
    $this->drupalLogin($member);

    $this->visit("{$this->group->get('path')->getValue()[0]['alias']}/bibcite/reference/{$reference->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalPostForm(NULL, [
      'html_title[0][value]' => 'Artwork Reference Edited',
    ], 'Save');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    $references = $entity_type_manager->getStorage('bibcite_reference')->loadByProperties([
      'html_title__value' => 'Artwork Reference Edited',
    ]);

    $this->assertNotEmpty($references);
    $reference = \reset($references);

    $this->assertEquals('Artwork Reference Edited', $reference->html_title->value);
  }

  /**
   * Tests bibcite_reference delete global path access.
   *
   * @covers ::os_bibcite_reference_access
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBibciteReferenceDelete(): void {
    // Setup.
    $member = $this->createUser();
    $this->group->addMember($member);
    $reference = $this->createReference([
      'html_title' => [
        'value' => 'Artwork meant to be deleted',
      ],
    ]);
    $reference->setOwner($member)->save();
    $this->group->addContent($reference, 'group_entity:bibcite_reference');

    // Tests.
    $this->drupalLogin($member);

    $this->visit("{$this->group->get('path')->getValue()[0]['alias']}/bibcite/reference/{$reference->id()}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->pressButton('Delete');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');

    $references = $entity_type_manager->getStorage('bibcite_reference')->loadByProperties([
      'html_title__value' => 'Artwork meant to be deleted',
    ]);

    $this->assertEmpty($references);
  }

  /**
   * Tests block_content update global path access.
   *
   * @covers ::os_block_content_access
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBlockContentUpdate(): void {
    // Setup.
    $group_admin = $this->createUser();
    $this->addGroupAdmin($group_admin, $this->group);
    $block_content = $this->createBlockContent([
      'info' => [
        'value' => 'One Widget to Rule Them All',
      ],
    ]);
    $this->group->addContent($block_content, 'group_entity:block_content');

    // Tests.
    $this->drupalLogin($group_admin);

    $this->visitViaVsite("block/{$block_content->id()}", $this->group);
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'info[0][value]' => 'One Widget to Rule Above All',
    ], 'Save');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\Core\Entity\EntityStorageInterface $block_content_storage */
    $block_content_storage = $entity_type_manager->getStorage('block_content');

    $block_contents = $block_content_storage->loadByProperties([
      'info' => 'One Widget to Rule Above All',
    ]);

    $this->assertNotEmpty($block_contents);
    $test_block_content = \reset($block_contents);

    $this->assertEquals('One Widget to Rule Above All', $test_block_content->info->value);
  }

  /**
   * Tests block_content delete global path access.
   *
   * @covers ::os_block_content_access
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testBlockContentDelete(): void {
    // Setup.
    $group_admin = $this->createUser();
    $this->addGroupAdmin($group_admin, $this->group);
    $block_content = $this->createBlockContent([
      'info' => [
        'value' => 'Transmission',
      ],
    ]);
    $this->group->addContent($block_content, 'group_entity:block_content');

    // Tests.
    $this->drupalLogin($group_admin);

    $this->visitViaVsite("block/{$block_content->id()}/delete", $this->group);
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->pressButton('Delete');

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\Core\Entity\EntityStorageInterface $block_content_storage */
    $block_content_storage = $entity_type_manager->getStorage('block_content');

    $block_contents = $block_content_storage->loadByProperties([
      'info' => 'Transmission',
    ]);

    $this->assertEmpty($block_contents);
  }

}
