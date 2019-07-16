<?php

namespace Drupal\cp_taxonomy;

/**
 * Helper functions interface.
 */
interface CpTaxonomyHelperInterface {

  /**
   * Find out the list of vocabulary vids.
   *
   * Related to current entity type and bundle which is stored in config.
   *
   * @param string $bundle_key
   *   Entity type and bundle information.
   *
   * @return array
   *   List of vocabulary vid.
   */
  public function searchAllowedVocabulariesByType(string $bundle_key): array;

  /**
   * Get selected bundles from stored config.
   *
   * @param array $form
   *   Form array.
   *
   * @return array
   *   Selected bundles in array.
   */
  public function getSelectedBundles(array $form): array;

  /**
   * Get selectable bundles.
   *
   * @return array
   *   Selectable bundles array, named entity_type:bundle.
   */
  public function getSelectableBundles(): array;

  /**
   * Get selectable bundles.
   *
   * @param string $vid
   *   Vocabulary id.
   * @param array $allowed_entity_types
   *   Allowed entity types array from form_state.
   */
  public function saveAllowedBundlesToVocabulary(string $vid, array $allowed_entity_types): void;

  /**
   * Explode entity bundles.
   *
   * @param array $bundles
   *   Array of entity bundles.
   *
   * @return array
   *   Exploded array, keyed entity name and values are bundles array.
   */
  public function explodeEntityBundles(array $bundles): array;

  /**
   * Check visibility of taxonomy terms on page.
   *
   * @param array $build
   *   View alter build array.
   * @param array $view_modes
   *   Applied view modes.
   */
  public function checkTaxonomyTermsPageVisibility(array &$build, array $view_modes): void;

  /**
   * Check visibility of taxonomy terms on list page.
   *
   * @param array $build
   *   View alter build array.
   * @param string $entity_type
   *   Current entity type with bundle (ex node:news).
   */
  public function checkTaxonomyTermsListingVisibility(array &$build, string $entity_type): void;

  /**
   * Set build cache tags.
   *
   * @param array $build
   *   View alter build array.
   */
  public function setCacheTags(array &$build): void;

}
