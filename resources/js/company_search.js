const PayeverHttpClient = {
    get(url, callback, contentType = 'application/json') {
        const request = this._createPreparedRequest('GET', url, contentType);

        return this._sendRequest(request, null, callback);
    },

    post(
        url,
        data,
        callback,
        contentType = 'application/json'
    ) {
        const request = this._createPreparedRequest('POST', url, contentType);

        return this._sendRequest(request, data, callback);
    },

    _createPreparedRequest(type, url, contentType) {
        this._request = new XMLHttpRequest();

        this._request.open(type, url);
        this._request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        if (contentType) {
            this._request.setRequestHeader('Content-type', contentType);
        }

        return this._request;
    },
    _sendRequest(request, data, callback) {
        if (callback) {
            request.addEventListener('loadend', () => {
                callback(request.responseText, request);
            });
        }

        request.send(data);

        return request;
    }
};

const PayeverDomAccess = {
    querySelector(parentElement, identifier) {
        if (parentElement) {
            return parentElement.querySelector(identifier);
        }

        return document.querySelector(identifier);
    },
    querySelectorAll(parentElement, identifier) {
        if (parentElement) {
            return parentElement.querySelectorAll(identifier);
        }

        return document.querySelectorAll(identifier);
    },
    getAttribute(element, attribute) {
        return element.getAttribute(attribute);
    }
};

const PayeverApiClient = {
    init(options) {
        this.storeApiClient = Object.assign({}, PayeverHttpClient)

        /**
         * API Endpoint to search for a address
         * @type {string}
         * @private
         */
        this.companyFindEndpoint = '/payment/payever/companySearch?term={value}&country={country}';

        this.companySaveEndpoint = '/payment/payever/company';
    },

    /**
     * This method can be used to find suggestions for the given value or get suggestions of a cluster
     *
     * @param value{string}
     * @param country{string}
     * @param callbackOptions{callbackOptions}
     * @return {XMLHttpRequest}
     */
    companyFind(value, country, callbackOptions) {
        let endpointUrl = this.companyFindEndpoint
            .replace('{value}', this._encode(value))
            .replace('{country}', country);

        return this._call(endpointUrl, callbackOptions);
    },

    company(data, callbackOptions) {
        return this.storeApiClient.post(
            this.companySaveEndpoint,
            data,
            (res) => this._handleCallResponse(res, callbackOptions),
        );
    },

    /**
     * @param item
     * @param callbackOptions
     */
    addressRetrieve(item, callbackOptions) {
        const response = JSON.stringify(item);

        return this._handleCallResponse(response, callbackOptions);
    },

    _encode(string) {
        string = string.replace('/', '-')
        return encodeURIComponent(string);
    },

    /**
     * Call the StoreApi Endpoint
     *
     * @param endpointUrl{string}
     * @param callbackOptions{callbackOptions}
     * @return {XMLHttpRequest}
     * @private
     */
    _call(endpointUrl, callbackOptions) {
        return this.storeApiClient.get(
            endpointUrl,
            (res) => this._handleCallResponse(res, callbackOptions),
        );
    },

    /**
     * Handle the response from the validation api
     *
     * @param res
     * @param callbackOptions{callbackOptions}
     * @private
     * @return void
     */
    _handleCallResponse(res, callbackOptions) {
        try {
            if (!res) {
                return;
            }
            const response = JSON.parse(res);

            // Do nothing if there is no response. This can happen, when the request is cancled by _abortValidation()
            if (!response) {
                throw new Error('Missing response.');
            }

            // Hide loading indicator
            if (callbackOptions.hideLoadingIndicator) {
                callbackOptions.hideLoadingIndicator();
            }

            // API (Payever Service) respond with error
            if (response.error) {
                throw new Error('API respond with error. Look in your log files (var/log) for more information.');
            }

            // The XHR request returned a error
            if (response.errors && response.errors.length > 0) {
                const statusCode = parseInt(response.errors[0].status, 10);

                if (statusCode === 404) {
                    // If api return a 404 status code the value does not match a mail address
                    return;
                }
                if (statusCode !== 200) {
                    // If api return an unknown status code (e.g. 500) we will set the field to valid
                    throw new Error('API respond with error. Look in your log files (var/log) for more information.');
                }
            }

            // Process correct api response
            if (callbackOptions.success) {
                callbackOptions.success(response);
            }
        } catch (e) {
            /* eslint-disable no-console */
            console.log('Error in PayeverApiClient::_handleCallResponse', e);
            /* eslint-enable no-console */
        }
    }
};

const PayeverCountryPicker = {
    options: {
        // Default country
        defaultCountry: "",
        // Position the selected flag inside or outside of the input
        defaultStyling: "inside",
        // Display only these countries
        onlyCountries: ["us", "gb", "de"],
        // The countries at the top of the list. Defaults to United States and United Kingdom
        preferredCountries: [],
        // localized country names e.g. { 'de': 'Deutschland' }
        localizedCountries: null,
        // Set the dropdown's width to be the same as the input. This is automatically enabled for small screens.
        responsiveDropdown: (document.querySelector('html').clientWidth < 768),
        //all contries
        allCountries: [{
            name: "Afghanistan (‫افغانستان‬‎)",
            iso2: "af"
        }, {
            name: "Åland Islands (Åland)",
            iso2: "ax"
        }, {
            name: "Albania (Shqipëri)",
            iso2: "al"
        }, {
            name: "Algeria (‫الجزائر‬‎)",
            iso2: "dz"
        }, {
            name: "American Samoa",
            iso2: "as"
        }, {
            name: "Andorra",
            iso2: "ad"
        }, {
            name: "Angola",
            iso2: "ao"
        }, {
            name: "Anguilla",
            iso2: "ai"
        }, {
            name: "Antarctica",
            iso2: "aq"
        }, {
            name: "Antigua and Barbuda",
            iso2: "ag"
        }, {
            name: "Argentina",
            iso2: "ar"
        }, {
            name: "Armenia (Հայաստան)",
            iso2: "am"
        }, {
            name: "Aruba",
            iso2: "aw"
        }, {
            name: "Australia",
            iso2: "au"
        }, {
            name: "Austria (Österreich)",
            iso2: "at"
        }, {
            name: "Azerbaijan (Azərbaycan)",
            iso2: "az"
        }, {
            name: "Bahamas",
            iso2: "bs"
        }, {
            name: "Bahrain (‫البحرين‬‎)",
            iso2: "bh"
        }, {
            name: "Bangladesh (বাংলাদেশ)",
            iso2: "bd"
        }, {
            name: "Barbados",
            iso2: "bb"
        }, {
            name: "Belarus (Беларусь)",
            iso2: "by"
        }, {
            name: "Belgium (België)",
            iso2: "be"
        }, {
            name: "Belize",
            iso2: "bz"
        }, {
            name: "Benin (Bénin)",
            iso2: "bj"
        }, {
            name: "Bermuda",
            iso2: "bm"
        }, {
            name: "Bhutan (འབྲུག)",
            iso2: "bt"
        }, {
            name: "Bolivia",
            iso2: "bo"
        }, {
            name: "Bosnia and Herzegovina (Босна и Херцеговина)",
            iso2: "ba"
        }, {
            name: "Botswana",
            iso2: "bw"
        }, {
            name: "Bouvet Island (Bouvetøya)",
            iso2: "bv"
        }, {
            name: "Brazil (Brasil)",
            iso2: "br"
        }, {
            name: "British Indian Ocean Territory",
            iso2: "io"
        }, {
            name: "British Virgin Islands",
            iso2: "vg"
        }, {
            name: "Brunei",
            iso2: "bn"
        }, {
            name: "Bulgaria (България)",
            iso2: "bg"
        }, {
            name: "Burkina Faso",
            iso2: "bf"
        }, {
            name: "Burundi (Uburundi)",
            iso2: "bi"
        }, {
            name: "Cambodia (កម្ពុជា)",
            iso2: "kh"
        }, {
            name: "Cameroon (Cameroun)",
            iso2: "cm"
        }, {
            name: "Canada",
            iso2: "ca"
        }, {
            name: "Cape Verde (Kabu Verdi)",
            iso2: "cv"
        }, {
            name: "Caribbean Netherlands",
            iso2: "bq"
        }, {
            name: "Cayman Islands",
            iso2: "ky"
        }, {
            name: "Central African Republic (République Centrafricaine)",
            iso2: "cf"
        }, {
            name: "Chad (Tchad)",
            iso2: "td"
        }, {
            name: "Chile",
            iso2: "cl"
        }, {
            name: "China (中国)",
            iso2: "cn"
        }, {
            name: "Christmas Island",
            iso2: "cx"
        }, {
            name: "Cocos (Keeling) Islands (Kepulauan Cocos (Keeling))",
            iso2: "cc"
        }, {
            name: "Colombia",
            iso2: "co"
        }, {
            name: "Comoros (‫جزر القمر‬‎)",
            iso2: "km"
        }, {
            name: "Congo (DRC) (Jamhuri ya Kidemokrasia ya Kongo)",
            iso2: "cd"
        }, {
            name: "Congo (Republic) (Congo-Brazzaville)",
            iso2: "cg"
        }, {
            name: "Cook Islands",
            iso2: "ck"
        }, {
            name: "Costa Rica",
            iso2: "cr"
        }, {
            name: "Côte d’Ivoire",
            iso2: "ci"
        }, {
            name: "Croatia (Hrvatska)",
            iso2: "hr"
        }, {
            name: "Cuba",
            iso2: "cu"
        }, {
            name: "Curaçao",
            iso2: "cw"
        }, {
            name: "Cyprus (Κύπρος)",
            iso2: "cy"
        }, {
            name: "Czech Republic (Česká republika)",
            iso2: "cz"
        }, {
            name: "Denmark (Danmark)",
            iso2: "dk"
        }, {
            name: "Djibouti",
            iso2: "dj"
        }, {
            name: "Dominica",
            iso2: "dm"
        }, {
            name: "Dominican Republic (República Dominicana)",
            iso2: "do"
        }, {
            name: "Ecuador",
            iso2: "ec"
        }, {
            name: "Egypt (‫مصر‬‎)",
            iso2: "eg"
        }, {
            name: "El Salvador",
            iso2: "sv"
        }, {
            name: "Equatorial Guinea (Guinea Ecuatorial)",
            iso2: "gq"
        }, {
            name: "Eritrea",
            iso2: "er"
        }, {
            name: "Estonia (Eesti)",
            iso2: "ee"
        }, {
            name: "Ethiopia",
            iso2: "et"
        }, {
            name: "Falkland Islands (Islas Malvinas)",
            iso2: "fk"
        }, {
            name: "Faroe Islands (Føroyar)",
            iso2: "fo"
        }, {
            name: "Fiji",
            iso2: "fj"
        }, {
            name: "Finland (Suomi)",
            iso2: "fi"
        }, {
            name: "France",
            iso2: "fr"
        }, {
            name: "French Guiana (Guyane française)",
            iso2: "gf"
        }, {
            name: "French Polynesia (Polynésie française)",
            iso2: "pf"
        }, {
            name: "French Southern Territories (Terres australes françaises)",
            iso2: "tf"
        }, {
            name: "Gabon",
            iso2: "ga"
        }, {
            name: "Gambia",
            iso2: "gm"
        }, {
            name: "Georgia (საქართველო)",
            iso2: "ge"
        }, {
            name: "Germany (Deutschland)",
            iso2: "de"
        }, {
            name: "Ghana (Gaana)",
            iso2: "gh"
        }, {
            name: "Gibraltar",
            iso2: "gi"
        }, {
            name: "Greece (Ελλάδα)",
            iso2: "gr"
        }, {
            name: "Greenland (Kalaallit Nunaat)",
            iso2: "gl"
        }, {
            name: "Grenada",
            iso2: "gd"
        }, {
            name: "Guadeloupe",
            iso2: "gp"
        }, {
            name: "Guam",
            iso2: "gu"
        }, {
            name: "Guatemala",
            iso2: "gt"
        }, {
            name: "Guernsey",
            iso2: "gg"
        }, {
            name: "Guinea (Guinée)",
            iso2: "gn"
        }, {
            name: "Guinea-Bissau (Guiné Bissau)",
            iso2: "gw"
        }, {
            name: "Guyana",
            iso2: "gy"
        }, {
            name: "Haiti",
            iso2: "ht"
        }, {
            name: "Heard Island and Mcdonald Islands",
            iso2: "hm"
        }, {
            name: "Honduras",
            iso2: "hn"
        }, {
            name: "Hong Kong (香港)",
            iso2: "hk"
        }, {
            name: "Hungary (Magyarország)",
            iso2: "hu"
        }, {
            name: "Iceland (Ísland)",
            iso2: "is"
        }, {
            name: "India (भारत)",
            iso2: "in"
        }, {
            name: "Indonesia",
            iso2: "id"
        }, {
            name: "Iran (‫ایران‬‎)",
            iso2: "ir"
        }, {
            name: "Iraq (‫العراق‬‎)",
            iso2: "iq"
        }, {
            name: "Ireland",
            iso2: "ie"
        }, {
            name: "Isle of Man",
            iso2: "im"
        }, {
            name: "Israel (‫ישראל‬‎)",
            iso2: "il"
        }, {
            name: "Italy (Italia)",
            iso2: "it"
        }, {
            name: "Jamaica",
            iso2: "jm"
        }, {
            name: "Japan (日本)",
            iso2: "jp"
        }, {
            name: "Jersey",
            iso2: "je"
        }, {
            name: "Jordan (‫الأردن‬‎)",
            iso2: "jo"
        }, {
            name: "Kazakhstan (Казахстан)",
            iso2: "kz"
        }, {
            name: "Kenya",
            iso2: "ke"
        }, {
            name: "Kiribati",
            iso2: "ki"
        }, {
            name: "Kosovo (Kosovë)",
            iso2: "xk"
        }, {
            name: "Kuwait (‫الكويت‬‎)",
            iso2: "kw"
        }, {
            name: "Kyrgyzstan (Кыргызстан)",
            iso2: "kg"
        }, {
            name: "Laos (ລາວ)",
            iso2: "la"
        }, {
            name: "Latvia (Latvija)",
            iso2: "lv"
        }, {
            name: "Lebanon (‫لبنان‬‎)",
            iso2: "lb"
        }, {
            name: "Lesotho",
            iso2: "ls"
        }, {
            name: "Liberia",
            iso2: "lr"
        }, {
            name: "Libya (‫ليبيا‬‎)",
            iso2: "ly"
        }, {
            name: "Liechtenstein",
            iso2: "li"
        }, {
            name: "Lithuania (Lietuva)",
            iso2: "lt"
        }, {
            name: "Luxembourg",
            iso2: "lu"
        }, {
            name: "Macau (澳門)",
            iso2: "mo"
        }, {
            name: "Macedonia (FYROM) (Македонија)",
            iso2: "mk"
        }, {
            name: "Madagascar (Madagasikara)",
            iso2: "mg"
        }, {
            name: "Malawi",
            iso2: "mw"
        }, {
            name: "Malaysia",
            iso2: "my"
        }, {
            name: "Maldives",
            iso2: "mv"
        }, {
            name: "Mali",
            iso2: "ml"
        }, {
            name: "Malta",
            iso2: "mt"
        }, {
            name: "Marshall Islands",
            iso2: "mh"
        }, {
            name: "Martinique",
            iso2: "mq"
        }, {
            name: "Mauritania (‫موريتانيا‬‎)",
            iso2: "mr"
        }, {
            name: "Mauritius (Moris)",
            iso2: "mu"
        }, {
            name: "Mayotte",
            iso2: "yt"
        }, {
            name: "Mexico (México)",
            iso2: "mx"
        }, {
            name: "Micronesia",
            iso2: "fm"
        }, {
            name: "Moldova (Republica Moldova)",
            iso2: "md"
        }, {
            name: "Monaco",
            iso2: "mc"
        }, {
            name: "Mongolia (Монгол)",
            iso2: "mn"
        }, {
            name: "Montenegro (Crna Gora)",
            iso2: "me"
        }, {
            name: "Montserrat",
            iso2: "ms"
        }, {
            name: "Morocco (‫المغرب‬‎)",
            iso2: "ma"
        }, {
            name: "Mozambique (Moçambique)",
            iso2: "mz"
        }, {
            name: "Myanmar (Burma) (မြန်မာ)",
            iso2: "mm"
        }, {
            name: "Namibia (Namibië)",
            iso2: "na"
        }, {
            name: "Nauru",
            iso2: "nr"
        }, {
            name: "Nepal (नेपाल)",
            iso2: "np"
        }, {
            name: "Netherlands (Nederland)",
            iso2: "nl"
        }, {
            name: "New Caledonia (Nouvelle-Calédonie)",
            iso2: "nc"
        }, {
            name: "New Zealand",
            iso2: "nz"
        }, {
            name: "Nicaragua",
            iso2: "ni"
        }, {
            name: "Niger (Nijar)",
            iso2: "ne"
        }, {
            name: "Nigeria",
            iso2: "ng"
        }, {
            name: "Niue",
            iso2: "nu"
        }, {
            name: "Norfolk Island",
            iso2: "nf"
        }, {
            name: "North Korea (조선 민주주의 인민 공화국)",
            iso2: "kp"
        }, {
            name: "Northern Mariana Islands",
            iso2: "mp"
        }, {
            name: "Norway (Norge)",
            iso2: "no"
        }, {
            name: "Oman (‫عُمان‬‎)",
            iso2: "om"
        }, {
            name: "Pakistan (‫پاکستان‬‎)",
            iso2: "pk"
        }, {
            name: "Palau",
            iso2: "pw"
        }, {
            name: "Palestine (‫فلسطين‬‎)",
            iso2: "ps"
        }, {
            name: "Panama (Panamá)",
            iso2: "pa"
        }, {
            name: "Papua New Guinea",
            iso2: "pg"
        }, {
            name: "Paraguay",
            iso2: "py"
        }, {
            name: "Peru (Perú)",
            iso2: "pe"
        }, {
            name: "Philippines",
            iso2: "ph"
        }, {
            name: "Pitcairn Islands",
            iso2: "pn"
        }, {
            name: "Poland (Polska)",
            iso2: "pl"
        }, {
            name: "Portugal",
            iso2: "pt"
        }, {
            name: "Puerto Rico",
            iso2: "pr"
        }, {
            name: "Qatar (‫قطر‬‎)",
            iso2: "qa"
        }, {
            name: "Réunion (La Réunion)",
            iso2: "re"
        }, {
            name: "Romania (România)",
            iso2: "ro"
        }, {
            name: "Russia (Россия)",
            iso2: "ru"
        }, {
            name: "Rwanda",
            iso2: "rw"
        }, {
            name: "Saint Barthélemy (Saint-Barthélemy)",
            iso2: "bl"
        }, {
            name: "Saint Helena",
            iso2: "sh"
        }, {
            name: "Saint Kitts and Nevis",
            iso2: "kn"
        }, {
            name: "Saint Lucia",
            iso2: "lc"
        }, {
            name: "Saint Martin (Saint-Martin (partie française))",
            iso2: "mf"
        }, {
            name: "Saint Pierre and Miquelon (Saint-Pierre-et-Miquelon)",
            iso2: "pm"
        }, {
            name: "Saint Vincent and the Grenadines",
            iso2: "vc"
        }, {
            name: "Samoa",
            iso2: "ws"
        }, {
            name: "San Marino",
            iso2: "sm"
        }, {
            name: "São Tomé and Príncipe (São Tomé e Príncipe)",
            iso2: "st"
        }, {
            name: "Saudi Arabia (‫المملكة العربية السعودية‬‎)",
            iso2: "sa"
        }, {
            name: "Senegal (Sénégal)",
            iso2: "sn"
        }, {
            name: "Serbia (Србија)",
            iso2: "rs"
        }, {
            name: "Seychelles",
            iso2: "sc"
        }, {
            name: "Sierra Leone",
            iso2: "sl"
        }, {
            name: "Singapore",
            iso2: "sg"
        }, {
            name: "Sint Maarten",
            iso2: "sx"
        }, {
            name: "Slovakia (Slovensko)",
            iso2: "sk"
        }, {
            name: "Slovenia (Slovenija)",
            iso2: "si"
        }, {
            name: "Solomon Islands",
            iso2: "sb"
        }, {
            name: "Somalia (Soomaaliya)",
            iso2: "so"
        }, {
            name: "South Africa",
            iso2: "za"
        }, {
            name: "South Georgia & South Sandwich Islands",
            iso2: "gs"
        }, {
            name: "South Korea (대한민국)",
            iso2: "kr"
        }, {
            name: "South Sudan (‫جنوب السودان‬‎)",
            iso2: "ss"
        }, {
            name: "Spain (España)",
            iso2: "es"
        }, {
            name: "Sri Lanka (ශ්‍රී ලංකාව)",
            iso2: "lk"
        }, {
            name: "Sudan (‫السودان‬‎)",
            iso2: "sd"
        }, {
            name: "Suriname",
            iso2: "sr"
        }, {
            name: "Svalbard and Jan Mayen (Svalbard og Jan Mayen)",
            iso2: "sj"
        }, {
            name: "Swaziland",
            iso2: "sz"
        }, {
            name: "Sweden (Sverige)",
            iso2: "se"
        }, {
            name: "Switzerland (Schweiz)",
            iso2: "ch"
        }, {
            name: "Syria (‫سوريا‬‎)",
            iso2: "sy"
        }, {
            name: "Taiwan (台灣)",
            iso2: "tw"
        }, {
            name: "Tajikistan",
            iso2: "tj"
        }, {
            name: "Tanzania",
            iso2: "tz"
        }, {
            name: "Thailand (ไทย)",
            iso2: "th"
        }, {
            name: "Timor-Leste",
            iso2: "tl"
        }, {
            name: "Togo",
            iso2: "tg"
        }, {
            name: "Tokelau",
            iso2: "tk"
        }, {
            name: "Tonga",
            iso2: "to"
        }, {
            name: "Trinidad and Tobago",
            iso2: "tt"
        }, {
            name: "Tunisia (‫تونس‬‎)",
            iso2: "tn"
        }, {
            name: "Turkey (Türkiye)",
            iso2: "tr"
        }, {
            name: "Turkmenistan",
            iso2: "tm"
        }, {
            name: "Turks and Caicos Islands",
            iso2: "tc"
        }, {
            name: "Tuvalu",
            iso2: "tv"
        }, {
            name: "Uganda",
            iso2: "ug"
        }, {
            name: "Ukraine (Україна)",
            iso2: "ua"
        }, {
            name: "United Arab Emirates (‫الإمارات العربية المتحدة‬‎)",
            iso2: "ae"
        }, {
            name: "United Kingdom",
            iso2: "gb"
        }, {
            name: "United States",
            iso2: "us"
        }, {
            name: "U.S. Minor Outlying Islands",
            iso2: "um"
        }, {
            name: "U.S. Virgin Islands",
            iso2: "vi"
        }, {
            name: "Uruguay",
            iso2: "uy"
        }, {
            name: "Uzbekistan (Oʻzbekiston)",
            iso2: "uz"
        }, {
            name: "Vanuatu",
            iso2: "vu"
        }, {
            name: "Vatican City (Città del Vaticano)",
            iso2: "va"
        }, {
            name: "Venezuela",
            iso2: "ve"
        }, {
            name: "Vietnam (Việt Nam)",
            iso2: "vn"
        }, {
            name: "Wallis and Futuna",
            iso2: "wf"
        }, {
            name: "Western Sahara (‫الصحراء الغربية‬‎)",
            iso2: "eh"
        }, {
            name: "Yemen (‫اليمن‬‎)",
            iso2: "ye"
        }, {
            name: "Zambia",
            iso2: "zm"
        }, {
            name: "Zimbabwe",
            iso2: "zw"
        }],
        ns: '.countrySelect1',
        keys: {
            UP: 38,
            DOWN: 40,
            ENTER: 13,
            ESC: 27,
            BACKSPACE: 8,
            PLUS: 43,
            SPACE: 32,
            A: 65,
            Z: 90
        }
    },
    selectedCountryCode: '',
    eventListeners: {
        country_li_mouseover: {},
        country_li_click: {},
        body_click: {},
    },

    init(companySearch, el, options) {
        this.companySearch = companySearch;

        if (options && options.onlyCountries && options.onlyCountries.length) {
            this.options.onlyCountries = options.onlyCountries;
        }

        if (options && options.defaultCountry) {
            this.options.defaultCountry = options.defaultCountry;
        }

        this.el = el;
        this._init_widget();
    },

    _init_widget() {
        // Process all the data: onlyCountries, preferredCountries, defaultCountry etc
        this._processCountryData();

        // Generate the markup
        this._generateMarkup();

        // Set the initial state of the input value and the selected flag
        this._setInitialState();

        // Start all of the event listeners: input keyup, selectedFlag click
        this._initListeners();

        // Get auto country.
        this._initAutoCountry();

        // Keep track as the user types
        this.typedLetters = "";

        return this;
    },

    /**
     * Prepares all of the country data, including onlyCountries, preferredCountries and defaultCountry options
     */
    _processCountryData() {
        // set the instances country data objects
        this._setInstanceCountryData();
        // set the preferredCountries property
        this._setPreferredCountries();
        // translate countries according to localizedCountries option
        if (this.options.localizedCountries) this._translateCountriesByLocale();
        // sort countries by name
        if (this.options.onlyCountries.length || this.options.localizedCountries) {
            this.countries.sort(this._countryNameSort);
        }
    },

    /**
     * Processes onlyCountries array if present
     */
    _setInstanceCountryData() {
        const me = this;
        const newCountries = [];

        if (this.options.onlyCountries.length) {
            this.options.onlyCountries.forEach(function (countryCode) {
                const countryData = me._getCountryData(countryCode, true);
                if (countryData) {
                    newCountries.push(countryData);
                }
            });

            this.countries = newCountries;
        } else {
            this.countries = this.options.allCountries;
        }
    },

    /**
     * Processes preferred countries - iterate through the preferences, fetching the country data for each one
     */
    _setPreferredCountries() {
        const me = this;
        this.preferredCountries = [];
        this.options.preferredCountries.forEach(el => {
            const countryData = me._getCountryData(el, false);
            if (countryData) {
                me.preferredCountries.push(countryData);
            }
        });
    },

    /**
     * Translates Countries by object literal provided on config
     */
    _translateCountriesByLocale() {
        for (let i = 0; i < this.countries.length; i++) {
            const iso = this.countries[i].iso2.toLowerCase();
            if (this.options.localizedCountries.hasOwnProperty(iso)) {
                this.countries[i].name = this.options.localizedCountries[iso];
            }
        }
    },

    /**
     * Sorts by country name
     *
     * @param a
     * @param b
     * @returns {number}
     */
    _countryNameSort(a, b) {
        return a.name.localeCompare(b.name);
    },

    /**
     * Generates all of the markup for the plugin: the selected flag overlay, and the dropdown
     */
    _generateMarkup() {
        // Country input
        this.countryInput = this.el;
        this.el.parentNode.style.overflow = "visible";

        // containers (mostly for positioning)
        let mainClass = "country-select";
        if (this.options.defaultStyling) {
            mainClass += " " + this.options.defaultStyling;
        }

        const wrapper = document.createElement('div');
        wrapper.setAttribute("class", mainClass);
        this.countryInput.parentNode.insertBefore(wrapper, this.countryInput);
        wrapper.appendChild(this.countryInput);

        let flagsContainer = document.createElement("div");
        flagsContainer.setAttribute("class", "flag-dropdown");
        this.countryInput.after(
            flagsContainer
        );

        let selectedFlag = document.createElement("div");
        selectedFlag.setAttribute("class", "selected-flag");
        flagsContainer.append(selectedFlag);

        this.selectedFlagInner = document.createElement("div");
        this.selectedFlagInner.setAttribute("class", "flag");
        selectedFlag.append(this.selectedFlagInner);

        let arrow = document.createElement("div");
        arrow.setAttribute("class", "arrow");
        selectedFlag.append(arrow);

        this.countryList = document.createElement("ul");
        this.countryList.setAttribute("class", "country-list v-hide");
        flagsContainer.append(this.countryList);

        if (!this.preferredCountries) {
            this._appendListItems(this.preferredCountries, "preferred");

            let divider = document.createElement("li");
            divider.setAttribute("class", "arrow");
            this.countryList.append(divider);
        }
        this._appendListItems(this.countries, "");

        // Add the hidden input for the country code
        this.countryCodeInput = document.getElementById("country_selector_code");

        if (!this.countryCodeInput) {
            this.countryCodeInput = document.createElement("input");
            this.countryCodeInput.setAttribute("type", "hidden");
            this.countryCodeInput.setAttribute("id", "country_selector_code");
            this.countryCodeInput.setAttribute("name", "country_selector_code");
            this.countryInput.after(
                this.countryCodeInput
            );
        }

        // now we can grab the dropdown height, and hide it properly
        this.dropdownHeight = this.countryList.getBoundingClientRect().height;
        this.countryList.classList.remove("v-hide");
        this.countryList.classList.add("hide");
        // this is useful in lots of places
        this.countryListItems = this.countryList.querySelectorAll('.country');
    },

    /**
     * Adds a country <li> to the countryList <ul> container
     *
     * @param countries
     * @param className
     */
    _appendListItems(countries, className) {
        // Generate DOM elements as a large temp string, so that there is only
        // one DOM insert event
        let tmp = "";
        // for each country
        countries.forEach(el => {
            tmp += '<li class="country ' + className + '" data-country-code="' + el.iso2 + '">';
            // add the flag
            tmp += '<div class="flag ' + el.iso2 + '"></div>';
            // and the country name
            tmp += '<span class="country-name">' + el.name + '</span>';
            // close the list item
            tmp += '</li>';
        });
        this.countryList.innerHTML += tmp;
    },

    /**
     * Sets the initial state of the input value and the selected flag
     */
    _setInitialState() {
        const flagIsSet = false;

        // If the country code input is pre-populated, update the name and the selected flag
        const selectedCode = this.countryCodeInput.value;
        if (selectedCode) {
            this.selectCountry(selectedCode);
        }
        if (!flagIsSet) {
            // flag is not set, so set to the default country
            let defaultCountry;
            // check the defaultCountry option, else fall back to the first in the list
            if (this.options.defaultCountry) {
                defaultCountry = this._getCountryData(this.options.defaultCountry, false);
                // Did we not find the requested default country?
                if (!defaultCountry) {
                    defaultCountry = this.preferredCountries.length ? this.preferredCountries[0] : this.countries[0];
                }
            } else {
                defaultCountry = this.preferredCountries.length ? this.preferredCountries[0] : this.countries[0];
            }
            this.defaultCountry = defaultCountry.iso2;
        }
    },

    _initListeners() {
        const me = this;

        // toggle country dropdown on click
        const selectedFlag = this.selectedFlagInner.parentElement;
        selectedFlag.addEventListener('click', function (e) {
            if (me.countryList.classList.contains("hide") && !me.countryInput.getAttribute("disabled")) {
                me._showDropdown();
            }
        });
    },

    /**
     * Sets default country
     */
    _initAutoCountry() {
        if (this.defaultCountry) {
            this.selectCountry(this.defaultCountry);
        }
    },

    /**
     * Focus input and put the cursor at the end
     */
    _focus() {
        this.countryInput.focus();
        if (this.countryInput.setSelectionRange) {
            const len = this.countryInput.value.length;
            this.countryInput.setSelectionRange(len, len);
        }

    },

    /**
     * Decides where to position dropdown (depends on position within viewport, and scroll)
     */
    _setDropdownPosition() {
        const rect = this.countryInput.getBoundingClientRect(),
            inputTop = rect.top + window.scrollY,
            windowTop = document.scrollingElement.scrollTop,
            dropdownFitsBelow = inputTop + this.countryInput.outerHeight + this.dropdownHeight < windowTop + window.innerHeight,
            dropdownFitsAbove = inputTop - this.dropdownHeight > windowTop;
        // dropdownHeight - 1 for border
        this.countryList.style.top = !dropdownFitsBelow && dropdownFitsAbove ? '-' + (this.dropdownHeight - 1) + 'px' : '';
    },

    /**
     * Shows the dropdown
     */
    _showDropdown() {
        this._setDropdownPosition();
        // update highlighting and scroll to active list item
        let activeListItem = PayeverDomAccess.querySelector(this.countryList, 'li.active');
        if (!activeListItem) {
            activeListItem = PayeverDomAccess.querySelector(this.countryList, 'li.country');
        }
        this._highlightListItem(activeListItem);
        // show it
        this.countryList.classList.remove('hide', 'v-hide');
        this._scrollTo(activeListItem);
        // bind all the dropdown-related listeners: mouseover, click, click-off, keydown
        this._bindDropdownListeners();
        // update the arrow
        this.selectedFlagInner.parentElement.querySelectorAll(".arrow").forEach(el => {
            el.classList.add("up");
        });
    },

    /**
     * we only bind dropdown listeners when the dropdown is open
     */
    _bindDropdownListeners() {
        const me = this;

        this.eventListeners.country_li_mouseover = function(event) {
            me._highlightListItem(event.target)
        }.bind(me);
        this.eventListeners.country_li_click = function(event) {
            me._selectListItem(event.target)
        }.bind(me);
        this.countryList.querySelectorAll('li.country').forEach(el => {
            el.addEventListener('mouseover', me.eventListeners.country_li_mouseover);
            el.addEventListener('click', me.eventListeners.country_li_click);
        });

        // click off to close
        // (except when this initial opening click is bubbling up)
        // we cannot just stopPropagation as it may be needed to close another instance
        let isOpening = true;
        this.eventListeners.body_click = function (event) {
            event.preventDefault();
            if (!isOpening) {
                me._closeDropdown();
            }
            isOpening = false;
        }.bind(me);
        document.body.addEventListener('click', me.eventListeners.body_click);

        // Listen for up/down scrolling, enter to select, or letters to jump to country name.
        // Use keydown as keypress doesn't fire for non-char keys and we want to catch if they
        // just hit down and hold it to scroll down (no keyup event).
        // Listen on the document because that's where key events are triggered if no input has focus
        me.keydownEventHandler = me._keydown.bind(me);
        window.addEventListener('keydown', me.keydownEventHandler);
    },

    _keydown(event) {
        // prevent down key from scrolling the whole page,
        // and enter key from submitting a form etc
        event.preventDefault();
        if (event.which === this.options.keys.UP || event.which === this.options.keys.DOWN) {
            // up and down to navigate
            this._handleUpDownKey(event.which);
        } else if (event.which === this.options.keys.ENTER) {
            // enter to select
            this._handleEnterKey();
        } else if (event.which === this.options.keys.ESC) {
            // esc to close
            this._closeDropdown();
        } else if (event.which >= this.options.keys.A && event.which <= this.options.keys.Z || event.which === this.options.keys.SPACE) {
            this.typedLetters += String.fromCharCode(event.which);
            this._filterCountries(this.typedLetters);
        } else if (event.which === this.options.keys.BACKSPACE) {
            this.typedLetters = this.typedLetters.slice(0, -1);
            this._filterCountries(this.typedLetters);
        }
    },

    /**
     * Highlight the next/prev item in the list (and ensure it is visible)
     *
     * @param key
     */
    _handleUpDownKey(key) {
        // Convert `HTMLCollection` to `Array`
        const countryItems = Array.from(this.countryList.children);
        const me = this;
        let found = null;
        // Check for` `li` items
        countryItems.forEach(function (countryItem) {
            if (found) {
                // Skip if item is found
                return;
            }

            if (countryItem.classList.contains('highlight')) {
                let next = key === me.options.keys.UP ? countryItem.previousElementSibling : countryItem.nextElementSibling;
                // skip the divider
                if (next && next.classList.contains('divider')) {
                    next = key === me.options.keys.UP ? next.previousElementSibling : next.nextElementSibling;
                }

                found = next;
                next && me._highlightListItem(next);
                next && me._scrollTo(next);
            }
        });
    },

    /**
     * Selects the currently highlighted item
     */
    _handleEnterKey() {
        // Convert `HTMLCollection` to `Array`
        const countryItems = Array.from(this.countryList.children);
        const me = this;
        let found = null;
        // Check for` `li` items
        countryItems.forEach(function (countryItem) {
            if (found) {
                // Skip if item is found
                return;
            }

            found = countryItem;
            countryItem && me._selectListItem(countryItem);
        });
    },

    /**
     * @param letters
     */
    _filterCountries(letters) {
        const countryItems = Array.from(this.countryListItems);
        const countries = countryItems.filter(function (countryListItem) {
            return (!countryListItem.classList.contains('preferred') && countryListItem.textContent.toLowerCase().indexOf(letters.toLowerCase()) > -1);
        });

        const me = this;
        let found = null;
        countries.forEach(function (countryListItem) {
            if (found) { return; } // Skip if item is found

            let highlighted = countries.filter(function (countryListItem) {
                return (countryListItem.classList.contains('highlighted'));
            });

            found = countryListItem;

            // if one is already highlighted, then we want the next one
            if (highlighted.length > 0 &&
                highlighted[0].nextElementSibling &&
                highlighted[0].nextElementSibling.toLowerCase().indexOf(letters.toLowerCase()) > -1
            ) {
                found = highlighted[0].nextElementSibling
            }

            // update highlighting and scroll
            me._highlightListItem(found);
            me._scrollTo(found);
        });
    },


    /**
     * Removes highlighting from other list items and highlight the given item
     * @param listItem
     */
    _highlightListItem(listItem) {
        this.countryListItems.forEach(el => {
            el.classList.remove("highlight");
        });
        if (listItem) {
            listItem.classList.add("highlight");
        }
    },

    /**
     * Finds the country data for the given country code
     * the ignoreOnlyCountriesOption is only used during init() while parsing the onlyCountries array
     * @param countryCode
     * @param ignoreOnlyCountriesOption
     */
    _getCountryData(countryCode, ignoreOnlyCountriesOption) {
        const countryList = ignoreOnlyCountriesOption ? this.options.allCountries : this.countries;
        for (let value of countryList) {
            if (value.iso2 === countryCode) {
                return value;
            }
        }
        return null;
    },

    /**
     * Updates the selected flag and the active list item
     *
     * @param countryCode
     * @returns {boolean}
     */
    _selectFlag(countryCode) {
        if (!countryCode) {
            return false;
        }
        this.selectedFlagInner.setAttribute("class", "flag " + countryCode);
        // update the title attribute
        const countryData = this._getCountryData(countryCode);
        this.selectedFlagInner.parentElement.setAttribute("title", countryData.name);

        // update the active list item
        const listItem = this.countryInput.parentElement.querySelectorAll(".flag." + countryCode).item(0).parentElement;
        this.countryListItems.forEach(el => {
            el.classList.remove("active");
        });
        listItem.classList.add("active");

        const companySearch = this.getCompanySearch();
        companySearch.getLastSearch = '';
        companySearch.doSearch();
        this.selectedCountryCode = countryCode;
    },

    /**
     * Called when the user selects a list item from the dropdown
     *
     * @param listItem
     */
    _selectListItem(listItem) {
        // update selected flag and active list item
        const countryCode = listItem.getAttribute("data-country-code");
        this._selectFlag(countryCode);
        this._closeDropdown();
        // update input value
        this._updateName(countryCode);

        this.countryInput.dispatchEvent(new Event("change"));
        this.countryCodeInput.dispatchEvent(new Event("change"));
        // focus the input
        this._focus();
    },

    /**
     * Closes the dropdown and unbind any listeners
     */
    _closeDropdown() {
        this.countryList.classList.add("hide");
        // update the arrow
        this.selectedFlagInner.parentElement.querySelectorAll(".arrow").forEach(el => {
            el.classList.remove("up");
        });
        const me = this;
        this.countryList.querySelectorAll('li.country').forEach(el => {
            el.removeEventListener("mouseover", me.eventListeners.country_li_mouseover);
            el.removeEventListener("click", me.eventListeners.country_li_click);
        });
        document.body.removeEventListener("click", me.eventListeners.body_click);
        window.removeEventListener('keydown', me.keydownEventHandler);
    },

    /**
     * Checks if an element is visible within its container, else scroll until it is
     *
     * @param element
     * @private
     */
    _scrollTo(element) {
        if (!element) {
            return;
        }

        element.scrollIntoView(false);
    },

    /**
     * Replaces any existing country name with the new one
     *
     * @param countryCode
     */
    _updateName(countryCode) {
        this.countryCodeInput.value = countryCode;
        this.countryCodeInput.dispatchEvent(new Event('change'));
    },

    /**
     * Returns the company search js plugin
     *
     * @return {PayeverCompanySearch}
     */
    getCompanySearch() {
        return this.companySearch;
    },

    /**
     * Updates the selected flag
     * @param countryCode
     */
    selectCountry(countryCode) {
        countryCode = countryCode.toLowerCase();
        // check if already selected
        if (!this.selectedFlagInner.classList.contains(countryCode)) {
            this._selectFlag(countryCode);
            this._updateName(countryCode);
        }
    }
};

const PayeverCompanyAutocomplete = {
    options: {
        /**
         * Selector to find the loading indicator
         * @type string
         */
        selectorLoadingIndicator: '.payever-company-autocomplete-loading',

        payeverCompanyInputSelector: '[name*=payever_company_selected]',
    },
    selectedCompany: null,

    init(parentElement, options) {
        this.companyElement = parentElement;
        this.initAutocompleteDOM();
        this.payeverApiClient = Object.assign({}, PayeverApiClient);
        this.payeverApiClient.init(options);
    },

    getAutocompleteItemsContainer() {
        return PayeverDomAccess.querySelector(this.el, '.payever-company-autocomplete-items');
    },

    getSelectedCompany() {
        return this.selectedCompany;
    },

    /**
     * Set the address callback. Will be fired if the user select a specific address
     *
     * @param addressCallback{function(addressDetails)}
     */
    setAddressCallback(addressCallback) {
        this.addressCallback = addressCallback;
    },

    /**
     * Start the search for an address
     *
     * @param needle{string}
     * @param country{string}
     */
    search(needle, country) {
        this.abortLastApiRequest();

        this.searchTimeoutId = window.setTimeout(() => {
            // prepare autocomplete dropdown for new search
            this.clearSearchItems();
            this.showLoadingIndicator();
            this.show();

            // fire request to api
            this.lastRequest = this.payeverApiClient.companyFind(
                needle,
                country,
                {
                    success: ((response) => {
                        const me = this;
                        response.forEach(function (value) {
                            me.addSearchItem(value);
                        });
                    }),
                    hideLoadingIndicator: this.hideLoadingIndicator.bind(this),
                    showLoadingIndicator: this.showLoadingIndicator.bind(this),
                },
            );
        }, 50);
    },

    /**
     * @param item{searchResultItem}
     * @param success{function(addressDetails)}
     */
    retrieveAddressDetails(item, success) {
        this.clearSearchItems();
        this.showLoadingIndicator();
        this.show();

        this.lastRequest = this.payeverApiClient.addressRetrieve(
            item,
            {
                success,
                hideLoadingIndicator: this.hideLoadingIndicator.bind(this),
                showLoadingIndicator: this.showLoadingIndicator.bind(this),
            },
        );
    },

    /**
     * Abort a running search
     */
    abortLastApiRequest() {
        // clear the input timeout
        clearTimeout(this.searchTimeoutId);

        // stop running api requests
        if (this.lastRequest instanceof XMLHttpRequest) {
            this.lastRequest.abort();
        }
    },

    /**
     * Add a search item to the autocomplete dropdown. The item contains one of the following keys:
     *
     * @param item{searchResultItem}
     * @return void
     */
    addSearchItem(item) {
        const typeAsClass = item.Type === 'Address' ? 'is-single' : 'is-group';
        const items_count = PayeverDomAccess.querySelectorAll(
            this.getAutocompleteItemsContainer(),
            '.payever-company-autocomplete-item',
            false
        ).length;
        const itemId = 'search_company_' + items_count;

        let street, street_number, post_code, city = '';

        if (typeof item.address !== "undefined") {
            street = item.address.street_name;
            street_number = (typeof item.address.street_number !== "undefined") ? item.address.street_number : '';
            post_code = item.address.post_code;
            city = item.address.city;
        }

        // Add item to DOM
        const template = document.createRange().createContextualFragment(`
            <li class="payever-company-autocomplete-item ${typeAsClass}" id="${itemId}"> 
                <a class="payever-company-autocomplete-item-link ${typeAsClass}" href="javascript:void(0)">
                    ${item.name},
                    <span class="payever-company-autocomplete-item-link-secondary-text"> -
                        ${street} ${street_number}, ${post_code}, ${city} 
                    </span>
                </a>
            </li>
        `);
        this.getAutocompleteItemsContainer().append(template);

        const itemElement = PayeverDomAccess.querySelector(this.getAutocompleteItemsContainer(), '#' + itemId, false);

        // Add click event in the added item
        let self = this;
        itemElement.addEventListener('click', function (event) {
            event.preventDefault();
            self.selectedCompany = item
            self._handleSearchItemClick(item);

            const companyHiddenInput = PayeverDomAccess.querySelector(self.getAutocompleteItemsContainer().parentNode, self.options.payeverCompanyInputSelector);

            if (companyHiddenInput) {
                companyHiddenInput.value = JSON.stringify(item);
            }
        })
    },

    /**
     * Handle a click on a search item in the autocomplete dropdown
     * @param item{searchResultItem}
     * @private
     */
    _handleSearchItemClick(item) {
        this.retrieveAddressDetails(item, (address) => {
            this.addressCallback(address);
            this.hide();
        });
    },

    /**
     * Remove all search result items
     */
    clearSearchItems() {
        let elements = PayeverDomAccess.querySelectorAll(this.getAutocompleteItemsContainer(), 'li');
        if (elements) {
            elements.forEach(element => element.remove());
        }
    },

    /**
     * Show autocomplete dropdown
     * @return void
     */
    show() {
        this.el.style.display = 'block';
    },

    /**
     * Hide autocomplete dropdown
     * @return void
     */
    hide() {
        this.el.style.display = 'none';
    },

    /**
     * Show/Hide the loading indicator
     */
    showLoadingIndicator() {
        let indicator = PayeverDomAccess.querySelector(this.companyElement, this.options.selectorLoadingIndicator);
        if (indicator) {
            indicator.style.display = 'block';
        }
    },

    initAutocompleteDOM() {
        const autocomplete = document.createElement("div");
        autocomplete.setAttribute('class', 'payever-company-autocomplete');
        autocomplete.innerHTML = '<div class="payever-company-autocomplete-loading">\n' +
            '\t\t\t<div class="payever-loading-animation">\n' +
            '\t\t\t\t<div class="payever-loader"></div>\n' +
            '\t\t\t</div>\n' +
            '\t\t</div>\n' +
            '\t<ul class="payever-company-autocomplete-items"></ul>' +
            '<input type="hidden" id="payever_company_selected" name="payever_company_selected" value="" />';
        autocomplete.setAttribute('data-payever-company-autocomplete', 'true')
        autocomplete.style.maxWidth = "365px";

        this.companyElement.parentNode.insertBefore(autocomplete, this.companyElement.nextSibling);
        this.el = PayeverDomAccess.querySelector(this.companyElement.parentNode, '.payever-company-autocomplete');

    },

    hideLoadingIndicator() {
        const indicator = PayeverDomAccess.querySelector(
            this.companyElement.closest('form'),
            this.options.selectorLoadingIndicator
        );

        if (indicator) {
            indicator.style.display = 'none';
        }
    }
};

const PayeverCompanySearch = {
    getLastSearch: null,
    options: {
        /**
         * @typedef defaultAddressFields
         * @type {Object}
         * @property {('[name*=street]')} street
         * @property {('[name*=zipcode]')} zipcode
         * @property {('[name*=city]')} city
         * @property {('[name*=countryId]')} countryId
         * @property {('[name*=countryStateId]')} countryStateId
         */
        /**
         * @typedef defaultAddressFieldsArray
         * @type {('street', 'zipcode', 'city', 'countryId', 'countryStateId')}
         */
        /**
         * Selector to find all default address fields to show/hide them
         * @type defaultAddressFields
         */
        selectorDefaultAddressFields: {
            street: '[name*=street]',
            zipcode: '[name*=zip]',
            city: '[name*=town]',
            housenumber: '[name*=housenumber]',
            countryId: '[id*=country-id-select]',
        },
        countryIdInput: '[id*=country-id-select]',
        /**
         * Specifies the element where the user types his company
         * @type string
         */
        selectorCompanyInput: 'input[name=company]',

        /**
         * Specifies the country iso2 code element
         * @type string
         */
        selectorCountryCode: '[name*=country_selector_code]',

        /**
         * Specifies the country iso2 code element
         * @type string
         */
        selectorHousenumber: '[name*=housenumber]',

        /**
         * Specifies the company id element
         * @type string
         */
        selectorCompanyId: '.payever-buyer-id',

        /**
         * Specifies the element where the user types his vat ID
         * @type string
         */
        selectorVatIdsInput: '[name*=vatIds]',
        countryMapping: {}
    },
    payeverCompanyAutocomplete: null,
    payeverCountryPicker: null,
    payeverApiClient: null,

    init(element, options) {
        this.el = element;

        // Init
        this.registerDomEvents();

        if (options && options.countryMapping && Object.keys(options.countryMapping).length) {
            this.options.countryMapping = options.countryMapping;
            const countryField = this.getCountryElement();

            if (countryField.value) {
                for (var key in options.countryMapping) {
                    if (options.countryMapping[key] == countryField.value) {
                        options['defaultCountry'] = key
                    }
                }
            }
        }

        this.payeverCompanyAutocomplete = Object.assign({}, PayeverCompanyAutocomplete);
        this.payeverCompanyAutocomplete.setAddressCallback(this.fillAddress.bind(this));
        this.payeverCompanyAutocomplete.init(this.el, options);
        this.payeverCountryPicker = Object.assign({}, PayeverCountryPicker);
        this.payeverCountryPicker.init(this, this.el, options);

        this.payeverApiClient = Object.assign({}, PayeverApiClient);
        this.payeverApiClient.init();
        const me = this;
        this.getParentForm()
            .querySelector('button[type=submit]')
            .addEventListener("click", function () {
                me.saveAddress()
            });
    },

    saveAddress() {
        const companyData = this.payeverCompanyAutocomplete.getSelectedCompany();

        if (!companyData || !this.getParentForm()) {
            return;
        }

        const formData = new FormData(this.getParentForm());
        const data = {
            company: formData.get('company'),
            email: formData.get('email'),
            town: formData.get('town'),
            zip: formData.get('zip'),
            companyData: companyData,
        };

        return this.payeverApiClient.company(JSON.stringify(data), {
            success: ((response) => {
                console.log(response);
            }),
        })
    },

    /**
     * Add event to get changes from the suggest input field
     * @return void
     */
    registerDomEvents() {
        let self = this;
        const companyInput = this.getCompanyInputElement();
        if (companyInput) {
            companyInput.addEventListener('keyup', function () {
                self.doSearch(1150);
            });
        }
    },

    doSearch(timeout = 0) {
        const companyInput = this.getCompanyInputElement();
        const searchValue = companyInput.value.trim();
        if (searchValue.length < 3) {
            this.currentTimer && window.clearTimeout(this.currentTimer);
            this.getAutocomplete().abortLastApiRequest();
        }

        if (searchValue.length >= 3) {
            if (this.currentTimer && this.getLastSearch !== searchValue) {
                window.clearTimeout(this.currentTimer);
                this.getAutocomplete().abortLastApiRequest();
            }

            let self = this;
            this.currentTimer = window.setTimeout(function () {
                self.getAutocomplete().clearSearchItems();
                self.getAutocomplete().showLoadingIndicator();
                self.getAutocomplete().show();

                // Get Country
                let country = 0;
                if (self.getCountryPickerElement() && self.getCountryPickerElement().value) {
                    country = self.getCountryPickerElement().value;
                }

                self.getAutocomplete().search(
                    searchValue,
                    country
                );

                self.getLastSearch = searchValue;
                self.currentTimer = null;
            }, timeout);
        }
    },

    /**
     * Returns the parent form html element
     *
     * @return {HTMLElement}
     */
    getParentForm() {
        return this.el.closest('form');
    },

    /**
     * Returns the autocomplete js plugin
     *
     * @return PayeverCompanyAutocomplete
     */
    getAutocomplete() {
        return this.payeverCompanyAutocomplete;
    },

    /**
     * Returns the CompanyId element
     * @return {HTMLElement}
     */
    getCompanyIdElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorCompanyId);
    },

    /**
     * Returns the country element
     * @return {HTMLElement}
     */
    getCountryElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorDefaultAddressFields.countryId);
    },

    /**
     * Returns the country element
     * @return {HTMLElement}
     */
    getHouseNumberElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorDefaultAddressFields.housenumber);
    },

    /**
     * Returns the country picker element
     * @return {HTMLElement}
     */
    getCountryPickerElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorCountryCode);
    },

    /**
     * Returns the street element
     * @return {HTMLElement}
     */
    getStreetElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorDefaultAddressFields.street);
    },

    /**
     * Returns the postcode element
     * @return {HTMLElement}
     */
    getZipElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorDefaultAddressFields.zipcode);
    },

    /**
     * Returns the city element
     * @return {HTMLElement}
     */
    getCityElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorDefaultAddressFields.city);
    },

    /**
     * Returns the company element
     * @return {HTMLElement}
     */
    getCompanyInputElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorCompanyInput);
    },

    /**
     * Returns the vatIds element
     * @return {HTMLElement}
     */
    getVatIdsElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorVatIdsInput);
    },

    /**
     * Returns the state element
     * @return {HTMLElement}
     */
    getCountryStateIdElement() {
        const form = this.getParentForm();
        if (!form) {
            return null;
        }

        return PayeverDomAccess.querySelector(form, this.options.selectorDefaultAddressFields.countryStateId);
    },

    /**
     * Fill address in default shopware fields
     * @param company{companyDetails}
     * @return void
     */
    fillAddress(company) {
        this.setElementValue(this.getStreetElement(), company.address.street_name);
        this.setElementValue(this.getHouseNumberElement(), company.address.street_number);
        this.setElementValue(this.getZipElement(), company.address.post_code);
        this.setElementValue(this.getCityElement(), company.address.city);
        this.setElementValue(this.getCompanyInputElement(), company.name);

        if (
            Object.keys(this.options.countryMapping).length
            && this.options.countryMapping[this.payeverCountryPicker.selectedCountryCode]
        ) {
            this.setElementValue(
                this.getCountryElement(),
                this.options.countryMapping[this.payeverCountryPicker.selectedCountryCode]
            );
        }
    },
    setElementValue(element, value)
    {
        if (!element || typeof value === "undefined") {
            return;
        }

        element.value = value;
        element.dispatchEvent(new Event('input'));
    }
};