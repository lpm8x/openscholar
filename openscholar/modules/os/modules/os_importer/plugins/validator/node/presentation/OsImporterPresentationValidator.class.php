<?php

/**
 * @file
 * Contains OsImporterPresentationValidator
 */
/**
 * required title
 * validate date
 */
class OsImporterPresentationValidator extends OsImporterEntityValidateBase {

  public function setFieldsInfo() {
    $fields = parent::setFieldsInfo();

    $fields['field_presentation_date__start'] = array (
      'validators' => array(
        'validatorDate',
      ),
    );

    return $fields;
  }

  function validatorDate($field, $value) {
    // todo: todo.
  }
}
