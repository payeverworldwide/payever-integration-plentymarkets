@pluginIsEnabled @setupPaymentMethods @javascript @restartSession @payments @configuration @skip
Feature: Configuration
  Configure Payever payment methods of Plentymarket.
  Please note that Payever Assistant must be passed before.
  It will be removed and passed again.

  Background:
    Given I am on admin login page
    And I login to admin section
    Then I see dashboard
    And I wait 1 seconds

  Scenario: Configuration
    # Visit Assistants page
    Given I redirect to "/plenty/terra/system/assistants/plugin/pluginSet1"
    And I wait till element exists ".theme-core"
    And I wait 10 seconds
    # Select Payever Assistant. It should be passed. Remove it.
    And I click on CSS locator "terra-wizard-browser mat-card.assistant-card:nth-child(1)"
    And I wait 1 seconds
    # Click Delete
    Then I click on CSS locator "terra-wizard-option-card button"
    And I wait till element exists "terra-delete-option-id-dialog"
    And I click on CSS locator "terra-delete-option-id-dialog button.mat-primary"
    And I wait 2 seconds
    Then I should see "There is no configuration for the assistant Payever Assistant available"
    Then I should see "Create a new configuration in order to continue"
    # Click Create
    Then I click on CSS locator "terra-no-result a.terra-no-result-action"
    And I wait 2 seconds
    And I wait till element exists "mat-dialog-container"
    Then I should see "New configuration for Payever Assistant"
    And I click on CSS locator "mat-dialog-container button.mat-button"
    And I wait till element exists "terra-wizard-step"
    And I wait 2 seconds
    Then I should see "Payever Assistant"
    # Setup API Keys: Click Next
    Then I should see "Setup API Keys"
    Then I click on CSS locator "terra-wizard-navigation button:nth-child(2)"
    And I wait 2 seconds
    # Settings: Click Next
    Then I should see "Settings"
    Then I click on CSS locator "terra-wizard-navigation button:nth-child(2)"
    And I wait 2 seconds
    # Configure payment methods: Click Next
    Then I should see "Configure payment methods"
    Then I click on CSS locator "terra-wizard-navigation button:nth-child(2)"
    And I wait 2 seconds
    # Summary: Click Finalize
    Then I should see "Summary"
    Then I click on CSS locator "terra-wizard-navigation button:nth-child(2)"
    And I wait till element exists "terra-recommended-assistants-dialog"
    And I wait 2 seconds
    Then I should see "The assistant Payever Assistant has been completed successfully"
