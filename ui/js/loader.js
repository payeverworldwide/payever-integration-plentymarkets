function toggleLoader(show) {
    const loader = $('.loader')
    show ? loader.css("display", "flex").hide().fadeIn() : loader.fadeOut()
}
