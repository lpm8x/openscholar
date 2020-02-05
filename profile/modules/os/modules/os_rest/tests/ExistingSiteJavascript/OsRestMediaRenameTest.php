<?php

namespace Drupal\os_rest\ExistingSiteJavascript;

use Drupal\file\Entity\File;
use Drupal\Tests\openscholar\ExistingSiteJavascript\OsExistingSiteJavascriptTestBase;

/**
 * Tests the uploaded media renaming.
 *
 * @group functional-javascript
 * @group os
 */
class OsRestMediaRenameTest extends OsExistingSiteJavascriptTestBase {

  /**
   * The user we're logging in as.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->user = $this->createUser();
    $this->addGroupAdmin($this->user, $this->group);
    $this->drupalLogin($this->user);
  }

  /**
   * Edit media filename and check file move.
   */
  public function testEditMediaRenameFilename() {
    $web_assert = $this->assertSession();
    $media = $this->createMedia([], 'image');
    $this->group->addContent($media, 'group_entity:media');
    $blog = $this->createNode([
      'type' => 'blog',
      'field_attached_media' => $media,
    ]);
    $this->group->addContent($blog, 'group_node:blog');
    $file_id = $media->get('field_media_file')->get(0)->get('target_id')->getValue();
    $file = File::load($file_id);
    $this->placeFileToGroupDir($file, $this->group);
    $original_filename = $file->getFilename();
    $purl = $this->container->get('vsite.context_manager')->getActivePurl();
    $this->assertFileExists('public://' . $purl . '/files/' . $original_filename);

    $this->visitViaVsite('node/' . $blog->id() . '/edit', $this->group);
    $page = $this->getCurrentPage();
    $web_assert->waitForElementVisible('css', 'ul.media-actions li.edit a');
    $page->find('css', 'ul.media-actions li.edit a')->click();
    $page->find('named', ['fieldset', 'Advanced'])->click();
    $new_filename = $this->randomMachineName() . '.png';
    $page->findById('fe-filepath')->setValue($new_filename);
    $page->find('css', '.control button')->press();
    $this->waitForDialogClose();
    $this->assertFileExists('public://' . $purl . '/files/' . $new_filename);
    $web_assert->pageTextContains($new_filename);
  }

}
