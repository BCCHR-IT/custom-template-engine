$(document).ready(function () {
    $(this).find(".fa-caret-up").hide();
    $(this).find(".fa-caret-down").show();
});

$(".collapsible").click(function() {
    $(this).find(".fa-caret-down").toggle();
    $(this).find(".fa-caret-up").toggle();
    $(this).next().toggle();
});

$("#readmore-link").click(function () {
    $("#readmore").toggle();
});