Feature:
  As a user
  I want to block/unmatch another user

  @block
  Scenario: I want to block another user
    Given the following profiles exist:
        | email                          | characteristics  | requirements   | city    | age |
        | chelsea_blue@example.com       | Square, Blue     | Yellow, Circle | London  | 30  |
        | westminster_yellow@example.com | Yellow, Circle   | Blue, Square   | London  | 30  |
    And the following blocks exist
        | email                    | block                          |
        | chelsea_blue@example.com | westminster_yellow@example.com |
    When the user "chelsea_blue@example.com" searches for matches
    Then the user "westminster_yellow@example.com" does not match

  @block
  Scenario: I have been blocked by another user
    Given the following profiles exist:
      | email                          | characteristics  | requirements   | city    | age |
      | chelsea_blue@example.com       | Square, Blue     | Yellow, Circle | London  | 30  |
      | westminster_yellow@example.com | Yellow, Circle   | Blue, Square   | London  | 30  |
    And the following blocks exist
      | email                    | block                          |
      | chelsea_blue@example.com | westminster_yellow@example.com |
    When the user "westminster_yellow@example.com" searches for matches
    Then the user "chelsea_blue@example.com" does not match

  @ui
  @block
  Scenario: I can block another user
    Given the following profiles exist:
      | email                          | characteristics  | requirements   | city    | age |
      | chelsea_blue@example.com       | Square, Blue     | Yellow, Circle | London  | 30  |
      | westminster_yellow@example.com | Yellow, Circle   | Blue, Square   | London  | 30  |
    And I am logged in with "chelsea_blue@example.com"
    And I am on "/search"
    Then I should see "westminster_yellow"
    And I follow "westminster_yellow"
    And I follow "Block"
    And I select "No reason" from "block_form_reason"
    And I press "Block"
    Then I should be on "/search"
    And I should see "Successfully blocked user"
    And I should not see "westminster_yellow"