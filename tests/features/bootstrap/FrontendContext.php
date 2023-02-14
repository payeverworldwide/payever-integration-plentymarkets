<?php

namespace Payever\Tests;

use Assert\Assertion;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\Selenium2Driver;
use Payever\Stub\BehatExtension\Context\FrontendContext as BaseFrontendContext;

class FrontendContext extends BaseFrontendContext
{
    /** @var string */
    private $backendUrl;

    /** @var string */
    private $backendUsername;

    /** @var string */
    private $backendPassword;

    /**
     * {@inheritDoc}
     */
    public function setUrl($url)
    {
        $this->backendUrl = $url;
    }

    /**
     * {@inheritDoc}
     */
    public function setUsername($username)
    {
        $this->backendUsername = $username;
    }

    /**
     * {@inheritDoc}
     */
    public function setPassword($password)
    {
        $this->backendPassword = $password;
    }

    /**
     * @BeforeScenario
     */
    public function setupTimeouts()
    {
        if ($this->getSession()->getDriver() instanceof Selenium2Driver) {
            $this->getSession()->getDriver()->setTimeouts(['page load' => 120 * 1000]);
        }
    }

    /**
     * @Given /^(?:|I )login to admin section$/
     */
    public function loginToAdminSection()
    {
        $this->waitTillElementExists('input[name="username"]');
        $this->fillInput('[name=language]', 'en');

        $page = $this->getSession()->getPage();
        $page->fillField('username', $this->backendUsername);
        $page->fillField('password', $this->backendPassword);
        $page->pressButton('Login');
    }

    /**
     * @Given /^I go to the url "([^"]+)"$/
     */
    public function iGoToTheUrl($url) {
        $this->getSession()->visit($url);
    }

    /**
     * @Given /^(?:|I )am on admin page with path "([^"]+)"$/
     *
     * @param string $path
     */
    public function visitAdminPagePath($path)
    {
        $path = rtrim($this->backendUrl, '/') . '/' . $path;
        $this->visitPath($path);
        $this->getSession()->wait(500);
    }

    /**
     * @Given /^(?:|I )am on admin login page$/
     */
    public function openAdminHomepage()
    {
        $this->visitAdminPagePath('');
    }
    /**
     * @Given /^(?:|I )fill input "([^"]+)" with value "([^"]+)"$/
     */
    public function fillInput($inputSelector, $value)
    {
        $this->waitTillElementExists($inputSelector);
        $this->scrollIntoView($inputSelector);

        $script = <<<JS
let elements = document.querySelectorAll("$inputSelector");
if (elements.length > 0) {
    const event = new Event('input', { bubbles: true }); 
    elements[0].value = '$value';
    elements[0].dispatchEvent(event);
} else {
   throw new Error('Input not found by selector $inputSelector');
}
JS;

        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^I should see payment option "([^"]+)"$/
     *
     * @param string $name
     */
    public function iShouldSeePaymentOption($name)
    {
        $script = <<<JS
(function () {
    var targetText = '$name';
    var methodNames = document.querySelectorAll('.payment-option label span');
    for (var i = 0; i < methodNames.length; i++) {
        if (methodNames.hasOwnProperty(i) && methodNames[i].innerText.indexOf(targetText) >= 0) {
            return;
        }
    }
    throw new Error('Payment option ' + targetText + ' not found on this page');
})()
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )wait till "([^"]+)" popup is open$/
     *
     * @param string $name
     */
    public function waitTillCartPopupIsOpen($name)
    {
        $this->waitTillElementExists($name);
        $condition = <<<JS
document.querySelectorAll('$name')[0].style.display == 'block'
JS;
        $this->getSession()->wait(
            30000,
            $condition
        );
    }

    /**
     * @Given /^(?:|I )wait for visibility of "([^"]+)" is hidden/
     *
     * @param string $name
     */
    public function waitTillElementVisibilityIsHidden($name)
    {
        $this->waitTillElementExists($name);
        $condition = <<<JS
document.querySelectorAll('$name')[0].style.visibility === 'hidden'
JS;
        $this->getSession()->wait(
            30000,
            $condition
        );
    }

    /**
     * @Given /^(?:|I )should (not\s)?see the following elements:$/
     * | title |
     *
     * @param TableNode $table
     * @param bool $not
     */
    public function assertElementsExist(TableNode $table, $not = false)
    {
        foreach ($table as $item) {
            if ($not) {
                $this->assertElementNotOnPage($item['selector']);
            } else {
                $this->assertElementOnPage($item['selector']);
            }
        }
    }

    /**
     * @Given /^(?:|I )click on element "([^"]+)" with text "([^"]+)"$/
     *
     * @param string $tag
     * @param string $text
     * @throws \Assert\AssertionFailedException
     */
    public function clickOnText($tag, $text)
    {
        $element = $this->getSession()->getPage()->find('xpath', "//{$tag}[contains(text(), '$text')]");
        Assertion::notNull($element, sprintf("Element with text '%s' not found", $text));
        $element->click();
    }

    /**
     * @Given /^(?:|I )switch to frame "([^"]+)"$/
     *
     * @param string $name
     */
    public function switchToFrame($locator)
    {
        $function = <<<JS
            (function(){
                 var iframe = document.querySelector("$locator");
                 iframe.name = "iframeToSwitchTo";
            })()
JS;
        try {
            $this->getSession()->executeScript($function);
        } catch (\Exception $e){
            throw new \Exception("Element $locator was NOT found.".PHP_EOL . $e->getMessage());
        }

        $this->getSession()->getDriver()->switchToIFrame("iframeToSwitchTo");
    }

    /**
     * @param int $secondsLimit
     */
    private function waitTillPageReload($secondsLimit)
    {
        $session = $this->getSession();
        $script = <<<JS
window.reloadStarted = false;
window.onunload = function () { window.reloadStarted = true; };
JS;
        $session->executeScript($script);
        $condition = <<<JS
(window.reloadStarted === true || typeof window.reloadStarted === "undefined")
JS;
        $session->wait(
            $secondsLimit * 1000,
            $condition
        );
    }

    /**
     * Opens specified page
     * Example: Given I am redirecting to "http://batman.com"
     * Example: And I am redirecting to "/articles/isBatmanBruceWayne"
     * Example: When I redirect to "/articles/isBatmanBruceWayne"
     *
     * @Given /^(?:|I )am redirecting to "(?P<page>[^"]+)"$/
     * @When /^(?:|I )redirect to "(?P<page>[^"]+)"$/
     */
    public function redirectTo($page)
    {
        $session = $this->getSession();
        $script = <<<JS
self.location.href = '$page';
JS;
        $session->executeScript($script);

        $this->waitTillElementExists('html');
    }

    /**
     * @Then /^(?:|I )wait till element not exists "(.*)"$/
     *
     * @param string $selector
     */
    public function waitTillElementExists($selector)
    {
        $condition = <<<JS
document.querySelectorAll('$selector').length === 0
JS;
        $this->getSession()->wait(30000, $condition);
        $this->getSession()->wait(1500);
    }

    /**
     * @Given /^(?:|I )select option in Angular listbox "([^"]+)" with value "([^"]+)"$/
     */
    public function selectListBoxItem($listBoxSelector, $value)
    {
        $this->waitTillElementExists($listBoxSelector);
        $this->scrollIntoView($listBoxSelector);

        $script = <<<JS
let elements = document.querySelectorAll("$listBoxSelector");
if (elements.length > 0) {
    let listBox = elements[0];

    let items = document.querySelectorAll("mat-option span.mat-option-text");
    items.forEach(function(currentValue, index, array) {
        if (currentValue.textContent.trim() === 'PLENTY-63') {
            currentValue.click();
        }
    });

} else {
   throw new Error('Input not found by selector $listBoxSelector');
}
JS;

        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )select current branch in "([^"]+)"$/
     */
    public function selectCurrentGitBranch($listBoxSelector)
    {
        $branch = getenv('BRANCH');
        if (empty($branch)) {
            $branch = getenv('CI_COMMIT_BRANCH');
        }

        if (empty($branch)) {
            $branch = 'master';
        }

        echo 'Selected branch: ' . $branch;

        $this->selectListBoxItem($listBoxSelector, $branch);
    }

    /**
     * @Given /^(?:|I )switch window "([^"]+)"$/
     */
    public function changeWindow($number)
    {
        $ttw = 40;
        while ((sizeof($this->getSession()->getWindowNames()) < 2 && $ttw > 0) == true) {
            $this->getSession()->wait(1000);
            $ttw--;
        }

        $windowNames = $this->getSession()->getWindowNames();
        $this->getSession()->switchToWindow($windowNames[(int) $number]);
    }
}
