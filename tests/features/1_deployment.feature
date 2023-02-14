@pluginIsEnabled @setupPaymentMethods @javascript @restartSession @payments @deployment @skip
Feature: Deployment
  Triggers code deployment of Plentymarket.
  Payever, PlentyShop LTS and IO plugins must be configured.

  Background:
    Given I am on admin login page
    And I login to admin section
    Then I see dashboard
    And I wait 1 seconds

  Scenario: Deployment
    # Select plugin set
    Given I redirect to "/plenty/terra/plugins/plugin-sets/sets"
    And I wait till element exists ".theme-core"
    # Select the plugin set
    And I click on CSS locator "#tab-content table[data-cy=set-overview-plugins-table] tr.mat-row:nth-child(1)"
    And I wait till element exists ".theme-core"
    And I wait 10 seconds
    # Remove the plugin
    And I click on CSS locator "table.cdk-table tr.mat-row:nth-child(3) button#openActionMenu"
    And I wait till element exists "#cdk-overlay-0"
    And I click on CSS locator "#cdk-overlay-0 button.deleteButton"
    And I wait till element exists "terra-delete-plugin-overlay"
    And I fill input "terra-delete-plugin-overlay input[data-placeholder=\'Confirmation field\']" with value "DELETE"
    And I click on CSS locator "terra-delete-plugin-overlay button#confirm-button"
    And I wait 5 seconds
    # Install the plugin
    Given I redirect to "/plenty/terra/plugins/plugin-sets/sets"
    And I wait till element exists ".theme-core"
    # Select the plugin set
    And I click on CSS locator "#tab-content table[data-cy=set-overview-plugins-table] tr.mat-row:nth-child(1)"
    And I wait till element exists ".theme-core"
    And I wait 5 seconds
    # Add the plugin
    And I click on CSS locator "button#addButton"
    And I wait till element exists "table.mat-table.cdk-table.mat-sort"
    # Select Payever from the list
    And I click on CSS locator "table.mat-table.cdk-table.mat-sort tr.mat-row:nth-child(3)"
    And I wait till element exists "button#installPluginButton"
    And I wait till element exists "terra-plugin-install-update mat-form-field mat-select"

    # Select branch
    And I click on CSS locator "terra-plugin-install-update mat-form-field mat-select"
    And I wait till element exists ".mat-select-panel-wrap div[role=listbox]"
    And I select current branch in ".mat-select-panel-wrap div[role=listbox]"
    And I wait 1 seconds

    # Install the plugin
    Then I click on CSS locator "button#installPluginButton"
    And I wait 2 seconds
    Then I should see "Installing the plugin Payever"
    And I wait till I see "To publish your changes, you have to deploy the plugin set"

    # Make the plugin active
    And I click on CSS locator "terra-plugin-table table.mat-table.cdk-table.mat-sort tr.mat-row:nth-child(3) td mat-slide-toggle input"

    # Deploy
    And I click on CSS locator "button#deploy-button"
    Then I wait till element not exists ".mat-progress-bar"
