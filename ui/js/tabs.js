$(document).ready(function () {
    const tabsWrapper = $('.pe-tabs')
    const tabs = tabsWrapper.find('.tab')

    const tabsButtons = $('<div></div>', {
        class: 'tabs-buttons'
    })

    tabs.each(function (index) {
        if (index !== 0) $(this).css('display', 'none')

        $(this).attr('data-index', index)

        createButton($(this).data('title'), index, index === 0)
    })

    tabsWrapper.prepend(tabsButtons)

    function createButton(text, index, isActive) {
        const btn = $('<a></a>', {
            text,
            class: `tab-btn ${isActive ? 'active-tab' : ''}`,
            'data-index': index
        })

        btn.click(function () {
            changeTab(index)
        })

        tabsButtons.append(btn)
    }

    function changeTab(index) {
        tabs.css('display', 'none')

        tabsWrapper.find(`.tab[data-index=${index}]`).fadeIn()

        tabsButtons.find('.tab-btn').removeClass('active-tab')

        tabsButtons.find(`.tab-btn[data-index=${index}]`).addClass('active-tab')
    }
})
