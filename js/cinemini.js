/**
 * Cine-Mini: Controlador Frontend con AJAX y Animación Ken Burns
 * Plataforma TecnoPresta
 */
(function ($) {
    'use strict';

    var CineMini = {
        config: {
            endpoint: 'get_anuncio_activo.php',
            storageKey: 'cinemini_dismissed_anuncio_',
            containerId: 'cinemini-wrapper',
            reopenBtnId: 'cinemini-reopen',
            defaultDuration: 5000,
            autoPlay: true
        },

        state: {
            anuncio: null,
            currentIndex: 0,
            timer: null,
            isPlaying: true,
            totalSlides: 0
        },

        init: function () {
            var self = this;
            $(document).ready(function () {
                self.fetchAnuncio();
            });
        },

        fetchAnuncio: function () {
            var self = this;
            $.ajax({
                url: self.config.endpoint,
                type: 'GET',
                dataType: 'json',
                cache: false,
                success: function (response) {
                    if (response && response.success && response.activo && response.anuncio) {
                        var anuncio = response.anuncio;
                        if (anuncio.diapositivas && anuncio.diapositivas.length > 0) {
                            self.state.anuncio = anuncio;
                            self.state.totalSlides = anuncio.diapositivas.length;
                            self.render();
                        }
                    }
                },
                error: function (xhr, status, error) {
                    console.warn('[Cine-Mini] No fue posible conectar con el servicio de anuncios:', error);
                }
            });
        },

        render: function () {
            var self = this;
            var a = self.state.anuncio;

            // Revisar si ya fue cerrado en esta sesión de navegador
            var wasDismissed = sessionStorage.getItem(self.config.storageKey + a.id);

            // Generar markup de slides
            var slidesHtml = '';
            var dotsHtml = '';
            var kbClasses = ['kb-1', 'kb-2', 'kb-3', 'kb-4', 'kb-5'];

            $.each(a.diapositivas, function (idx, slide) {
                var kbClass = kbClasses[idx % kbClasses.length];
                var activeClass = (idx === 0) ? 'active' : '';
                
                slidesHtml += '<div class="cinemini-slide ' + activeClass + '" data-slide-index="' + idx + '">';
                slidesHtml += '  <img src="' + slide.imagen_url + '" class="cinemini-img ' + kbClass + '" alt="Diapositiva ' + (idx + 1) + '" loading="eager">';
                if (slide.titulo || slide.texto) {
                    slidesHtml += '  <div class="cinemini-caption">';
                    if (slide.titulo) slidesHtml += '    <h5>' + self.escapeHtml(slide.titulo) + '</h5>';
                    if (slide.texto) slidesHtml += '    <p>' + self.escapeHtml(slide.texto) + '</p>';
                    slidesHtml += '  </div>';
                }
                slidesHtml += '</div>';

                dotsHtml += '<div class="cinemini-dot ' + (idx === 0 ? 'active' : '') + '" data-goto="' + idx + '"><div class="cinemini-dot-fill"></div></div>';
            });

            var markup = 
                '<div id="' + self.config.containerId + '" class="cinemini-overlay" aria-live="polite">' +
                '  <div class="cinemini-header">' +
                '    <span class="cinemini-tag">Novedades</span>' +
                '    <div class="cinemini-title" title="' + self.escapeHtml(a.titulo) + '">' + self.escapeHtml(a.titulo) + '</div>' +
                '    <button type="button" class="cinemini-close-btn" id="cinemini-close" aria-label="Cerrar">&times;</button>' +
                '  </div>' +
                '  <div class="cinemini-stage">' + slidesHtml + '</div>' +
                '  <div class="cinemini-footer">' +
                '    <div class="cinemini-indicators">' + dotsHtml + '</div>' +
                '    <div class="cinemini-controls">' +
                '      <button type="button" class="cinemini-btn-icon" id="cinemini-toggle-play" title="Pausar/Reanudar">⏸</button>' +
                '      <span style="font-size: 0.72rem; color: #718096;" id="cinemini-counter">1/' + self.state.totalSlides + '</span>' +
                '    </div>' +
                '  </div>' +
                '</div>' +
                '<button type="button" id="' + self.config.reopenBtnId + '" class="cinemini-reopen-btn">' +
                '  🎬 Ver Próxima Actualización' +
                '</button>';

            $('body').append(markup);

            self.bindEvents();

            if (wasDismissed === 'true') {
                $('#' + self.config.reopenBtnId).css('display', 'inline-flex');
            } else {
                setTimeout(function () {
                    $('#' + self.config.containerId).addClass('cinemini-visible');
                    self.startSequence();
                }, 800);
            }
        },

        bindEvents: function () {
            var self = this;
            var container = $('#' + self.config.containerId);
            var reopenBtn = $('#' + self.config.reopenBtnId);

            // Botón Cerrar
            $(document).on('click touchend', '#cinemini-close', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.stopSequence();
                container.removeClass('cinemini-visible');
                container.css('pointer-events', 'none');
                setTimeout(function () {
                    container.hide();
                    reopenBtn.css('display', 'inline-flex');
                }, 350);
                sessionStorage.setItem(self.config.storageKey + self.state.anuncio.id, 'true');
            });

            // Botón Reabrir
            $(document).on('click touchend', '#' + self.config.reopenBtnId, function (e) {
                e.preventDefault();
                e.stopPropagation();
                reopenBtn.hide();
                container.show();
                setTimeout(function () {
                    container.css('pointer-events', 'auto');
                    container.addClass('cinemini-visible');
                    self.startSequence();
                }, 50);
                sessionStorage.removeItem(self.config.storageKey + self.state.anuncio.id);
            });

            // Pausar / Reanudar
            $('#cinemini-toggle-play').on('click', function () {
                if (self.state.isPlaying) {
                    self.stopSequence();
                    self.state.isPlaying = false;
                    $(this).text('▶');
                } else {
                    self.state.isPlaying = true;
                    $(this).text('⏸');
                    self.startSequence();
                }
            });

            // Clic en Dots indicadores
            container.on('click', '.cinemini-dot', function () {
                var targetIdx = parseInt($(this).data('goto'), 10);
                self.goToSlide(targetIdx);
                if (self.state.isPlaying) {
                    self.restartTimer();
                }
            });
        },

        startSequence: function () {
            var self = this;
            var duration = (self.state.anuncio && self.state.anuncio.duracion_slide_ms) ? self.state.anuncio.duracion_slide_ms : self.config.defaultDuration;
            self.stopSequence();
            self.triggerDotAnimation(duration);

            self.state.timer = setInterval(function () {
                self.nextSlide();
            }, duration);
        },

        stopSequence: function () {
            var self = this;
            if (self.state.timer) {
                clearInterval(self.state.timer);
                self.state.timer = null;
            }
        },

        restartTimer: function () {
            var self = this;
            self.stopSequence();
            self.startSequence();
        },

        nextSlide: function () {
            var self = this;
            var nextIndex = (self.state.currentIndex + 1) % self.state.totalSlides;
            self.goToSlide(nextIndex);
        },

        goToSlide: function (index) {
            var self = this;
            var slides = $('.cinemini-slide');
            var dots = $('.cinemini-dot');

            slides.removeClass('active');
            slides.eq(index).addClass('active');

            dots.removeClass('active');
            dots.find('.cinemini-dot-fill').stop().css({ width: '0%' });
            dots.eq(index).addClass('active');

            self.state.currentIndex = index;
            $('#cinemini-counter').text((index + 1) + '/' + self.state.totalSlides);

            var duration = (self.state.anuncio && self.state.anuncio.duracion_slide_ms) ? self.state.anuncio.duracion_slide_ms : self.config.defaultDuration;
            self.triggerDotAnimation(duration);
        },

        triggerDotAnimation: function (duration) {
            var activeDot = $('.cinemini-dot.active .cinemini-dot-fill');
            activeDot.css({ width: '0%' }).animate({ width: '100%' }, duration, 'linear');
        },

        escapeHtml: function (text) {
            if (!text) return '';
            return $('<div>').text(text).html();
        }
    };

    window.CineMini = CineMini;
    CineMini.init();

})(jQuery);
