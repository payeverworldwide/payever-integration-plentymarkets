window.PayeverPendingStatusChecker = {
    attempts: 0,
    checkStatus: function (paymentStatusUrl) {
        if (window.PayeverPendingStatusChecker.attempts >= 6) {
            let loaderElement = document.getElementById('payever-loading-animation');
            let statusElement = document.getElementById('payever-status-message');

            loaderElement.style.display = 'none';
            statusElement.innerHTML = 'Sorry, but it will take more time.';

            return;
        }

        window.PayeverPendingStatusChecker.sendStatusCheckRequest(paymentStatusUrl);
    },
    sendStatusCheckRequest: function (paymentStatusUrl) {
        let xhr = new XMLHttpRequest();
        xhr.open('GET', paymentStatusUrl, true);
        xhr.setRequestHeader('Content-type', 'application/json; charset=UTF-8');
        xhr.send();
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }

            if (xhr.status === 200) {
                var response = (function (raw) {
                    try {
                        return JSON.parse(raw);
                    } catch (e) {
                        console.error(e)
                        return {};
                    }
                })(xhr.response);
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                    return;
                }
            }

            setTimeout(function () {
                window.PayeverPendingStatusChecker.attempts++;
                window.PayeverPendingStatusChecker.checkStatus(paymentStatusUrl);
            }, 10000);
        };
    },
}