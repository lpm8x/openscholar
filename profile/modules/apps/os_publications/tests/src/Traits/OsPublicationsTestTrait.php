<?php

namespace Drupal\Tests\os_publications\Traits;

use Drupal\bibcite_entity\Entity\Contributor;
use Drupal\bibcite_entity\Entity\ContributorInterface;
use Drupal\bibcite_entity\Entity\Keyword;
use Drupal\bibcite_entity\Entity\KeywordInterface;
use Drupal\bibcite_entity\Entity\ReferenceInterface;

/**
 * OsPublications test helpers.
 */
trait OsPublicationsTestTrait {

  /**
   * Creates a contributor.
   *
   * @param array $values
   *   (Optional) Default values for the contributor.
   *
   * @return \Drupal\bibcite_entity\Entity\ContributorInterface
   *   The new contributor entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createContributor(array $values = []) : ContributorInterface {
    $contributor = Contributor::create($values + [
      'first_name' => $this->randomMachineName(),
      'middle_name' => $this->randomMachineName(),
      'last_name' => $this->randomMachineName(),
    ]);

    $contributor->save();

    $this->markEntityForCleanup($contributor);

    return $contributor;
  }

  /**
   * Creates a keyword.
   *
   * @param array $values
   *   (Optional) Default values for the keyword.
   *
   * @return \Drupal\bibcite_entity\Entity\KeywordInterface
   *   The new keyword entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createKeyword(array $values = []) : KeywordInterface {
    $keyword = Keyword::create($values + [
      'name' => $this->randomMachineName(),
    ]);

    $keyword->save();

    $this->markEntityForCleanup($keyword);

    return $keyword;
  }

  /**
   * Returns the rdf file template path.
   *
   * The path is already URI prefixed, i.e. prefixed with `public://`.
   *
   * @param \Drupal\bibcite_entity\Entity\ReferenceInterface $reference
   *   The reference whose template path to be obtained.
   *
   * @return string
   *   The template path.
   */
  protected function getRepecTemplatePath(ReferenceInterface $reference): string {
    /** @var \Drupal\repec\RepecInterface $repec */
    $repec = $this->container->get('repec');

    $serie_directory_config = $repec->getEntityBundleSettings('serie_directory', $reference->getEntityTypeId(), $reference->bundle());
    $directory = "{$repec->getArchiveDirectory()}{$serie_directory_config}/";
    $file_name = "{$serie_directory_config}_{$reference->getEntityTypeId()}_{$reference->id()}.rdf";

    return "$directory/$file_name";
  }

  /**
   * Returns a particular section of the html page.
   *
   * @return string
   *   The row html to compare.
   */
  protected function getActualHtml(): string {
    $page = $this->getCurrentPage();
    $row = $page->find('css', '.csl-entry');
    $row_html = $row->getHtml();
    // Strip Purl from the html for proper comparison as render method won't
    // return it.
    $stripped = str_replace("$this->groupAlias", '', $row_html);
    return preg_replace('/\s*/m', '', $stripped);
  }

  /**
   * Changes vsite style.
   *
   * @param string $style
   *   The style to be set.
   */
  protected function changeStyle(string $style): void {
    $this->visitViaVsite('cp/settings/apps-settings/publications', $this->group);
    $this->submitForm(['os_publications_preferred_bibliographic_format' => $style], 'edit-submit');
  }

}
