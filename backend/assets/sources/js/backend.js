$(document).on('change', '#country-dashboard, #deposit-period, #expiration-period, #currency-type, #duration-period, #date-period, #fund-id', function () {
    $(this).closest('form').submit();
});

(function initMailingComposer() {
    var $form = $('#telegram-constructor-form');
    if (!$form.length) return;

    var $bot = $form.find('input[name="TelegramConstructor[bot_id]"]');
    var $audience = $('#telegramconstructor-audience_id');
    var $onlyWithUser = $('#telegramconstructor-only_with_user');
    var $message = $('#telegramconstructor-telegram_constructor_message_id');
    var $audienceResult = $('#mailing-audience-result');
    var $audienceText = $audienceResult.find('.mailing-audience-result__text');
    var $audienceLink = $('#mailing-audience-preview-link');
    var $messagePreview = $('#mailing-message-preview');
    var $templateLink = $('#mailing-selected-template-link');
    var $summaryChannel = $('#mailing-summary-channel');
    var $summaryAudience = $('#mailing-summary-audience');
    var $summaryTemplate = $('#mailing-summary-template');
    var requestSequence = 0;

    function botValue() {
        return $bot.filter(':checked').val();
    }

    function selectedText($select) {
        var text = $select.find('option:selected').text();
        return $select.val() ? text : '—';
    }

    function updateSummary() {
        var $selectedBot = $bot.filter(':checked');
        $summaryChannel.text($selectedBot.closest('.mailing-channel-option').find('strong').text() || '—');
        $summaryTemplate.text(selectedText($message));
        if (!$audience.val()) $summaryAudience.text('—');
    }

    function recipientWord(count) {
        var lastTwo = count % 100;
        var last = count % 10;
        if (last === 1 && lastTwo !== 11) return 'получатель';
        if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) return 'получателя';
        return 'получателей';
    }

    function toggleVkOption() {
        var isVk = String(botValue()) === String($form.data('vk-bot-id'));
        $('#mailing-vk-option').prop('hidden', !isVk);
        if (!isVk) $onlyWithUser.prop('checked', false);
    }

    function updateAudience() {
        var botId = botValue();
        var audienceId = $audience.val();
        var currentRequest = ++requestSequence;
        $audienceLink.prop('hidden', true);

        if (!botId || !audienceId) {
            $audienceResult.removeClass('is-loading');
            $audienceResult.find('.mailing-audience-result__icon i').attr('class', 'fa-solid fa-users');
            $audienceText.text('Выберите аудиторию — здесь появится точное количество.');
            $summaryAudience.text('—');
            return;
        }

        $audienceResult.addClass('is-loading');
        $audienceResult.find('.mailing-audience-result__icon i').attr('class', 'fa-solid fa-rotate');
        $audienceText.text('Считаем доступных получателей…');
        var requestData = {
            bot_id: botId,
            audience_id: audienceId,
            only_with_user: $onlyWithUser.is(':checked') ? 1 : 0
        };

        $.getJSON($form.data('audience-count-url'), requestData)
            .done(function (response) {
                if (currentRequest !== requestSequence) return;
                $audienceResult.removeClass('is-loading');
                $audienceResult.find('.mailing-audience-result__icon i').attr('class', 'fa-solid fa-users');
                if (!response.success) {
                    $audienceText.text(response.message || 'Не удалось получить количество.');
                    $summaryAudience.text('Не удалось посчитать');
                    return;
                }
                if (Number(response.count) === 0) {
                    $audienceText.html('<strong>Нет получателей</strong><span>Измените канал или аудиторию.</span>');
                    $summaryAudience.text('Нет получателей');
                    return;
                }
                var count = Number(response.count);
                $audienceText.html('<strong>' + response.formatted + ' ' + recipientWord(count) + '</strong><span>Количество актуально на эту минуту.</span>');
                $summaryAudience.text(response.formatted + ' · ' + selectedText($audience));
                var previewUrl = $form.data('audience-preview-url') + '?' + $.param(requestData);
                $audienceLink.attr('href', previewUrl).prop('hidden', false);
            })
            .fail(function () {
                if (currentRequest !== requestSequence) return;
                $audienceResult.removeClass('is-loading');
                $audienceResult.find('.mailing-audience-result__icon i').attr('class', 'fa-solid fa-triangle-exclamation');
                $audienceText.text('Не удалось проверить аудиторию. Повторите попытку.');
                $summaryAudience.text('Не удалось посчитать');
            });
    }

    function updateMessagePreview() {
        var messageId = $message.val();
        $summaryTemplate.text(selectedText($message));
        $templateLink.prop('hidden', !messageId);
        if (messageId && window.mailingComposerConfig) {
            $templateLink.attr('href', window.mailingComposerConfig.templateEdit.replace('__id__', messageId));
        }
        if (!messageId) {
            $messagePreview.html('<div class="mailing-preview-empty"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Выберите шаблон, чтобы увидеть сообщение.</span></div>');
            return;
        }

        $messagePreview.attr('aria-busy', 'true').html('<div class="mailing-preview-empty"><i class="fa-solid fa-rotate fa-spin" aria-hidden="true"></i><span>Загружаем шаблон…</span></div>');
        $.get($form.data('message-preview-url'), {id: messageId})
            .done(function (html) { $messagePreview.html(html); })
            .fail(function () { $messagePreview.html('<div class="mailing-preview-empty"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>Не удалось загрузить предпросмотр.</span></div>'); })
            .always(function () { $messagePreview.removeAttr('aria-busy'); });
    }

    $bot.on('change', function () { toggleVkOption(); updateSummary(); updateAudience(); });
    $audience.add($onlyWithUser).on('change', updateAudience);
    $message.on('change', updateMessagePreview);
    toggleVkOption();
    updateSummary();
    updateAudience();
    updateMessagePreview();
})();

(function initMailingTemplateForm() {
    var $form = $('#mailing-template-form');
    if (!$form.length) return;

    var $mode = $form.find('input[name="TelegramConstructorMessageForm[image_mode]"]');
    var $deleteImage = $form.find('.is_delete_image');
    var $url = $('#telegram-message-image-url');
    var $file = $form.find('input[type="file"][name*="image_file"]');
    var $previewImage = $('#mailing-template-preview-image');
    var $previewText = $('#mailing-template-preview-text');
    var $counter = $('#mailing-message-character-count');
    var uploadPreviewUrl = $previewImage.data('upload-src') || '';
    var filePreviewUrl = '';

    function currentMode() {
        return $mode.filter(':checked').val() || 'none';
    }

    function visibleText(html) {
        var container = document.createElement('div');
        container.innerHTML = String(html || '').replace(/<br\s*\/?>/gi, '\n').replace(/<\/p\s*>/gi, '\n');
        return (container.textContent || '').trim();
    }

    function safePreviewHtml(html) {
        var source = document.createElement('div');
        var output = document.createElement('div');
        var allowed = ['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'A', 'UL', 'OL', 'LI', 'CODE'];
        source.innerHTML = String(html || '');

        function copyChildren(from, to) {
            Array.prototype.forEach.call(from.childNodes, function (node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    to.appendChild(document.createTextNode(node.textContent));
                    return;
                }
                if (node.nodeType !== Node.ELEMENT_NODE) return;
                if (allowed.indexOf(node.tagName) === -1) {
                    copyChildren(node, to);
                    return;
                }
                var clean = document.createElement(node.tagName.toLowerCase());
                if (node.tagName === 'A') {
                    var href = node.getAttribute('href') || '';
                    if (/^https?:\/\//i.test(href)) {
                        clean.setAttribute('href', href);
                        clean.setAttribute('target', '_blank');
                        clean.setAttribute('rel', 'noopener');
                    }
                }
                copyChildren(node, clean);
                to.appendChild(clean);
            });
        }

        copyChildren(source, output);
        return output.innerHTML;
    }

    function editorHtml() {
        if (window.mailingTemplateEditor && !window.mailingTemplateEditor.removed) {
            return window.mailingTemplateEditor.getContent();
        }
        return $('#telegram-message-editor').val() || '';
    }

    function updateTextPreview() {
        var html = editorHtml();
        var length = Array.from(visibleText(html)).length;
        $counter.text(length + ' / 4096').toggleClass('is-over-limit', length > 4096);
        if (visibleText(html)) {
            $previewText.html(safePreviewHtml(html));
        } else {
            $previewText.html('<span class="mailing-message-bubble__empty">Добавьте текст или изображение.</span>');
        }
    }

    function setPreviewImage(src) {
        if (src) {
            $previewImage.attr('src', src).prop('hidden', false);
        } else {
            $previewImage.removeAttr('src').prop('hidden', true);
        }
    }

    function updateImagePreview() {
        var mode = currentMode();
        $form.find('[data-mailing-media-panel]').prop('hidden', true);
        $form.find('[data-mailing-media-panel="' + mode + '"]').prop('hidden', false);
        $deleteImage.val(mode === 'none' ? 1 : 0);

        if (mode === 'url') {
            setPreviewImage($.trim($url.val()).replace(/\{user_id\}/g, '1'));
        } else if (mode === 'upload') {
            setPreviewImage(filePreviewUrl || uploadPreviewUrl);
        } else {
            setPreviewImage('');
        }
    }

    function updateButtonsPreview() {
        var $target = $('#mailing-template-preview-buttons').empty();
        $('#sortable-buttons .telegram_message_buttons_item').each(function () {
            var title = $(this).find('.button_title[data-language="ru-RU"]').val() || 'Кнопка без названия';
            $('<span>').text(title).appendTo($target);
        });
        $target.prop('hidden', !$target.children().length);
        $('.mailing-empty-buttons').prop('hidden', $('#sortable-buttons .telegram_message_buttons_item').length > 0);
    }

    window.updateMailingTemplateButtons = updateButtonsPreview;
    document.addEventListener('mailing:editor-change', updateTextPreview);
    $mode.on('change', updateImagePreview);
    $url.on('input change', updateImagePreview);
    $file.on('change', function () {
        var file = this.files && this.files[0];
        if (filePreviewUrl) URL.revokeObjectURL(filePreviewUrl);
        filePreviewUrl = file ? URL.createObjectURL(file) : '';
        updateImagePreview();
    });

    updateTextPreview();
    updateImagePreview();
    updateButtonsPreview();
})();

(function initMailingButtons() {
    var $modal = $('#modalFormAddButtonTgConstructor');
    if (!$modal.length) return;

    var buttonBeingEdited = null;
    var nextIndex = $('#sortable-buttons .telegram_message_buttons_item').length;
    var currentLanguage = 'ru-RU';

    function clearButtonForm() {
        $('.telegramConstructorMessageButtonTitle').val('');
        $('#telegramConstructorMessageButtonUrl').val('');
        $('#telegramConstructorMessageButtonMessageId').val(null).trigger('change');
        $('.mailing-button-error').prop('hidden', true).text('');
    }

    function showButtonError(message) {
        $('.mailing-button-error').text(message).prop('hidden', false);
    }

    function refreshButtonText() {
        $('#sortable-buttons .telegram_message_buttons_item').each(function () {
            var title = $(this).find('.button_title[data-language="' + currentLanguage + '"]').val() || 'Кнопка без названия';
            $(this).find('.telegram_message_buttons_item_title').text(title);
        });
    }

    $(document).on('click', '.button_add', function () {
        buttonBeingEdited = null;
        clearButtonForm();
    });

    $(document).on('click', '.telegram_message_buttons_item_delete', function () {
        $(this).closest('.telegram_message_buttons_item_wrap').remove();
        if (window.updateMailingTemplateButtons) window.updateMailingTemplateButtons();
    });

    $(document).on('click', '.telegram_message_buttons_item_update', function () {
        buttonBeingEdited = $(this).closest('.telegram_message_buttons_item_wrap');
        clearButtonForm();
        buttonBeingEdited.find('.button_title').each(function () {
            $('.telegramConstructorMessageButtonTitle[data-language="' + $(this).data('language') + '"]').val($(this).val());
        });
        $('#telegramConstructorMessageButtonUrl').val(buttonBeingEdited.find('.button_url').val() || '');
        $('#telegramConstructorMessageButtonMessageId').val(buttonBeingEdited.find('.button_messageId').val() || null).trigger('change');
    });

    $(document).on('click', '#modalFormAddButtonTgConstructor .addButton', function () {
        var title = $.trim($('.telegramConstructorMessageButtonTitle[data-language="ru-RU"]').val());
        var url = $.trim($('#telegramConstructorMessageButtonUrl').val());
        var messageId = $('#telegramConstructorMessageButtonMessageId').val();
        $('.mailing-button-error').prop('hidden', true).text('');

        if (!title) {
            showButtonError('Введите название кнопки.');
            return;
        }
        if ((!url && !messageId) || (url && messageId)) {
            showButtonError('Выберите одно действие: ссылку или ответное сообщение.');
            return;
        }
        if (url) {
            try {
                var parsedUrl = new URL(url);
                if (parsedUrl.protocol !== 'http:' && parsedUrl.protocol !== 'https:') throw new Error('protocol');
            } catch (error) {
                showButtonError('Введите полную ссылку, начинающуюся с https://');
                return;
            }
        }

        var titles = [];
        $('.telegramConstructorMessageButtonTitle').each(function () {
            titles.push({text: $.trim(this.value), language: this.dataset.language});
        });
        nextIndex += 1;
        $.get('/telegram-constructor-message/get-button', {
            messageId: messageId || '',
            titles: JSON.stringify(titles),
            languages: JSON.stringify(window.languages || []),
            url: url,
            index: nextIndex
        }).done(function (html) {
            var $button = $($.parseHTML(html.trim()));
            if (buttonBeingEdited) {
                buttonBeingEdited.replaceWith($button);
                buttonBeingEdited = null;
            } else {
                $('#sortable-buttons').append($button);
            }
            refreshButtonText();
            if (window.updateMailingTemplateButtons) window.updateMailingTemplateButtons();
            clearButtonForm();
            var modalInstance = bootstrap.Modal.getInstance($modal[0]);
            if (modalInstance) modalInstance.hide();
        }).fail(function () {
            showButtonError('Не удалось сохранить кнопку. Повторите попытку.');
        });
    });

    $(document).on('click', '.fileinput-remove', function () {
        $(this).closest('.file-input').siblings('.is_delete_image').val(1);
        $('.is_delete_image').val(1);
    });

    if ($.fn.sortable) {
        $('#sortable-buttons').sortable({axis: 'y', handle: '.telegram_message_buttons_item_drag'});
    }
})();

// Функция updateAudienceId удалена, используется новая логика в форме
if ($.fn.fileinputLocales && $.fn.fileinputLocales.ru) {
    $.fn.fileinputLocales.ru.dropZoneTitle = 'Выберите файл';
}

function initBackend() {
    $('.color_picker').off('input.adminColor').on('input.adminColor', function () {
        var target = document.getElementById(this.getAttribute('data-color-target'));
        if (target) {
            target.value = this.value;
            target.dispatchEvent(new Event('change', {bubbles: true}));
        }
    });
    $('.color_picker_text').off('input.adminColor').on('input.adminColor', function () {
        var picker = document.getElementById(this.getAttribute('data-color-picker'));
        if (picker && /^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test(this.value)) {
            picker.value = this.value.slice(0, 7);
        }
    });
}

function initMobileSidebar() {
    const sidebarWrapper = $('.sidebar-wrapper');
    const sidebar = $('#main-sidebar');
    const overlay = $('#sidebar-overlay');
    const pushMenuBtn = $('#mobile-menu-toggle');
    const sidebarCollapseBtn = $('#sidebar-collapse-btn');
    const MOBILE_BREAKPOINT = 768;
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function isMobile() {
        return $(window).width() < MOBILE_BREAKPOINT;
    }

    function openSidebar() {
        if (!isMobile()) return;
        sidebarWrapper.addClass('sidebar-open');
        overlay.addClass('active');
        $('body').addClass('sidebar-open');
        pushMenuBtn.attr('aria-expanded', 'true');
        sidebar.attr({'role': 'dialog', 'aria-modal': 'true'});
        (sidebar.find('[data-sidebar-search]')[0] || sidebar.find(focusableSelector)[0])?.focus();
    }

    function closeSidebar(returnFocus) {
        sidebarWrapper.removeClass('sidebar-open');
        overlay.removeClass('active');
        $('body').removeClass('sidebar-open');
        pushMenuBtn.attr('aria-expanded', 'false');
        sidebar.removeAttr('role aria-modal');
        if (returnFocus) pushMenuBtn.trigger('focus');
    }

    function toggleSidebar() {
        sidebarWrapper.hasClass('sidebar-open') ? closeSidebar(true) : openSidebar();
    }

    pushMenuBtn.off('click.mobileSidebar').on('click.mobileSidebar', function(e) {
        e.preventDefault();
        if (isMobile()) {
            toggleSidebar();
        }
    });

    overlay.off('click.mobileSidebar').on('click.mobileSidebar', function() {
        closeSidebar(true);
    });

    sidebarCollapseBtn.off('click.mobileSidebar').on('click.mobileSidebar', function(e) {
        if (isMobile()) {
            e.preventDefault();
            closeSidebar(true);
        }
    });

    sidebarWrapper.off('click.mobileSidebar', 'a.sidebar-menu-link').on('click.mobileSidebar', 'a.sidebar-menu-link', function() {
        if (isMobile()) {
            setTimeout(function() { closeSidebar(false); }, 200);
        }
    });

    $(document).off('keydown.mobileSidebar').on('keydown.mobileSidebar', function(e) {
        if (!isMobile() || !$('body').hasClass('sidebar-open')) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            closeSidebar(true);
            return;
        }
        if (e.key !== 'Tab') return;

        const focusable = Array.from(sidebar[0].querySelectorAll(focusableSelector)).filter(function(element) {
            return !element.hidden && element.offsetParent !== null;
        });
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    $(window).off('resize.mobileSidebar').on('resize.mobileSidebar', function() {
        if ($(window).width() >= MOBILE_BREAKPOINT) {
            closeSidebar(false);
        }
    });
}

function initAdminSidebar() {
    const sidebar = document.getElementById('main-sidebar');
    const layout = document.getElementById('admin-layout-grid');
    const collapseButton = document.getElementById('sidebar-collapse-btn');
    if (!sidebar || !collapseButton) return;

    const setCollapsed = function(collapsed) {
        sidebar.classList.toggle('sidebar-collapsed', collapsed);
        layout?.classList.toggle('sidebar-collapsed', collapsed);
        collapseButton.setAttribute('aria-expanded', String(!collapsed));
        collapseButton.setAttribute('aria-label', collapsed ? 'Развернуть меню' : 'Свернуть меню');
        collapseButton.setAttribute('title', collapsed ? 'Развернуть меню' : 'Свернуть меню');
    };

    setCollapsed(localStorage.getItem('admin-sidebar-collapsed') === 'true');
    collapseButton.onclick = function() {
        if (window.innerWidth < 768) return;
        const collapsed = !sidebar.classList.contains('sidebar-collapsed');
        setCollapsed(collapsed);
        localStorage.setItem('admin-sidebar-collapsed', String(collapsed));
    };

    sidebar.querySelectorAll('.sidebar-submenu-toggle').forEach(function(button) {
        button.onclick = function() {
            const target = document.getElementById(button.getAttribute('data-submenu-target'));
            if (!target) return;
            const open = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(open));
            target.hidden = !open;
        };
    });

    const search = sidebar.querySelector('[data-sidebar-search]');
    const empty = sidebar.querySelector('[data-sidebar-empty]');
    if (search) {
        search.oninput = function() {
            const query = search.value.trim().toLocaleLowerCase('ru');
            let visibleItems = 0;
            sidebar.querySelectorAll('[data-sidebar-menu-item]').forEach(function(item) {
                if (item.closest('.sidebar-submenu') && !item.parentElement.closest('[data-sidebar-menu-item]')) return;
                const text = item.textContent.toLocaleLowerCase('ru');
                const visible = query === '' || text.includes(query);
                item.hidden = !visible;
                if (visible) {
                    visibleItems++;
                    if (query !== '') {
                        const submenu = item.querySelector(':scope > .sidebar-submenu');
                        const toggle = item.querySelector(':scope > .sidebar-submenu-toggle');
                        if (submenu && toggle) {
                            submenu.hidden = false;
                            toggle.setAttribute('aria-expanded', 'true');
                        }
                    }
                }
            });
            sidebar.querySelectorAll('[data-sidebar-section]').forEach(function(section) {
                section.hidden = !Array.from(section.querySelectorAll(':scope > .sidebar-menu > [data-sidebar-menu-item]')).some(function(item) {
                    return !item.hidden;
                });
            });
            if (empty) empty.hidden = visibleItems !== 0;
        };
    }
}

function initAdminUserMenu() {
    const button = document.getElementById('user-menu-toggle');
    const menu = document.getElementById('admin-user-dropdown');
    if (!button || !menu) return;

    const close = function(returnFocus) {
        menu.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        if (returnFocus) button.focus();
    };
    const open = function() {
        menu.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        menu.querySelector('[role="menuitem"]')?.focus();
    };

    button.onclick = function(e) {
        e.stopPropagation();
        menu.hidden ? open() : close(false);
    };
    document.addEventListener('click', function(e) {
        if (!menu.hidden && !e.target.closest('.admin-user-menu')) close(false);
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !menu.hidden) close(true);
    });
}

function initSettingsWorkspace() {
    const workspace = document.querySelector('[data-settings-workspace]');
    if (!workspace) return;

    const navigationToggle = workspace.querySelector('[data-settings-nav-toggle]');
    const navigationContent = workspace.querySelector('[data-settings-nav-content]');
    if (navigationToggle && navigationContent) {
        navigationToggle.onclick = function() {
            const open = navigationToggle.getAttribute('aria-expanded') !== 'true';
            navigationToggle.setAttribute('aria-expanded', String(open));
            navigationToggle.setAttribute('aria-label', open ? 'Скрыть разделы настроек' : 'Показать разделы настроек');
            navigationContent.classList.toggle('is-open', open);
            if (open) navigationContent.querySelector('input')?.focus();
        };
    }

    const categorySearch = workspace.querySelector('[data-settings-category-search]');
    const categoryEmpty = workspace.querySelector('[data-settings-category-empty]');
    if (categorySearch) {
        categorySearch.oninput = function() {
            const query = categorySearch.value.trim().toLocaleLowerCase('ru');
            let found = 0;
            workspace.querySelectorAll('[data-settings-category-item]').forEach(function(item) {
                const visible = query === '' || (item.dataset.searchValue || '').includes(query);
                item.hidden = !visible;
                if (visible) found++;
            });
            workspace.querySelectorAll('[data-settings-category-group]').forEach(function(group) {
                group.hidden = !Array.from(group.querySelectorAll('[data-settings-category-item]')).some(function(item) {
                    return !item.hidden;
                });
            });
            if (categoryEmpty) categoryEmpty.hidden = found !== 0;
        };
    }

    const fieldSearch = workspace.querySelector('[data-settings-field-search]');
    const fieldEmpty = workspace.querySelector('[data-settings-fields-empty]');
    if (fieldSearch) {
        fieldSearch.oninput = function() {
            const query = fieldSearch.value.trim().toLocaleLowerCase('ru');
            let found = 0;
            workspace.querySelectorAll('[data-setting-field]').forEach(function(field) {
                const visible = query === '' || (field.dataset.searchValue || '').includes(query);
                field.hidden = !visible;
                if (visible) found++;
            });
            if (fieldEmpty) fieldEmpty.hidden = found !== 0;
        };
    }

    workspace.querySelectorAll('[data-secret-toggle]').forEach(function(button) {
        button.onclick = function() {
            const input = document.getElementById(button.getAttribute('data-secret-toggle'));
            if (!input) return;
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-pressed', String(!showing));
            button.setAttribute('aria-label', showing ? 'Показать введённое значение' : 'Скрыть введённое значение');
            button.querySelector('i')?.classList.toggle('fa-eye', showing);
            button.querySelector('i')?.classList.toggle('fa-eye-slash', !showing);
        };
    });

    workspace.querySelectorAll('.admin-switch__input').forEach(function(input) {
        const sync = function() {
            const state = input.closest('.admin-switch')?.querySelector('[data-switch-state]');
            if (state) state.textContent = input.checked ? 'Включено' : 'Выключено';
        };
        input.addEventListener('change', sync);
        sync();
    });

    const form = workspace.querySelector('[data-settings-form]');
    if (form) {
        const status = form.querySelector('[data-settings-save-status]');
        const savebar = form.querySelector('.settings-savebar');
        const markDirty = function() {
            form.dataset.dirty = 'true';
            savebar?.classList.add('is-dirty');
            if (status) status.textContent = 'Есть несохранённые изменения';
        };
        form.querySelectorAll('input, textarea, select').forEach(function(control) {
            control.addEventListener('change', markDirty);
            if (control.type === 'text' || control.type === 'password' || control.tagName === 'TEXTAREA') {
                control.addEventListener('input', markDirty);
            }
        });
        form.addEventListener('submit', function() {
            form.dataset.submitting = 'true';
            if (status) status.textContent = 'Сохраняем…';
            form.querySelector('[data-settings-submit]')?.setAttribute('disabled', 'disabled');
        });
        window.addEventListener('beforeunload', function(e) {
            if (form.dataset.dirty === 'true' && form.dataset.submitting !== 'true') {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
}

function initFiltersDrawer() {
    const button = document.getElementById('filters-drawer-toggle');
    const backdrop = document.getElementById('filters-drawer-backdrop');
    const wrapper = document.getElementById('filters-wrapper');
    const slot = document.getElementById('filters-drawer-slot');
    if (!button || !backdrop || !wrapper || !slot) return;

    // Dense admin tables need the full content width on ordinary laptops.
    // Keep filters in the drawer until there is enough room for a 310px aside.
    const usesDrawer = function() { return window.innerWidth < 1600; };
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const close = function() {
        document.body.classList.remove('filters-drawer-open');
        backdrop.setAttribute('aria-hidden', 'true');
        button.setAttribute('aria-expanded', 'false');
        wrapper.setAttribute('role', 'complementary');
        wrapper.removeAttribute('aria-modal');
        if (wrapper.parentNode === document.body) slot.appendChild(wrapper);
        button.focus();
    };
    const open = function() {
        if (!usesDrawer()) return;
        if (wrapper.parentNode === slot) document.body.appendChild(wrapper);
        document.body.classList.add('filters-drawer-open');
        backdrop.setAttribute('aria-hidden', 'false');
        button.setAttribute('aria-expanded', 'true');
        wrapper.setAttribute('role', 'dialog');
        wrapper.setAttribute('aria-modal', 'true');
        (wrapper.querySelector(focusableSelector) || wrapper).focus();
    };

    button.onclick = open;
    backdrop.onclick = close;
    document.body.addEventListener('click', function(e) {
        if (e.target.closest('.filters-drawer-close')) close();
    });
    document.addEventListener('keydown', function(e) {
        if (!document.body.classList.contains('filters-drawer-open')) return;
        if (e.key === 'Escape') {
            close();
            return;
        }
        if (e.key === 'Tab') {
            const focusable = Array.from(wrapper.querySelectorAll(focusableSelector));
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });
    window.addEventListener('resize', function() {
        if (!usesDrawer() && document.body.classList.contains('filters-drawer-open')) close();
    });
}

function initAdminKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        const target = e.target;
        const typing = target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
        if (!typing && e.key === '/') {
            const sidebarSearch = document.querySelector('[data-sidebar-search]');
            if (sidebarSearch) {
                e.preventDefault();
                if (window.innerWidth < 768 && !document.body.classList.contains('sidebar-open')) {
                    document.getElementById('mobile-menu-toggle')?.click();
                    window.requestAnimationFrame(function() { sidebarSearch.focus(); });
                } else {
                    sidebarSearch.focus();
                }
            }
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            const settingsSearch = document.querySelector('[data-settings-category-search]');
            if (settingsSearch) {
                e.preventDefault();
                const workspace = settingsSearch.closest('[data-settings-workspace]');
                const navigationToggle = workspace?.querySelector('[data-settings-nav-toggle]');
                const navigationContent = workspace?.querySelector('[data-settings-nav-content]');
                if (navigationToggle && navigationContent && settingsSearch.offsetParent === null) {
                    navigationToggle.setAttribute('aria-expanded', 'true');
                    navigationToggle.setAttribute('aria-label', 'Скрыть разделы настроек');
                    navigationContent.classList.add('is-open');
                    window.requestAnimationFrame(function() { settingsSearch.focus(); });
                } else {
                    settingsSearch.focus();
                }
            }
        }
    });
}

function initCustomDialogFocusTrap() {
    if (document.body.dataset.customDialogFocusTrap === 'ready') return;
    document.body.dataset.customDialogFocusTrap = 'ready';
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Tab') return;
        const dialogs = Array.from(document.querySelectorAll('[role="dialog"][aria-modal="true"]')).filter(function(dialog) {
            return dialog.getAttribute('aria-hidden') !== 'true' && !dialog.hidden && !dialog.classList.contains('hidden') && dialog.offsetParent !== null;
        });
        const dialog = dialogs[dialogs.length - 1];
        if (!dialog) return;

        const focusable = Array.from(dialog.querySelectorAll(focusableSelector)).filter(function(element) {
            return element.offsetParent !== null && element.getAttribute('aria-hidden') !== 'true';
        });
        if (focusable.length === 0) {
            e.preventDefault();
            dialog.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });
}

function initAccessibleGridFilters() {
    document.querySelectorAll('table').forEach(function(table) {
        const headings = Array.from(table.querySelectorAll('thead tr:not(.filters):first-child th'));
        table.querySelectorAll('thead tr.filters').forEach(function(filterRow) {
            Array.from(filterRow.children).forEach(function(cell, index) {
                const heading = headings[index];
                const headingText = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
                cell.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(function(control) {
                    const hasReadableLabel = Array.from(control.labels || []).some(function(label) {
                        return label.textContent.replace(/\s+/g, ' ').trim().length > 0;
                    });
                    if (hasReadableLabel || control.hasAttribute('aria-label') || control.hasAttribute('aria-labelledby')) return;
                    const fallback = control.getAttribute('placeholder') || control.getAttribute('name') || 'значение';
                    control.setAttribute('aria-label', headingText ? 'Фильтр: ' + headingText : 'Фильтр: ' + fallback);
                });
            });
        });
    });
}

function initAccessibleTables() {
    const pageTitle = document.querySelector('.page-title-header__title, h1')
        ?.textContent.replace(/\s+/g, ' ').trim();

    document.querySelectorAll('table').forEach(function(table) {
        const hasAccessibleName = table.querySelector('caption')
            || table.hasAttribute('aria-label')
            || table.hasAttribute('aria-labelledby');

        if (!hasAccessibleName) {
            const container = table.closest('.ds-card, section, article, .card, .table-responsive') || table.parentElement;
            const localHeading = container
                ?.querySelector('h1, h2, h3, h4, h5, h6, .card-title, .panel-title')
                ?.textContent.replace(/\s+/g, ' ').trim();
            const tableName = localHeading || pageTitle || 'Данные страницы';
            table.setAttribute('aria-label', 'Таблица: ' + tableName);
        }

        table.querySelectorAll('thead th').forEach(function(heading) {
            if (!heading.hasAttribute('scope')) heading.setAttribute('scope', 'col');
        });
        table.querySelectorAll('tbody tr').forEach(function(row) {
            const firstCell = row.querySelector(':scope > th:first-child');
            if (firstCell && !firstCell.hasAttribute('scope')) firstCell.setAttribute('scope', 'row');
        });
    });
}

function initAccessibleControls() {
    document.querySelectorAll('[aria-label="Close"]').forEach(function(control) {
        control.setAttribute('aria-label', 'Закрыть окно');
    });

    const controls = document.querySelectorAll('input:not([type="hidden"]), select, textarea');
    controls.forEach(function(control) {
        const hasReadableLabel = Array.from(control.labels || []).some(function(label) {
            return label.textContent.replace(/\s+/g, ' ').trim().length > 0;
        });
        if (hasReadableLabel || control.hasAttribute('aria-label') || control.hasAttribute('aria-labelledby')) return;

        const field = control.closest('.form-group, [class*="field-"], .admin-filter-field, .settings-field, .mb-3, .mb-4') || control.parentElement;
        const visualLabel = field?.querySelector('label');
        const labelText = visualLabel?.textContent.replace(/\s+/g, ' ').trim();
        const placeholder = control.getAttribute('placeholder')?.trim();
        const name = control.getAttribute('name')
            ?.replace(/^.*\[/, '')
            .replace(/\]$/, '')
            .replace(/[_-]+/g, ' ')
            .trim();
        const typeFallback = control.type === 'checkbox' || control.type === 'radio'
            ? 'Выбрать запись'
            : (control.type === 'submit' ? 'Отправить форму' : 'Поле формы');
        const accessibleName = labelText || placeholder || name || typeFallback;

        if (accessibleName) control.setAttribute('aria-label', accessibleName);
    });

    document.querySelectorAll('button[title], a.btn[title], [role="button"][title]').forEach(function(control) {
        if (!control.hasAttribute('aria-label') && !control.hasAttribute('aria-labelledby') && !control.textContent.trim()) {
            control.setAttribute('aria-label', control.getAttribute('title'));
        }
    });
}

function initAdminInterface() {
    initBackend();
    initMobileSidebar();
    initAdminSidebar();
    initAdminUserMenu();
    initSettingsWorkspace();
    initFiltersDrawer();
    initAccessibleTables();
    initAccessibleGridFilters();
    initAccessibleControls();
}

initAdminInterface();
initAdminKeyboardShortcuts();
initCustomDialogFocusTrap();

window.requestAnimationFrame(initAccessibleControls);
window.addEventListener('load', initAccessibleControls);

$(document).on('pjax:send', function() {

});
$(document).on('pjax:complete', function() {
    initAdminInterface();
});
