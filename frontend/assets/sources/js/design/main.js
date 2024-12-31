// Import our custom CSS
// import "../scss/styles.scss";
//
// // Import only the Bootstrap components we need
// import { Popover } from "bootstrap";
//
// import "./menu";
// import "./copy";
// import "./language";

// Create an example popover
document.querySelectorAll('[data-bs-toggle="popover"]').forEach((popover) => {
    new Popover(popover);
});

document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((tooltip) => {
    new bootstrap.Tooltip(tooltip);
});

// КАТЕГОРИИ
$(document).ready(function () {
    $(".owl-carousel").owlCarousel({
        loop: false,
        items: 6,
        stagePadding: 40,
        margin: 12,
        autoWidth: true,
        checkVisible: true,
        // nav: true,
        // navText: [
        //   "<i class='fa fa-caret-left'></i>",
        //   "<i class='fa fa-caret-right'></i>"
        // ],
    });
});
