<?php

namespace Drupal\Tests\os_media\ExistingSite;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * MediaHelperTest.
 *
 * @group kernel
 * @group other-1
 */
class MediaHelperTest extends ExistingSiteBase {

  /**
   * The Media Helper service.
   *
   * @var \Drupal\os_media\MediaEntityHelper
   */
  protected $mediaHelper;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->mediaHelper = $this->container->get('os_media.media_helper');
  }

  /**
   * Tests Field Mappings.
   */
  public function testGetField(): void {
    $actual = $this->mediaHelper->getField('image');
    $this->assertEquals('field_media_image', $actual);

    $actual = $this->mediaHelper->getField('video');
    $this->assertEquals('field_media_video_file', $actual);

    $actual = $this->mediaHelper->getField('document');
    $this->assertEquals('field_media_file', $actual);

    $this->assertEquals('filename', $this->mediaHelper::FILE_FIELDS[0]);

    $this->assertNotEmpty($this->mediaHelper::ALLOWED_TYPES, 'Returns allowed media types.');
  }

  /**
   * Test Embed type return.
   */
  public function testEmbedType(): void {
    // Test oEmbed return.
    $actual = $this->mediaHelper->getEmbedType('https://www.youtube.com/watch?v=WadTyp3FcgU');
    $this->assertEquals('oembed', $actual);

    // Test Html return.
    $actual = $this->mediaHelper->getEmbedType('<iframe width="560" height="315" src="https://www.youtube.com/embed/WadTyp3FcgU" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>');
    $this->assertEquals('html', $actual);
  }

  /**
   * Test Dimensions function.
   */
  public function testDimensions(): void {
    $html = '<iframe width="560" height="315" src="https://www.youtube.com/embed/WadTyp3FcgU" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    $max['width'] = 'default';
    $max['height'] = 'default';
    $data = $this->mediaHelper->getHtmlDimensions($html, $max);
    $this->assertEquals('560', $data['width']);
    $this->assertEquals('315', $data['height']);

    $resource['width'] = '854';
    $resource['height'] = '640';
    $data = $this->mediaHelper->getOembedDimensions($resource, $max);
    $this->assertEquals('854', $data['width']);
    $this->assertEquals('640', $data['height']);
  }

  /**
   * Tests Iframe data.
   */
  public function testIframeData(): void {
    $value = 'https://www.youtube.com/watch?v=WadTyp3FcgU';
    $max['width'] = '100%';
    $max['height'] = '400';
    $data = $this->mediaHelper->iFrameData($value, $max, NULL);
    $this->assertNotEmpty($data['#type']);
    $this->assertNotEmpty($data['#tag']);
    $this->assertEquals('100%', $data['#attributes']['width']);
    $this->assertEquals('400', $data['#attributes']['height']);
    $this->assertContains('/media/embed?url=https%3A//www.youtube.com/watch%3Fv%3DWadTyp3FcgU&max_width=100%25&max_height=400&hash=', $data['#attributes']['src']);
  }

  /**
   * We can only test error catching as of now due to api key limitation.
   *
   * @throws \Drupal\media\OEmbed\ResourceException
   */
  public function testEmbedlyFetch() : void {
    $response = $this->mediaHelper->fetchEmbedlyResource('https://www.youtube.com/watch?v=WadTyp3FcgU');
    $this->assertFalse($response, 'Test Negative case due to missing api.');
  }

  /**
   * Tests getting thumbnail uri.
   */
  public function testGetThumbail(): void {
    $resource['title'] = $this->randomString();
    $this->assertNotEmpty($this->mediaHelper->getLocalThumbnailUri($resource), 'thumbnail path is returned.');
  }

}
