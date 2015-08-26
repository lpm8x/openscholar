Feature:
  Testing access.

  @api @restful
  Scenario: Testing group content type cosuming
    Given I define "john" as a "private"
     When I consume "api/blog/12" as "demo"
     Then I verify the request "failed"

  @api @restful
  Scenario: Testing OG audience field population restrictions
    Given I try to post a "blog" as "alexander" to "john"
      And I verify it "failed"
     When I try to post a "blog" as "john" to "john"
     Then I verify it "passed"
