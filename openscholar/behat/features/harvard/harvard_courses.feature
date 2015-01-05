Feature:
  Testing the harvard courses import mechanism.

  @api @harvard
  Scenario: Importing courses and test their grouping to the correct sites.
    Given I am logging in as "admin"

    # Define harvard courses
     When I set feature "edit-spaces-features-harvard-courses" to "Public" on "john"
      And I set courses to import
      And I refresh courses
      And I visit "john/courses"
      And I should see "(Re)fabricating Tectonic Prototypes"

    # Remove the courses from the site.
      And I remove harvard courses
      And I visit "john/courses"
      And I should not see "(Re)fabricating Tectonic Prototypes"

    # Re add the courses and verify they were added.
      And I add the courses
      And I visit "john/courses"
     Then I should see "(Re)fabricating Tectonic Prototypes"

  @api @harvard
    Scenario: Testing the hvarvard courses bread crumb.
      Given I visit "john/courses"
       When I click "(Re)fabricating Tectonic Prototypes"
        And I click "Harvard courses"
       Then I should see "(Re)fabricating Tectonic Prototypes"
