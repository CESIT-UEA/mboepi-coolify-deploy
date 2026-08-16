// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * buttons.js
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery"], function ($) {
   let buttons = {
        init: function () {
            // Copia links
            $(".link-area").click(function (event) {
                event.preventDefault();

                let img = $(this).find("img").attr("src");

                $(".show-area").show(400);

                $(".show-area .img-area-p").attr("src", img);
                $(".show-area .img-area-m").attr("src", img);
                $(".show-area .img-area-g").attr("src", img);

                let url = $(this).attr("href").replace("#", "");
                let texto_p = '<a href="' + url + '">' + "\r\n" +
                    '    <img style="height:30px" src="' + img + '"  alt="img">' + "\r\n" +
                    '</a>';
                $("#copy-code-1").text(texto_p);
                $("#copy-code-12").text(texto_p.replace("/?", "/view.php?"));

                let texto_m = '<a href="' + url + '">' + "\r\n" +
                    '    <img style="height:50px" src="' + img + '" alt="img">' + "\r\n" +
                    '</a>';
                $("#copy-code-2").text(texto_m);
                $("#copy-code-22").text(texto_m.replace("/?", "/view.php?"));

                let texto_g = '<a href="' + url + '">' + "\r\n" +
                    '    <img  style="height:70px" src="' + img + '" alt="img">' + "\r\n" +
                    '</a>';
                $("#copy-code-3").text(texto_g);
                $("#copy-code-32").text(texto_g.replace("/?", "/view.php?"));
            });
            $(".copy-code").each(buttons.copy_code_each);
        },
        copy_code_each: function (id, element) {

            let $element = $(element);
            let copyElementId = $element.attr("id");

            function elementClick(event) {
                let cell = document.getElementById(copyElementId);
                let range, selection;
                if (document.body.createTextRange) {
                    range = document.body.createTextRange();
                    range.moveToElementText(cell);
                    range.select();
                } else if (window.getSelection) {
                    selection = window.getSelection();
                    range = document.createRange();
                    range.selectNodeContents(cell);
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }

            function elementCopy(event) {
                if (document.body.createTextRange) {
                    navigator.clipboard.writeText($element.val() || $element.text());
                } else if (window.getSelection) {
                    navigator.clipboard.writeText($element.val() || $element.text());
                }

                $tolltip.find("span").html(M.util.get_string("copied", "local_kopere_pay"));
            }

            let $tolltip = $(
                `<div class="copi-code-icon">
                 <span class="text-copiar">${M.util.get_string("copy", "local_kopere_pay")}</span>
                 <svg style="width:21px;height:21px;" enable-background="new -129.4 -5.972 854.079 854.079"
                      height="854.079" viewBox="-129.4 -5.972 854.079 854.079" width="854.079" xmlns="http://www.w3.org/2000/svg">
                    <path d="m621.682 138.654.075.139c28.454.07 54.141 10.497 72.75 27.318 18.457 16.752 29.992 39.899 29.992 65.479h.154v.139 523.277.07h-.154c-.077 25.579-11.612 48.865-30.222 65.756-18.534 16.684-44.143 27.109-72.443 27.109v.14h-.152-455.187-.077v-.14c-28.376-.069-54.139-10.426-72.749-27.317-18.458-16.753-29.992-39.899-30.069-65.479 0-180.449 0-342.966 0-523.416v-.069h.154c.077-25.649 11.689-48.936 30.299-65.757 18.533-16.683 44.065-27.11 72.442-27.179v-.139h.154c151.652.069 303.381.069 455.033.069zm-750.953 379.32-.154-430.896v-.07h.154c.076-25.649 11.688-48.936 30.222-65.757 18.533-16.683 44.143-27.11 72.442-27.18v-.069h.154 455.186c59.138 0 68.443 69.232.077 70.623h-455.186-.154v-.139c-6.768 0-12.919 2.572-17.457 6.743-4.538 4.031-7.383 9.731-7.383 15.779h.154v.07 22.035 408.792c0 57.415-78.055 60.613-78.055.069zm775.868 237.102v-523.347-.139h.154c0-6.047-2.846-11.678-7.46-15.779-4.537-4.031-10.766-6.673-17.534-6.673v.139h-.075-455.187-.154v-.139c-6.767 0-12.919 2.642-17.457 6.743-4.537 4.031-7.382 9.731-7.382 15.779h.153v.069 523.277.139h-.153c0 6.117 2.921 11.679 7.459 15.778 4.537 4.102 10.767 6.674 17.457 6.674v-.14h.077 455.187.152v.14c6.691 0 12.92-2.641 17.457-6.742 4.537-4.032 7.383-9.732 7.383-15.78z"/>
                 </svg>
             </div>`);

            $element
                .click(elementClick)
                .on("mouseout", function () {
                    $tolltip.find(".text-copiar").html(M.util.get_string("copy", "local_kopere_pay"));
                });
            $tolltip
                .click(elementCopy)
                .on("mouseout", function () {
                    $tolltip.find(".text-copiar").html(M.util.get_string("copy", "local_kopere_pay"));
                });

            $element.parent().addClass("copy-code-area");
            $element.after($tolltip);
        }
    }

    return buttons;
});
