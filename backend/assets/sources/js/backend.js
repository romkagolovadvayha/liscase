$(document).on('change', '#country-dashboard, #deposit-period, #expiration-period, #currency-type, #duration-period, #date-period, #fund-id', function () {
    $(this).closest('form').submit();
});
$(document).on('click', '.telegram_message_buttons_item_delete', function (e) {
    e.preventDefault();
    $(this).parent().remove();
});
var button_updated = undefined;
$(document).on('click', '.button_add', function (e) {
    e.preventDefault();
    button_updated = undefined;
    button_form_clear();
});
function updateButtonsText() {
    var buttons = $('.telegram_message_buttons_item');
    console.log(buttons);
    for (var k = 0; k < buttons.length; k++) {
        var value = $(buttons[k]).find('.button_title[data-language="' + current_language + '"]').val();
        if (!value.length) {
            value = "Пусто...";
        }
        $(buttons[k]).find('.telegram_message_buttons_item_title').html(value);
    }
}
if ($('.constructor_message_preview')) {
    var message_id = $('#telegramconstructor-telegram_constructor_message_id').val();
    if (message_id > 0) {
        updateMessagePreview();
    }
    $(document).on('change', '#telegramconstructor-telegram_constructor_message_id', function (e) {
        updateMessagePreview();
    });
}
function updateMessagePreview() {
    var message_id = $('#telegramconstructor-telegram_constructor_message_id').val();
    $.ajax({
        url: '/telegram-constructor-message/get-message-preview',
        method: 'GET',
        data: {
            id: message_id,
        },
        success: function(message_preview) {
            $('.constructor_message_preview').html(message_preview);
        }
    });
}
$(document).on('click', '.telegram_message_buttons_item_update', function (e) {
    e.preventDefault();
    button_updated = $(this).parent().parent();
    var button_titles = $(this).parent().find('.button_title');
    for (var k = 0; k < button_titles.length; k++) {
        $('.telegramConstructorMessageButtonTitle[data-language="' + button_titles[k].dataset.language + '"]').val(button_titles[k].value);
    }
    var messageId = $(this).parent().find('.button_messageId');
    if (messageId.length) {
        $('#telegramConstructorMessageButtonMessageId').val(messageId.val()).change();
    }
    var url = $(this).parent().find('.button_url');
    if (url.length) {
        $('#telegramConstructorMessageButtonUrl').val(url.val());
    }
});
function button_form_clear() {
    var url = $('#telegramConstructorMessageButtonUrl');
    var message_id = $('#telegramConstructorMessageButtonMessageId');
    $('.telegramConstructorMessageButtonTitle').val('');
    url.val('');
    message_id.val('').change();
}
$(document).on('click', '#modalFormAddButtonTgConstructor .addButton', function (e) {
    e.preventDefault();
    var url = $('#telegramConstructorMessageButtonUrl');
    var message_id = $('#telegramConstructorMessageButtonMessageId');
    addButton(url.val(), message_id.val());
    $('.telegramConstructorMessageButtonTitle').val('');
    button_form_clear();
});
$(document).on('click', '.tg-preview_message .fileinput-remove', function () {
    $(this).parent().parent().parent().parent().find('.is_delete_image').val(1);
});
var current_language = 'ru-RU';
if ($('.tabs_tg_message')) {
    $('.tabs_tg_message:first-of-type').addClass('active');
    $('.tg-preview_message:first-of-type').addClass('active');
    $(document).on('click', '.tabs_tg_message a', function (e) {
        e.preventDefault();
        $('.tabs_tg_message').removeClass('active');
        $('.tg-preview_message').removeClass('active');
        $(this).parent().addClass('active');
        current_language = $(this).attr('data-language');
        updateButtonsText();
        $($(this).attr('href')).addClass('active');
    });
}
var prevIndexButton = undefined;
function addButton(url, message_id) {
    if (!url && !message_id) {
        return;
    }
    if (prevIndexButton === undefined) {
        prevIndexButton = $('.telegram_message_buttons .telegram_message_buttons_item').length;
    }
    prevIndexButton++;
    var titlesArr = [];
    var titlesElements = $('.telegramConstructorMessageButtonTitle');
    for (var k = 0; k < titlesElements.length; k++) {
        titlesArr[titlesArr.length] = {text: titlesElements[k].value, language: titlesElements[k].dataset.language};
    }
    $.ajax({
        url: '/telegram-constructor-message/get-button',
        method: 'GET',
        data: {
            messageId: message_id,
            titles: JSON.stringify(titlesArr),
            languages: JSON.stringify(languages),
            url: url,
            index: prevIndexButton
        },
        success: function(button){
            if (button_updated === undefined) {
                // новая кнопка
                button = $($.parseHTML(button));
                $('.telegram_message_buttons').append(button);
                updateButtonsText();
            } else {
                // обновить кнопку
                button = $($.parseHTML(button));
                console.log(button);
                button_updated.html(button.html());
                button_updated = undefined;
                updateButtonsText();
            }
        }
    });
}

$( "#sortable-buttons" ).sortable({
    connectWith: ".connectedSortable",
}).disableSelection();

// Функция updateAudienceId удалена, используется новая логика в форме
if ($.fn.filseinputLocales) {
    $.fn.filseinputLocales['ru']['dropZoneTitle'] = "Выберите файлы";
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
