$(document).ready(function () {
    var clipboard = new ClipboardJS('.btn-clipboard');

    clipboard.on('success', function (e) {
        var message = '<i class=\'fas fa-check-circle\'></i><div class=\'toast-message_text\'>' + $(e.trigger).data('message') + '</div>';
        toastr.success(message, "", {"progressBar":true,"positionClass":"toast-top-right","escapeHtml":false});
    });

    try {
        var tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        for (var i = 0; i < tooltipTriggerList.length; i++) {
            new bootstrap.Tooltip(tooltipTriggerList[i]);
        }
    } catch (e) {}
});