$(document).ready(function () {
    const CANCEL_ACTION = 'cancel';
    const REFUND_ACTION = 'refund';
    const CAPTURE_ACTION = 'shipping';

    const itemsCountSelect = $('.items-count select')

    const initCurrentPage = getCurrentPage()
    const initItemsPerPage = getItemsPerPage()

    getOrders(initCurrentPage, initItemsPerPage)

    function getOrders(currentPage, itemsPerPage) {
        $.ajax({
            method: 'GET',
            url: `/order/payever/totals?page=${currentPage}&itemsPerPage=${itemsPerPage}`,
            beforeSend: function (xhr) {
                xhr.setRequestHeader("Accept","application/x.plentymarkets.v1+json");
                xhr.setRequestHeader("Content-Type","application/json");
                xhr.setRequestHeader("Authorization","Bearer " + localStorage.getItem('accessToken'));

                clearOrdersTable()
                toggleLoader(true)
            },
            error: function (error) {
                console.log(error)
                alert(error.responseText)
            },
            success: function (data) {
                if (!data.entries) return

                renderOrders(data.entries)

                renderPaginate(data.pages, data.total)
            },
            complete: function () {
                toggleLoader(false)
            }
        })
    }

    function renderPaginate(pages, total) {
        const pagination = $('.pagination')
        pagination.html('')

        const currentPage = getCurrentPage()

        for (let pageNumber = 1; pageNumber <= pages; pageNumber++) {
            pagination.append(`<a data-number-page="${pageNumber}">${pageNumber}</a>`)
        }

        pagination.find(`a[data-number-page="${currentPage}"]`).addClass('active')

        pagination.find('a').click(function () {
            hideErrorMessage()
            const newPageNumber = $(this).attr('data-number-page')
            pagination.attr('data-current-page', newPageNumber)
            getOrders(newPageNumber, getItemsPerPage())
        })
    }

    function clearOrdersTable() {
        $('.orders-table tbody').html('')
    }

    function renderOrders(orders) {
        const tableBody = $('.orders-table tbody')

        orders.forEach(order => {
            const orderId = order.orderId
            const status = order.status
            const grossTotal = parseFloat(order.amount.grossTotal).toFixed(2) + ' ' + order.amount.currency
            const paidAmount = parseFloat(order.amount.paidAmount).toFixed(2) + ' ' + order.amount.currency
            const payment = order.payment.paymentKey + ' / ' + order.payment.name
            const actions = order.actions
            const transactions = order.transactions

            tableBody.append(
                `<tr data-order-id="${orderId}">`
                    + `<td>${orderId}</td>`
                    + `<td>${status}</td>`
                    + `<td>${grossTotal}</td>`
                    + `<td>${paidAmount}</td>`
                    + `<td><div><span style="font-weight: bold">Method:</span> ${payment}</div><div style="margin-top: 0.4em">${renderTransactions(transactions)}</td>`
                    + `<td class="allowed-actions">`
                        + `${isAllowedAction(actions, 'cancel')
                                ? `<button class="action-btn" data-order-id="${orderId}" data-action="${CANCEL_ACTION}">Cancel</button>` : ''}`
                        + `${isAllowedAction(actions, 'refund')
                                ? `<button class="action-btn" data-order-id="${orderId}" data-action="${REFUND_ACTION}">Refund</button>` : ''}`
                        + `${isAllowedAction(actions, 'shipping_goods')
                                ? `<button class="action-btn" data-order-id="${orderId}" data-action="${CAPTURE_ACTION}">Shipping</button>` : ''}`
                    + `</td>`
                + `</tr>`
            )

            tableBody.find(`.action-btn[data-order-id="${orderId}"]`).click(function () {
                const action = $(this).attr('data-action')
                showItemsModal(order, action)
            })

            if (tableBody.find(`tr[data-order-id="${orderId}"] .action-btn`).length === 0) {
                tableBody.find(`tr[data-order-id="${orderId}"] .allowed-actions`).html('No actions available')
            }
        })
    }

    function showItemsModal(order, action) {
        hideErrorMessage()

        const modal = $('#items-modal')
        const orderId = order.orderId

        $.ajax({
            method: 'GET',
            url: `/order/payever/items?orderId=${orderId}&action=${action}`,
            beforeSend: function (xhr) {
                xhr.setRequestHeader("Accept","application/x.plentymarkets.v1+json");
                xhr.setRequestHeader("Content-Type","application/json");
                xhr.setRequestHeader("Authorization","Bearer " + localStorage.getItem('accessToken'));

                toggleLoader(true)
            },
            error: function (error) {
                console.log(error)
                alert(error.responseText)
            },
            success: function (data) {
                if (!data) return

                renderItems(data.entries)

                rerenderDisplayedModalData(data.availableAmountByAction, order.actions, action)

                showModal()
            },
            complete: function () {
                toggleLoader(false)
            }
        })

        const actionBtns = $('.payment-action')

        actionBtns.off('click').on('click', function (event) {
            event.preventDefault();

            const btnAction = $(this).attr('data-action');

            const items = getItems();

            $.ajax({
                url: '/order/payever/action',
                type: 'POST',
                dataType: 'json',
                data: {
                    order_id: orderId,
                    action: btnAction,
                    items,
                    amount: $('#items-modal input#amount-input').val()
                },
                beforeSend: function (xhr) {
                    xhr.setRequestHeader("Accept","application/x.plentymarkets.v1+json");
                    //xhr.setRequestHeader("Content-Type","application/json");
                    xhr.setRequestHeader("Authorization","Bearer " + localStorage.getItem('accessToken'));

                    toggleLoader(true)
                },
                error: function (error) {
                    console.log(error)
                    showErrorMessage(`[Error] Order ID ${orderId} <br>` + JSON.parse(error.responseText).error.message)
                },
                complete: () => {
                    toggleLoader(false)
                    getOrders(getCurrentPage(), getItemsPerPage())
                    closeModal()
                }
            })
        })

        function getItems() {
            const items = [];

            const orderItems = modal.find('.items-table tbody tr');

            orderItems.each(function (index, item) {
                const id = $(item).data('id');

                if (typeof id !== 'undefined') {
                    if ($(item).find('input.order-item').first().is(':checked')) {
                        const qtyEl = $(item).find('input.order-qty').first();

                        items.push({
                            id: id,
                            reference: $(item).data('reference'),
                            qty: qtyEl.val()
                        });
                    }
                }
            });

            return items;
        }

        function renderItems(items) {
            const tableBody = modal.find('.items-table tbody')
            tableBody.html('')

            items.forEach(item => {
                const id = item.id
                const itemType = item.itemType
                const name = item.name
                const identifier = item.identifier
                const quantity = item.quantity
                const unitPrice = parseFloat(item.unitPrice).toFixed(2)
                const totalPrice = parseFloat(item.totalPrice).toFixed(2)
                const qtyByAction = item.qtyByAction
                const totalByAction = parseFloat(item.totalByAction).toFixed(2)
                const availableQtyByAction = item.availableQtyByAction

                tableBody.append(
                `<tr data-id="${id}" data-reference="${orderId}" data-unit-price="${unitPrice}" data-qty="${quantity}" ${itemType === "6" ? 'style="border-top: 4px solid #636671;"' : ''}>`
                        + `<td><input class="order-item" type="checkbox" name="order-item[]" value="1" checked></td>`
                        + `<td>${name}</td>`
                        + `<td>${identifier}</td>`
                        + `<td>${unitPrice}</td>`
                        + `<td><input class="order-qty" name="order-qty[]" type="number" value="${availableQtyByAction}" min="0" max="${availableQtyByAction}"></td>`
                        + `<td>${totalPrice}</td>`
                        + `<td>${qtyByAction}</td>`
                        + `<td>${totalByAction}</td>`
                    + `</tr>`
                )
            })

            $('#items-modal .order-qty').on('blur', validateInput);
        }

        function rerenderDisplayedModalData(availableAmount, actions, action) {
            showItemsTable()
            const orderId = order.orderId
            const currency = order.amount.currency

            const total = parseFloat(order.amount.grossTotal).toFixed(2) + ' ' + currency
            const captured = parseFloat(order.capturedTotal).toFixed(2) + ' ' + currency
            const refunded = parseFloat(order.refundedTotal).toFixed(2) + ' ' + currency
            const cancelled = parseFloat(order.cancelledTotal).toFixed(2) + ' ' + currency

            // update amount input
            const amountInput = modal.find('#amount-input')
            amountInput.val(availableAmount)
            amountInput.attr('min', 0)
            amountInput.attr('max', availableAmount)

            // update modal header
            modal.find('.order-id').html(orderId)

            // set actions name in data-attr for buttons
            modal.find('button.action-btn').attr('data-action', action)
            modal.find('button.amount-action-btn').attr('data-action', `${action}_amount`)

            // update displayed action text on buttons
            modal.find('.action-name').html(action)

            // update totals table
            modal.find('.total').html(total)
            modal.find('.captured').html(captured)
            modal.find('.refunded').html(refunded)
            modal.find('.cancelled').html(cancelled)

            const isPartialAllowed = isPartialActionAllowed(actions, action)

            if (order.manual || !isPartialAllowed) {
                hideItemsTable()
            }

            if (!isPartialAllowed) {
                amountInput.attr('min', availableAmount)
            }
        }

        function showModal() {
            modal.css("display", "flex").hide().fadeIn()
        }

        function closeModal() {
            modal.fadeOut()
        }
    }

    function showErrorMessage(message) {
        $('#pe-error-msg').html(message)
    }

    function hideErrorMessage() {
        $('#pe-error-msg').html('')
    }

    // modal functionality
    $('.modal__close-btn').click(function () {
        $('.modal').fadeOut()
    })

    // Amount input validation
    $('#items-modal #amount-input').on('blur', validateInput);

    function validateInput() {
        const min = parseFloat($(this).attr('min'));
        const max = parseFloat($(this).attr('max'));
        const currentValue = parseFloat($(this).val().trim());

        if (!currentValue || currentValue < min || currentValue > max) {
            $(this).val(max);
        }
    }

    function isAllowedAction(actions, actionName) {
        const action = actions.find(action => action.action === actionName)

        if (action) {
            return action.enabled
        }
    }

    function isPartialActionAllowed(actions, actionName) {
        let parsedAction = actionName;

        if (actionName === 'shipping') {
            parsedAction = 'shipping_goods'
        }

        const action = actions.find(action => action.action === parsedAction)

        if (action) {
            return action.partialAllowed
        }
    }

    function renderTransactions(transactions) {
        let html = `<div style="margin-top: 0.4em"><div style="font-weight: bold">${transactions.length !== 0 ? 'Transactions ID:' : 'Transactions ID not found'}</div>`

        transactions.forEach(transaction => {
            html += `<div class="transactions">${transaction}</div>`
        })

        html += '</div>'
        return html
    }

    function getItemsPerPage() {
        return itemsCountSelect.val()
    }

    function getCurrentPage() {
        return $('.pagination').attr('data-current-page')
    }

    itemsCountSelect.change(function () {
        getOrders(getCurrentPage(), getItemsPerPage())
    })

    function showItemsTable() {
        $('#items-modal #by-items-wrapper').show()
    }

    function hideItemsTable() {
        $('#items-modal #by-items-wrapper').hide()
    }
})
