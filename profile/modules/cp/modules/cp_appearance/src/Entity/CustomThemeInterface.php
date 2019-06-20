<?php

namespace Drupal\cp_appearance\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for CustomTheme configuration entity.
 */
interface CustomThemeInterface extends ConfigEntityInterface {

  /**
   * Sets the base of the custom theme.
   *
   * @param string $theme
   *   The name of the theme.
   *
   * @return \Drupal\cp_appearance\Entity\CustomThemeInterface
   *   The custom theme this was called on.
   */
  public function setBaseTheme(string $theme): CustomThemeInterface;

  /**
   * Returns the base theme of the custom theme.
   *
   * @return string|null
   *   The theme name.
   */
  public function getBaseTheme(): ?string;

  /**
   * Returns the images of the custom theme.
   *
   * @return int[]
   *   The image file ids.
   */
  public function getImages(): array;

  /**
   * Sets images for the custom theme.
   *
   * @param int[] $images
   *   The image file ids.
   *
   * @return \Drupal\cp_appearance\Entity\CustomThemeInterface
   *   The custom theme this was called on.
   */
  public function setImages(array $images): CustomThemeInterface;

  /**
   * Returns the styles of the custom theme.
   *
   * @return string|null
   *   The cascading styles.
   */
  public function getStyles(): ?string;

  /**
   * Sets the styles for the custom theme.
   *
   * @param string $styles
   *   The cascading styles.
   *
   * @return \Drupal\cp_appearance\Entity\CustomThemeInterface
   *   The custom theme this was called on.
   */
  public function setStyles(string $styles): CustomThemeInterface;

  /**
   * Returns the scripts of the custom theme.
   *
   * @return string|null
   *   The JavaScript code added for the custom theme.
   */
  public function getScripts(): ?string;

  /**
   * Sets the scripts for the custom theme.
   *
   * @param string $scripts
   *   The JavaScript code to be set.
   *
   * @return \Drupal\cp_appearance\Entity\CustomThemeInterface
   *   The custom theme this was called on.
   */
  public function setScripts(string $scripts): CustomThemeInterface;

}