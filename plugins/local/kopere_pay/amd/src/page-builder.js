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
 * page-builder.js
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "editor_tiny/editor"], function ($, tiny) {
    var pageBuilder = {

        offset: 200, // Ajuste para o deslocamento desejado
        menuLinks: null,
        sections: null,

        init: function () {

            // Seleciona os links e as seções correspondentes
            pageBuilder.menuLinks = document.querySelectorAll(".mainmenu li");
            pageBuilder.sections = document.querySelectorAll(".course-details-content > div");

            if (pageBuilder.menuLinks && pageBuilder.menuLinks[0]) {
                // Adiciona Class ao primeiro elemento
                pageBuilder.menuLinks[0].classList.add("current");

                // Adiciona o evento de clique a todos os links do menu
                pageBuilder.menuLinks.forEach(link => {
                    link.addEventListener("click", pageBuilder.scrollToSection);
                });
            }

            // Rola a página para o topo
            window.scrollTo(0, 0);

            if (pageBuilder.sections) {
                // Armazena o valor de y para cada seção no atributo data-section-y
                pageBuilder.sections.forEach(section => {
                    const sectionY = section.getBoundingClientRect().y;
                    section.setAttribute("data-section-y", sectionY);
                });
            }

            window.addEventListener("scroll", pageBuilder.scrollPage);

            $("#video-popup-wrapper").click(function () {
                const youtubeVideo = $("#youtube-video");
                youtubeVideo.attr("src", youtubeVideo.attr("data-src"));
                $("#youtube-close").click(function () {
                    youtubeVideo.attr("src", "");
                });
            });

            $("#enrollment-comprar-btn").click(function () {
                const comprarLink = $("#comprar-iframe");
                comprarLink.attr("src", comprarLink.attr("data-src") + Math.random());
            });
        },

        // Função para rolar suavemente com o deslocamento
        scrollToSection: function (event) {
            event.preventDefault(); // Evita o comportamento padrão do link
            const targetId = this.getAttribute("data-action"); // Obtém o ID do link
            const targetSection = document.getElementById(targetId);

            // Calcula a posição de destino com o deslocamento
            const targetPosition = parseInt(targetSection.getAttribute("data-section-y")) - pageBuilder.offset;

            // Rola suavemente até a posição desejada
            window.scrollTo({
                top: targetPosition,
                behavior: "smooth"
            });
        },

        scrollPage: function () {
            const scrollPosition = window.scrollY;

            $(".kopere_pay_view .enrollment-sticky-top").css("top", $("#header").height());

            pageBuilder.sections.forEach(section => {
                const sectionY = parseInt(section.getAttribute("data-section-y"));

                // Verifica se a posição de rolagem atual corresponde ao valor em data-section-y
                if (Math.abs(scrollPosition - sectionY + pageBuilder.offset) < 40) { // Ajuste a tolerância conforme necessário
                    pageBuilder.menuLinks.forEach(link => {
                        link.classList.remove("current")
                    });

                    var element = document.getElementById(`for-${section.id}`);
                    element.classList.add("current");
                } else {
                    // section.classList.remove("current");
                }
            });
        },

        createEditor: function (elementId) {
            tiny.setupForElementId({
                elementId: elementId,
                options: JSON.parse($("#tyni_editor_config").val()),
            });
        }
    };

    return pageBuilder;
});
