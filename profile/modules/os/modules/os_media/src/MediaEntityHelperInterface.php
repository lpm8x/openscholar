<?php

namespace Drupal\os_media;

/**
 * Helper for Media entity for media browser related operations.
 */
interface MediaEntityHelperInterface {

  /**
   * Handles field mappings for different bundles.
   *
   * @param string $bundle
   *   The bundle to return the field for.
   *
   * @return string
   *   The mapped field.
   */
  public function getField(string $bundle) : string;

  /**
   * Returns the type of embed.
   *
   * @param string $embed
   *   Embedded code.
   *
   * @return string|null
   *   Type of media embed.
   */
  public function getEmbedType($embed) : ?string;

  /**
   * Get height and width for the content.
   *
   * @param string $html
   *   Actual embed html.
   * @param array $max
   *   Max height/width settings.
   *
   * @return array
   *   Optimal height/width settings.
   */
  public function getHtmlDimensions($html, array $max) : array;

  /**
   * Get height and width for the content.
   *
   * @param array $resource
   *   Resource to get height and width.
   * @param array $max
   *   Max height and width.
   *
   * @return array
   *   Height and with for the embed.
   */
  public function getOembedDimensions(array $resource, array $max) : array;

  /**
   * Fetches Embedly resource.
   *
   * @param string $url
   *   Resource url to fetch.
   * @param string $width
   *   Max width of the resource to emebed.
   * @param string $height
   *   Max height of the resource to emebed.
   *
   * @return mixed
   *   Data representation of embedly resource
   */
  public function fetchEmbedlyResource($url, $width, $height);

  /**
   * Returns Iframe data.
   *
   * @param string $value
   *   Field value.
   * @param array $max
   *   Max dimensions.
   * @param string $domain
   *   Domain to set.
   *
   * @return array
   *   Iframe data.
   */
  public function iFrameData($value, array $max, $domain) : array;

  /**
   * Downloads remote thumbnail uri.
   *
   * @param array $resource
   *   Embedly resource.
   */
  public function downloadThumbnail(array $resource): void;

  /**
   * Returns local thumbnail uri.
   *
   * @param array $resource
   *   Embedly resource.
   *
   * @return string
   *   Thumbnail uri.
   */
  public function getLocalThumbnailUri(array $resource) : string;

}