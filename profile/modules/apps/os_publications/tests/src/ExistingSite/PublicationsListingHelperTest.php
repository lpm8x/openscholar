<?php

namespace Drupal\Tests\os_publications\ExistingSite;

use Drupal\redirect\Entity\Redirect;

/**
 * LabelHelperTest.
 *
 * @group kernel
 * @group publications-1
 * @coversDefaultClass \Drupal\os_publications\PublicationsListingHelper
 */
class PublicationsListingHelperTest extends TestBase {

  /**
   * Listing helper.
   *
   * @var \Drupal\os_publications\PublicationsListingHelperInterface
   */
  protected $listingHelper;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->listingHelper = $this->container->get('os_publications.listing_helper');
  }

  /**
   * Tests convertToPublicationsListingLabel.
   *
   * @covers ::convertLabel
   */
  public function testConvertToPublicationsListingLabel(): void {
    $this->assertEquals('T', $this->listingHelper->convertLabel('Talk Talk'));
    $this->assertEquals('L', $this->listingHelper->convertLabel('The Lord of the Rings'));
    $this->assertEquals('U', $this->listingHelper->convertLabel('From up on Poppy Hill'));
  }

  /**
   * Tests convertToPublicationsListingAuthorName.
   *
   * @covers ::convertAuthorName
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function testConvertToPublicationsListingAuthorName(): void {
    $contributor = $this->createContributor([
      'last_name' => 'Hollis',
    ]);
    $reference1 = $this->createReference([
      'author' => [
        'target_id' => $contributor->id(),
        'category' => 'primary',
        'role' => 'author',
      ],
    ]);
    $reference2 = $this->createReference();

    $this->assertEquals('H', $this->listingHelper->convertAuthorName($reference1));
    $this->assertEquals('', $this->listingHelper->convertAuthorName($reference2));
  }

  /**
   * Tests setRedirect.
   *
   * @covers ::setRedirect
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSetRedirect(): void {
    $source = $this->randomMachineName();

    $redirect = Redirect::create([
      'redirect_source' => $source,
      'redirect_redirect' => "internal:/$source/author",
      'status_code' => 301,
    ]);
    $redirect->save();

    /** @var \Drupal\redirect\Entity\Redirect $new_redirect */
    $new_redirect = $this->listingHelper->setRedirect($source, "internal:/$source/year");

    /** @var \Drupal\redirect\RedirectRepository $redirect_repository */
    $redirect_repository = $this->container->get('redirect.repository');

    $this->assertNull($redirect_repository->load($redirect->id()));
    $this->assertNotNull($redirect_repository->load($new_redirect->id()));

    /** @var \Drupal\redirect\Entity\Redirect[] $redirects */
    $redirects = $redirect_repository->findBySourcePath($source);

    $this->assertCount(1, $redirects);
    $redirect = reset($redirects);
    $this->assertEquals("internal:/$source/year", $redirect->getRedirect()['uri']);

    $new_redirect = $this->listingHelper->setRedirect($source, "internal:/$source/type");
    $this->assertNull($new_redirect);
  }

}
