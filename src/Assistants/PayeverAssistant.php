<?php

namespace Payever\Assistants;

use Payever\Assistants\SettingsHandlers\PayeverAssistantSettingsHandler;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Order\Currency\Contracts\CurrencyRepositoryContract;
use Plenty\Modules\System\Models\Webstore;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Modules\Wizard\Services\WizardProvider;
use Plenty\Plugin\Application;
use Plenty\Plugin\ConfigRepository;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class PayeverAssistant extends WizardProvider
{
    const WIZARD_KEY = 'payment-payeverAssistant-assistant';

    /**
     * @var WebstoreRepositoryContract
     */
    private $webstoreRepository;

    /**
     * @var CountryRepositoryContract
     */
    private $countryRepository;

    /**
     * @var CurrencyRepositoryContract
     */
    private $currencyRepository;

    /**
     * @var ConfigRepository
     */
    private $configRepository;

    /**
     * @var array
     */
    private $webstoreValues;

    public function __construct(
        WebstoreRepositoryContract $webstoreRepository,
        CountryRepositoryContract $countryRepository,
        CurrencyRepositoryContract $currencyRepository,
        ConfigRepository $configRepository
    ) {
        $this->webstoreRepository = $webstoreRepository;
        $this->countryRepository = $countryRepository;
        $this->currencyRepository = $currencyRepository;
        $this->configRepository = $configRepository;
    }

    /**
     *  In this method we define the basic settings and the structure of the assistant in an array.
     *  Here, we have to define aspects like the topic, settings handler, steps and form elements.
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function structure(): array
    {
        return [
            /** Use translate keys for multilingualism. */
            'title' => 'Assistant.title',
            'shortDescription' => 'Assistant.description',
            'iconPath' => $this->getIcon(),
            'settingsHandlerClass' => PayeverAssistantSettingsHandler::class,
            'translationNamespace' => 'Payever',
            'key' => self::WIZARD_KEY,
            'keywords' => [
                'payever'
            ],
            'topics' => ['payment'],
            'priority' => 990,
            'options' => [
                'store' => [
                    'type' => 'select',
                    'defaultValue' => $this->getMainWebstore(),
                    'options' => [
                        'name' => 'Assistant.storeName',
                        'required' => true,
                        'listBoxValues' => $this->getWebstoreListForm(),
                    ],
                ],
            ],
            /** Define steps for the assistant. */
            "steps" => [
                'stepOne' => [
                    'title' => 'Assistant.stepOneTitle',
                    'sections' => [
                        [
                            'title' => 'Assistant.stepOneTitle',
                            'description' => 'Assistant.stepOneTitle',
                            'form' => [
                                'environment' => [
                                    'type' => 'select',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.environmentLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.environmentPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.environmentPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'clientId' => [
                                    'type' => 'text',
                                    'defaultValue' => '1454_2ax8i5chkvggc8w00g8g4sk80ckswkw0c8k8scss40o40ok4sk',
                                    'options' => [
                                        'name' => 'Assistant.clientIdLabel',
                                        'required' => true,
                                    ]
                                ],
                                'clientSecret' => [
                                    'type' => 'text',
                                    'defaultValue' => '22uvxi05qlgk0wo8ws8s44wo8ccg48kwogoogsog4kg4s8k8k',
                                    'options' => [
                                        'name' => 'Assistant.clientSecretLabel',
                                        'required' => true,
                                    ]
                                ],
                                'slug' => [
                                    'type' => 'text',
                                    'defaultValue' => 'payever',
                                    'options' => [
                                        'name' => 'Assistant.slugLabel',
                                        'required' => true,
                                    ]
                                ],
                            ]
                        ]
                    ]
                ],
                'stepTwo' => [
                    'title' => 'Assistant.stepTwoTitle',
                    'sections' => [
                        [
                            'title' => 'Assistant.stepTwoTitle',
                            'description' => 'Assistant.stepTwoTitle',
                            'form' => [
                                'display_payment_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.displayPaymentFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'display_payment_description' => [
                                    'type' => 'select',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.displayPaymentDescriptionLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'display_payment_icon' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.displayPaymentIconLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'redirect_to_payever' => [
                                    'type' => 'select',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.redirectToPayeverLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.redirectToPayeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.redirectToPayeverPossibleValue1',
                                                'value' => '1'
                                            ],
                                            [
                                                'caption' => 'Assistant.redirectToPayeverPossibleValue2',
                                                'value' => '2'
                                            ],
                                        ]
                                    ]
                                ],
                                'order_before_payment' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.orderBeforePaymentLabel',
                                        'required' => true,

                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],

                            ]
                        ]
                    ]
                ],
                'stepThree' => [
                    'title' => 'Assistant.stepThreeTitle',
                    'sections' => [
                        // Stripe Credit Card
                        [
                            'title' => 'Assistant.CreditCardTab',
                            'description' => 'Assistant.CreditCardTab',
                            'form' => [
                                'stripe.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'stripe.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Credit Card',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Simply pay by credit card. We accept MasterCard, Visa, and American Express.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'stripe.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'stripe.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '2.9',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.5',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'stripe.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCurrencies(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(),
                                    ]
                                ],
                            ]
                        ],
                        // Stripe Direct Debit
                        [
                            'title' => 'Assistant.StripeDirectDebitTab',
                            'description' => 'Assistant.StripeDirectDebitTab',
                            'form' => [
                                'stripe_directdebit.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'stripe_directdebit.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Stripe Direct Debit',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe_directdebit.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Accept SEPA Direct Debit Payments with Stripe.',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'stripe_directdebit.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'stripe_directdebit.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe_directdebit.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '2.9',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe_directdebit.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.5',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe_directdebit.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'stripe_directdebit.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'stripe_directdebit.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCurrencies(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(),
                                    ]
                                ],
                            ]
                        ],
                        // Stripe Direct Debit
                        [
                            'title' => 'Assistant.DirectDebitTab',
                            'description' => 'Assistant.DirectDebitTab',
                            'form' => [
                                'paymill_directdebit.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'paymill_directdebit.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Direct Debit',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_directdebit.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Simply pay via direct debit.',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'paymill_directdebit.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'paymill_directdebit.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_directdebit.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '2.9',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_directdebit.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.5',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_directdebit.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_directdebit.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'paymill_directdebit.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCurrencies(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(),
                                    ]
                                ],
                            ]
                        ],
                        // Paymill Credit Card
                        [
                            'title' => 'Assistant.PaymillCreditCardTab',
                            'description' => 'Assistant.PaymillCreditCardTab',
                            'form' => [
                                'paymill_creditcard.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'paymill_creditcard.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Paymill Credit Card',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_creditcard.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Pay your order with credit card. We accept Visa, Mastercard and American Express.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'paymill_creditcard.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'paymill_creditcard.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.28',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_creditcard.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '2.95',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_creditcard.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.5',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_creditcard.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paymill_creditcard.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'paymill_creditcard.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCurrencies(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(),
                                    ]
                                ],
                            ]
                        ],
                        // PayPal
                        [
                            'title' => 'Assistant.PayPalTab',
                            'description' => 'Assistant.PayPalTab',
                            'form' => [
                                'paypal.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'paypal.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'PayPal',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paypal.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'PayPal. Want it, get it.',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'paypal.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'paypal.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'paypal.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.35',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paypal.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '1.9',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paypal.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.5',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paypal.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'paypal.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'paypal.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCurrencies(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Invoice NO
                        [
                            'title' => 'Assistant.SantanderInvoiceNOTab',
                            'description' => 'Assistant.SantanderInvoiceNOTab',
                            'form' => [
                                'santander_invoice_no.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_invoice_no.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Invoice NO',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_no.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Kjøp nå  - betal etter levering. Fakturaen sendes på  epost fra Santander Consumer Bank.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_invoice_no.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_invoice_no.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_no.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_no.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_no.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_no.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['NO'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['NO']),
                                    ]
                                ],
                                'santander_invoice_no.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['NOK'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['NOK']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Invoice DE
                        [
                            'title' => 'Assistant.SantanderInvoiceDETab',
                            'description' => 'Assistant.SantanderInvoiceDETab',
                            'form' => [
                                'santander_invoice_de.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_invoice_de.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Invoice Germany',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_de.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Simply pay after delivery.',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_invoice_de.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_invoice_de.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_de.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_de.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_de.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_invoice_de.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['DE'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['DE']),
                                    ]
                                ],
                                'santander_invoice_de.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Factoring
                        [
                            'title' => 'Assistant.SantanderFactoringDETab',
                            'description' => 'Assistant.SantanderFactoringDETab',
                            'form' => [
                                'santander_factoring_de.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_factoring_de.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Factoring',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_factoring_de.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Factoring description.',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_factoring_de.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_factoring_de.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_factoring_de.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_factoring_de.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_factoring_de.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_factoring_de.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['DE'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['DE']),
                                    ]
                                ],
                                'santander_factoring_de.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Factoring
                        [
                            'title' => 'Assistant.SantanderInstallmentTab',
                            'description' => 'Assistant.SantanderInstallmentTab',
                            'form' => [
                                'santander_installment.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installment',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Pay your purchases starting from 99 with Santander Installments. With an annual percentage rate of max. 9,9% (6 - 72 monthly rates).', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_installment.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['DE'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['DE']),
                                    ]
                                ],
                                'santander_installment.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Installments AT
                        [
                            'title' => 'Assistant.SantanderInstallmentATTab',
                            'description' => 'Assistant.SantanderInstallmentTab',
                            'form' => [
                                'santander_installment_at.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_at.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments AT',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_at.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments AT',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_installment_at.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_at.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_at.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_at.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_at.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_at.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_at.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['AT'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['AT']),
                                    ]
                                ],
                                'santander_installment_at.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Installments BE
                        [
                            'title' => 'Assistant.SantanderInstallmentBETab',
                            'description' => 'Assistant.SantanderInstallmentTab',
                            'form' => [
                                'santander_installment_be.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_be.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments BE',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_be.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments BE',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_installment_be.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_be.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_be.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_be.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_be.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_be.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_be.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['BE'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['BE']),
                                    ]
                                ],
                                'santander_installment_be.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Installments FI
                        [
                            'title' => 'Assistant.SantanderInstallmentFITab',
                            'description' => 'Assistant.SantanderInstallmentFITab',
                            'form' => [
                                'santander_installment_fi.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_fi.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments FI',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_fi.description' => [
                                    'type' => 'text',
                                    'defaultValue' => '',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_installment_fi.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_fi.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_fi.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_fi.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_fi.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '300',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_fi.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '40000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_fi.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['FI'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['FI']),
                                    ]
                                ],
                                'santander_installment_fi.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Installment DK
                        [
                            'title' => 'Assistant.SantanderInstallmentDKTab',
                            'description' => 'Assistant.SantanderInstallmentDKTab',
                            'form' => [
                                'santander_installment_dk.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_dk.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments DK',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_dk.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments DK',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_installment_dk.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_dk.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.25',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_dk.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_dk.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_dk.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_dk.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['DK'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['DK']),
                                    ]
                                ],
                                'santander_installment_dk.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['DKK'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['DKK']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Installment NO
                        [
                            'title' => 'Assistant.SantanderInstallmentNOTab',
                            'description' => 'Assistant.SantanderInstallmentNOTab',
                            'form' => [
                                'santander_installment_no.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_no.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments NO',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_no.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments NO',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_installment_no.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_no.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_no.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_no.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_no.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_no.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['NO'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['NO']),
                                    ]
                                ],
                                'santander_installment_no.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['NOK'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['NOK']),
                                    ]
                                ],
                            ]
                        ],
                        // Santander Installment SE
                        [
                            'title' => 'Assistant.SantanderInstallmentSETab',
                            'description' => 'Assistant.SantanderInstallmentSETab',
                            'form' => [
                                'santander_installment_se.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_se.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments SE',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_se.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Santander Installments SE',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'santander_installment_se.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'santander_installment_se.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_se.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_se.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_se.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'santander_installment_se.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['SE'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['SE']),
                                    ]
                                ],
                                'santander_installment_se.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['SEK'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['SEK']),
                                    ]
                                ],
                            ]
                        ],
                        // Sofort
                        [
                            'title' => 'Assistant.SofortTab',
                            'description' => 'Assistant.SofortTab',
                            'form' => [
                                'sofort.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'sofort.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Sofort',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'sofort.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Simply pay while you are shopping online. By entering your familiar online banking login details and confirmation codes with a maximum level of security. The online shop will receive a real-time transaction confirmation immediately after the transfer has been listed.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'sofort.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'sofort.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'sofort.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.15',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'sofort.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.8',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'sofort.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'sofort.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'sofort.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['BE', 'NL', 'CH', 'HU', 'DE', 'AT', 'SK', 'GB', 'IT', 'PL', 'ES'], //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(
                                            ['BE', 'NL', 'CH', 'HU', 'DE', 'AT', 'SK', 'GB', 'IT', 'PL', 'ES']
                                        ),
                                    ]
                                ],
                                'sofort.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // PayEx Faktura
                        [
                            'title' => 'Assistant.PayExFakturaTab',
                            'description' => 'Assistant.PayExFakturaTab',
                            'form' => [
                                'payex_faktura.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'payex_faktura.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'PayEx Faktura',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_faktura.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'PayEx Faktura',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'payex_faktura.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'payex_faktura.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.35',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_faktura.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '1.9',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_faktura.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_faktura.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_faktura.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['SE', 'NO'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['SE', 'NO']),
                                    ]
                                ],
                                'payex_faktura.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['SEK', 'NOK'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['SEK', 'NOK']),
                                    ]
                                ],
                            ]
                        ],
                        // PayEx Credit Card
                        [
                            'title' => 'Assistant.PayExCreditcardTab',
                            'description' => 'Assistant.PayExCreditcardTab',
                            'form' => [
                                'payex_creditcard.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'payex_creditcard.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'PayEx Credit Card',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_creditcard.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'PayEx Credit Card',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'payex_creditcard.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'payex_creditcard.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.35',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_creditcard.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '1.9',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_creditcard.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_creditcard.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'payex_creditcard.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'payex_creditcard.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCurrencies(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(),
                                    ]
                                ],
                            ]
                        ],
                        // Direct bank transfer
                        [
                            'title' => 'Assistant.InstantPaymentTab',
                            'description' => 'Assistant.InstantPaymentTab',
                            'form' => [
                                'instant_payment.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'instant_payment.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Direct bank transfer',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'instant_payment.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Upon redirect you\'ll log in with your online banking crendentials and confirm the bank transfer to the merchant. He\'ll receive a confirmation immediately and can ship your order right away. Direct bank transfer is a service by Santander, but can be used by customers of any bank.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'instant_payment.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'instant_payment.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'instant_payment.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'instant_payment.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'instant_payment.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'instant_payment.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'instant_payment.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCurrencies(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(),
                                    ]
                                ],
                            ]
                        ],
                        // Zinia BNPL
                        [
                            'title' => 'Assistant.ZiniaBnplTab',
                            'description' => 'Assistant.ZiniaBnplTab',
                            'form' => [
                                'zinia_bnpl.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'zinia_bnpl.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Zinia. Achteraf Betalen',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Voldoe in betaalbare maandelijkse termijnen via onze partner Zinia', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'zinia_bnpl.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'zinia_bnpl.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'zinia_bnpl.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '50',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '750',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['NL'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['NL']),
                                    ]
                                ],
                                'zinia_bnpl.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Zinia BNPL DE
                        [
                            'title' => 'Assistant.ZiniaBnplDETab',
                            'description' => 'Assistant.ZiniaBnplDETab',
                            'form' => [
                                'zinia_bnpl_de.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'zinia_bnpl_de.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Zinia Rechnungskauf',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl_de.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Mit dem Zinia Rechnungskauf geben Sie Ihren Kunden 14 Tage Zeit um den Einkauf zu bezahlen.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'zinia_bnpl_de.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'zinia_bnpl_de.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'zinia_bnpl_de.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl_de.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl_de.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '50',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl_de.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '750',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'zinia_bnpl_de.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['DE'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['DE']),
                                    ]
                                ],
                                'zinia_bnpl_de.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // Swedbank Credit Card
                        [
                            'title' => 'Assistant.SwedbankCreditCardTab',
                            'description' => 'Assistant.SwedbankCreditCardTab',
                            'form' => [
                                'swedbank_creditcard.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'swedbank_creditcard.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Swedbank Credit Card',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_creditcard.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Simply let your customers pay by credit card. We accept MasterCard, Visa, American Express and many more.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'swedbank_creditcard.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'swedbank_creditcard.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_creditcard.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_creditcard.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_creditcard.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_creditcard.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => $this->getDefaultCountries(),
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(),
                                    ]
                                ],
                                'swedbank_creditcard.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(
                                            ['NOK', 'SEK', 'DKK', 'USD', 'EUR']
                                        ),
                                    ]
                                ],
                            ]
                        ],
                        // Swedbank Invoice
                        [
                            'title' => 'Assistant.SwedbankInvoiceTab',
                            'description' => 'Assistant.SwedbankInvoiceTab',
                            'form' => [
                                'swedbank_invoice.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'swedbank_invoice.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Swedbank Invoice',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_invoice.description' => [
                                    'type' => 'text',
                                    'defaultValue' => 'Simply let your customers pay after delivery. You receive your payout before the customer pays the invoice.', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'swedbank_invoice.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'swedbank_invoice.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_invoice.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_invoice.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_invoice.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '4755.35',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'swedbank_invoice.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['SE', 'NO', 'FI'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['SE', 'NO', 'FI']),
                                    ]
                                ],
                                'swedbank_invoice.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['NOK', 'SEK'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['NOK', 'SEK']),
                                    ]
                                ],
                            ]
                        ],
                        // iDEAL
                        [
                            'title' => 'Assistant.IdealTab',
                            'description' => 'Assistant.IdealTab',
                            'form' => [
                                'ideal.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'ideal.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'iDEAL',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ideal.description' => [
                                    'type' => 'text',
                                    'defaultValue' => '', //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'ideal.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'ideal.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.29',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ideal.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ideal.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '0.5',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ideal.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '100000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ideal.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['AU', 'AT', 'BE', 'BG', 'CA', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GI', 'GR', 'HK', 'HU', 'IE', 'IT', 'JP', 'LV', 'LI', 'LT', 'LU', 'MT', 'MX', 'NL', 'NZ', 'NO', 'PL', 'PT', 'RO', 'SG', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'US'], //phpcs:ignore
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(
                                            ['AU', 'AT', 'BE', 'BG', 'CA', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GI', 'GR', 'HK', 'HU', 'IE', 'IT', 'JP', 'LV', 'LI', 'LT', 'LU', 'MT', 'MX', 'NL', 'NZ', 'NO', 'PL', 'PT', 'RO', 'SG', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'US'] //phpcs:ignore
                                        ),
                                    ]
                                ],
                                'ideal.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                        // IVY
                        [
                            'title' => 'Assistant.IvyTab',
                            'description' => 'Assistant.IvyTab',
                            'form' => [
                                'ivy.active' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverActiveLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'ivy.title' => [
                                    'type' => 'text',
                                    'defaultValue' => 'IVY',
                                    'options' => [
                                        'name' => 'Assistant.payeverTitleLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ivy.description' => [
                                    'type' => 'text',
                                    'defaultValue' => '',
                                    'options' => [
                                        'name' => 'Assistant.payeverDescriptionLabel',
                                        'required' => false,
                                    ]
                                ],
                                'ivy.redirect_method' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverRedirectMethod',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'ivy.accept_fee' => [
                                    'type' => 'select',
                                    'defaultValue' => '1',
                                    'options' => [
                                        'name' => 'Assistant.payeverAcceptFeeLabel',
                                        'required' => true,
                                        'listBoxValues' => [
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue0',
                                                'value' => '0'
                                            ],
                                            [
                                                'caption' => 'Assistant.payeverPossibleValue1',
                                                'value' => '1'
                                            ]
                                        ]
                                    ]
                                ],
                                'ivy.fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ivy.variable_fee' => [
                                    'type' => 'text',
                                    'defaultValue' => '0',
                                    'options' => [
                                        'name' => 'Assistant.payeverVariableFeeLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ivy.min_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '300',
                                    'options' => [
                                        'name' => 'Assistant.payeverMinOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ivy.max_order_total' => [
                                    'type' => 'text',
                                    'defaultValue' => '40000',
                                    'options' => [
                                        'name' => 'Assistant.payeverMaxOrderTotalLabel',
                                        'required' => true,
                                    ]
                                ],
                                'ivy.allowed_countries' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['FI'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCountries',
                                        'checkboxValues' => $this->getCountriesList(['FI']),
                                    ]
                                ],
                                'ivy.allowed_currencies' => [
                                    'type' => 'checkboxGroup',
                                    'defaultValue' => ['EUR'],
                                    'options' => [
                                        'name' => 'Assistant.payeverAllowedCurrencies',
                                        'checkboxValues' => $this->getCurrenciesList(['EUR']),
                                    ]
                                ],
                            ]
                        ],
                    ]
                ]
            ]
        ];
    }

    /**
     * Get Icon.
     * @return string
     */
    private function getIcon()
    {
        return pluginApp(Application::class)->getUrlPath('Payever') . '/images/logos/payever_dark.svg';
    }

    /**
     * We use this method to create a drop-down menu with all webstores
     * to configure our assistant for each client individually.
     */
    private function getWebstoreListForm()
    {
        if ($this->webstoreValues === null) {
            $webstores = $this->webstoreRepository->loadAll();

            /** @var Webstore $webstore */
            foreach ($webstores as $webstore) {
                $this->webstoreValues[] = [
                    'caption' => $webstore->name,
                    'value' => $webstore->storeIdentifier,
                ];
            }

            /** Sort the array for better usability. */
            usort($this->webstoreValues, function ($a, $b) {
                return ($a['value'] <=> $b['value']);
            });
        }

        return $this->webstoreValues;
    }

    /**
     * @return mixed
     */
    private function getMainWebstore()
    {
        return $this->webstoreRepository->findById(0)->storeIdentifier;
    }

    /**
     * Get Countries.
     *
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getCountriesList($countries = null)
    {
        $countriesList = $this->countryRepository->getCountriesList(true, ['names']);

        $results = [];
        foreach ($countriesList as $country) {
            $country = $this->countryRepository->getCountryById($country->id);

            $results[] = [
                'caption' => $country->name,
                'value' => $country->isoCode2
            ];
        }

        return $results;
    }

    /**
     * Get Default countries.
     *
     * @return array
     */
    private function getDefaultCountries()
    {
        $countries = $this->getCountriesList();

        return array_column($countries, 'value');
    }

    /**
     * Get Currencies.
     *
     * @return array
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function getCurrenciesList($currencies = null)
    {
        $results = [];
        $currenciesList = $this->currencyRepository->getCurrencyList();
        foreach ($currenciesList as $currency) {
            if (is_array($currencies) && in_array($currency->currency, $currencies)) {
                $results[] = [
                    'caption' => $currency->currency,
                    'value' => $currency->currency
                ];
            } else {
                $results[] = [
                    'caption' => $currency->currency,
                    'value' => $currency->currency
                ];
            }
        }

        return $results;
    }

    /**
     * Get Default currencies.
     *
     * @return string[]
     */
    private function getDefaultCurrencies()
    {
        $activeCurrencies = explode(
            ', ',
            $this->configRepository->get('currency.available_currencies', 'all')
        );

        if (in_array('all', $activeCurrencies, true)) {
            $currencies = $this->getCurrenciesList();

            return array_column($currencies, 'value');
        }

        return $activeCurrencies;
    }
}
