$(document).ready(function () {
    try {

        /*if ($("form#signup")) {
            $("form#signup").parents(".mt-3").addClass("signup-section");
        } //adding parent class over signup

        if ($("form#login")) {
            $("form#login").parents(".justify-content-center").addClass("login-section");
        } //adding parent class over login

        if ($('body.pagelayout-login')) {
            $('body.pagelayout-login .col-md-5') ? $('body.pagelayout-login .col-md-5').addClass('col-12 login-wrapper').removeClass('col-md-5') : '';

            var textCenter = $('body.pagelayout-login .login-wrapper:last-child') ? $('body.pagelayout-login .login-wrapper:last-child').addClass('text-center') : '';

            var centerText = $('body.pagelayout-login .justify-content-center:last-child') ? $('body.pagelayout-login .justify-content-center:last-child').addClass('text-center') : '';
            var buttonBlock = $('body.pagelayout-login .justify-content-center:last-child .btn') ? $('body.pagelayout-login .justify-content-center:last-child .btn').addClass('btn-block orcolor') : '';

            $('body.pagelayout-login .col-xl-6') ? $('body.pagelayout-login .col-xl-6').addClass('col-xl-12').removeClass('col-xl-6') : '';
        }*/

        if($('#page-login-index').length > 0){
            if($('form.login-form').length > 0){
               if($("button[data-modal='alert']").length > 0){
                   $('#guestlogin').append($("button[data-modal='alert']"));
               }
               if($('#guestlogin').length > 0){
                   $('form.login-form').append($('#guestlogin'));
               }
               if($('.login-languagemenu').length > 0){
                   $('form.login-form').append($('.login-languagemenu'));
               }
               $('<div class="signup-wrapper"></div>').insertAfter($('form.login-form'));
            }

            if($('.signup-wrapper').length > 0){
                if($('.login-instructions').length > 0 && $('.login-signup').length > 0){
                    $('.signup-wrapper').append($('.login-instructions'));
                    $('.signup-wrapper').append($('.login-signup'));
                }
            }
        }
    } catch (ignore) {}


});
