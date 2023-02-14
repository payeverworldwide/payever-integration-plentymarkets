@pluginIsEnabled @setupPaymentMethods @javascript @restartSession @payments @skip
Feature: Payments
  Adds a product to cart and go to checkout.

  Background:
    # Login as admin
    Given I am on admin login page
    And I login to admin section
    Then I see dashboard
    And I wait 1 seconds
    # Select plugin set
    Given I redirect to "/plenty/terra/plugins/plugin-sets/sets"
    And I wait till element exists ".theme-core"
    # Select the plugin set and click button to open context menu
    And I click on CSS locator "#tab-content table[data-cy=set-overview-plugins-table] tr.mat-row:nth-child(1) td.cdk-column-actions button:nth-child(2)"
    And I wait till element exists "#cdk-overlay-0"
    And I click on CSS locator "#cdk-overlay-0 button:nth-child(1)"
    # Switch to another window
    Then I switch window "1"
    Then I redirect to "/dining-room-chair-juicy-orange_115_1013"
    And I wait till element exists ".add-to-basket-container input.qty-input"
    And I fill input ".add-to-basket-container input.qty-input" with value "10"
    And I wait 1 seconds
    And I click on CSS locator ".add-to-basket-container button.btn-appearance"
    And I wait till "#add-item-to-basket-overlay .modal" popup is open
    And I click on CSS locator "#add-item-to-basket-overlay .btn:nth-child(2)"
    And I wait till element exists "#page-body"
    And I click on CSS locator ".col-sm-6 button.btn.btn-primary.btn-block.mb-3"
    And I wait till ".modal" popup is open
    And I wait till element exists "form.login-pwd-reset input[name=email]"
    And I scroll "form.login-pwd-reset button" into view
    And I fill input "form.login-pwd-reset input[name=email]" with value "autotest-plugin@example.com"
    And I click on CSS locator "form.login-pwd-reset button"
    And I wait 5 seconds
    And I wait till element exists ".page-checkout"
    And I wait till ".modal" popup is open
    And I select "male" from "txtSalutation15"
    And I fill in the following:
      | firstName       | Stub                        |
      | lastName        | User                        |
      | housenumber     | 10                          |
      | street          | Augsburger Strasse          |
      | zip             | 10111                       |
      | town            | Berlin                      |
      | email           | autotest-plugin@example.com |
    And I click on CSS locator "button[type=submit]"
    And I wait 2 seconds
    And I click on CSS locator ".payment-method-select ul.method-list li.method-list-item:nth-child(1) input"
    And I wait 2 seconds
    And I scroll "#registry-form-container input.form-check-input" into view
    And I click on CSS locator "#registry-form-container input.form-check-input"
    And I click on CSS locator ".checkout-rightside button.btn.btn-block.btn-primary.btn-appearance"
    And I wait 5 seconds
    And I switch to frame ".modal iframe"
    And I wait for visibility of ".ng-trigger.ng-trigger-slideInOut" is hidden
    And I should not see "Expired JWT Token"
    And I fill input "input[pe-qa-input-credit-card-number=\'control.formCardMain.cardNumber\']" with value "4242424242424242"
    And I fill input "input[pe-qa-input-credit-card-expiration=\'control.formCardMain.cardExpiration\']" with value "11/25"
    And I fill input "input[pe-qa-input=\'control.formCardMain.cardCvc\']" with value "123"
    And I click on CSS locator "checkout-sdk-continue-button button"

  Scenario: Payment
    # Return to the first window
    Given I switch window "0"
    And I redirect to "/plenty/terra/order/order-search"
    And I switch to frame "#gwtIframe"
    And I wait till element exists ".theme-core"
    And I wait 5 seconds
    And I click on CSS locator ".gwt-TabLayoutPanelContent button[data-icon=icon-search]"
    And I wait till element exists ".PlentyScrollablePanel"
    Then I should see a ".PlentyScrollablePanel .isOpen" element
