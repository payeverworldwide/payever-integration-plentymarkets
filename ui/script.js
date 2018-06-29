(function($) {
    $.waterfall = function() {
        var steps   = [],
            dfrd    = $.Deferred(),
            pointer = 0;

        $.each(arguments, function(i, a) {
            steps.push(function() {
                var args = [].slice.apply(arguments), d;

                if (typeof(a) == 'function') {
                    if (!((d = a.apply(null, args)) && d.promise)) {
                        d = $.Deferred()[d === false ? 'reject' : 'resolve'](d);
                    }
                } else if (a && a.promise) {
                    d = a;
                } else {
                    d = $.Deferred()[a === false ? 'reject' : 'resolve'](a);
                }

                d.fail(function() {
                    dfrd.reject.apply(dfrd, [].slice.apply(arguments));
                })
                    .done(function(data) {
                        pointer++;
                        args.push(data);

                        pointer == steps.length
                            ? dfrd.resolve.apply(dfrd, args)
                            : steps[pointer].apply(null, args);
                    });
            });
        });

        steps.length ? steps[0]() : dfrd.resolve();

        return dfrd;
    }
})($);

$.ajaxSetup({
    beforeSend: function (xhr) {
        /**
         * Setup headers for Plentymarkets REST API usage
         */
        xhr.setRequestHeader("Accept","application/x.plentymarkets.v1+json");
        xhr.setRequestHeader("Content-Type","application/json");
        xhr.setRequestHeader("Authorization","Bearer " + localStorage.getItem('accessToken'));
    }
});

var payeverInProgress = false,
    payeverMessages = {
        connectionError: 'Unexpected connection error occurred. Please, refresh the page.',
        sandboxSuccess: 'Sandbox credentials setup successful. Please, refresh plugin overview tab.',
        syncSuccess: 'Synchronization successful. Please, refresh Plugin overview tab.'
    };

function payeverToggleLoading(state) {
    payeverInProgress = state;
    $('.payever-btn')[state ? 'addClass' : 'removeClass']('in-progress');

    if (state) {
        $('.payever-message').html('');
    }
}

function payeverReportResult(isSuccess, message) {
    payeverToggleLoading(false);
    $('.payever-message')
        .removeClass('is-success is-error')
        .addClass(isSuccess ? 'is-success' : 'is-error')
        .html(message || '');
}

function payeverUpdateConfig(updateSourceCallback) {

    if (payeverInProgress) {
        return;
    }

    payeverToggleLoading(true);

    var pluginSetId,
        payeverPlugin,
        payeverConfigUri;

    /**
     * Collect all necessary data for proper config update
     */
    $.waterfall(
        $.getJSON('/rest/plugins'),
        $.getJSON('/rest/webstores'),
        updateSourceCallback,
        function (plugins, webstores) {
            payeverPlugin = plugins.filter(function (plugin) { return plugin.name === 'Payever'; }).shift();
            pluginSetId = webstores.slice().shift().pluginSetId;
            payeverConfigUri = '/rest/plugins/' + payeverPlugin.id + '/plugin_sets/' + pluginSetId + '/configurations';

            return $.getJSON(payeverConfigUri);
        }
    ).done(function (plugins, webstores, syncData, payeverConfig) {

        /**
         * Update existing config and save it via REST endpoint
         */

        console.debug('Payever processing collected data...');

        if (!syncData || !syncData.result || syncData.errors) {
            return payeverReportResult(false, syncData.errors.join('<br>'));
        }

        var isChanged = false,
            syncResult = syncData.result,
            // Get max id of config items
            incrementId = payeverConfig.slice().sort(function (a, b) { return a.id > b.id; }).shift().id;

        $.each(payeverConfig, function (index, item) {
            if (syncResult.hasOwnProperty(item.key) && item.value != syncResult[item.key]) {
                if (!item.id) {
                    payeverConfig[index].id = ++incrementId;
                }
                payeverConfig[index].value = syncResult[item.key];
                isChanged = true;
            }
        });

        if (isChanged) {
            $.ajax({
                url: payeverConfigUri, type: 'PUT', data: JSON.stringify(payeverConfig)
            }).done(function () {
                payeverReportResult(true, payeverMessages[syncResult.clientId ? 'sandboxSuccess' : 'syncSuccess'])
            }).fail(function () {
                payeverReportResult(false, payeverMessages.connectionError)
            });
        } else {
            payeverReportResult(true, payeverMessages[syncResult.clientId ? 'sandboxSuccess' : 'syncSuccess'])
        }

        console.debug('Payever process finished.', {
            plugins: plugins, webstores: webstores, syncData: syncData, payeverConfig: payeverConfig
        });

    }).fail(function () {
        payeverReportResult(false, payeverMessages.connectionError)
    });
}

function payeverSynchronize() {
    console.debug('Payever synchronization requested.');

    payeverUpdateConfig(function () {
        return $.getJSON('/payment/payever/synchronize');
    });
}

function payeverSetupSandbox() {
    console.debug('Payever sandbox setup requested.');

    payeverUpdateConfig(function () {
        return {
            result: {
                "environment": "0",
                "clientId": "1454_2ax8i5chkvggc8w00g8g4sk80ckswkw0c8k8scss40o40ok4sk",
                "clientSecret": "22uvxi05qlgk0wo8ws8s44wo8ccg48kwogoogsog4kg4s8k8k",
                "slug": "payever"
            }
        }
    });
}