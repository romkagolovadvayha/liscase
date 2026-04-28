/**
 * Календарь вайпов: FullCalendar, DnD серверов, модалка, AJAX к WipeCalendarController.
 * Конфиг: window.WipeCalendarPage (задаётся из view).
 */
(function () {
  'use strict';

  var cfg = window.WipeCalendarPage || {};
  var calendar = null;
  var highlights = {};
  var serverListEl = document.getElementById('wipe-cal-server-list');
  var calendarEl = document.getElementById('wipe-cal-fc');
  var modalEl = document.getElementById('wipe-cal-modal');
  var backdropEl = document.getElementById('wipe-cal-modal-backdrop');
  var formErrorEl = document.getElementById('wipe-cal-form-error');

  function ymd(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }

  function hm(d) {
    var h = String(d.getHours()).padStart(2, '0');
    var min = String(d.getMinutes()).padStart(2, '0');
    return h + ':' + min;
  }

  function showError(msg) {
    if (!formErrorEl) return;
    formErrorEl.textContent = msg || '';
    formErrorEl.style.display = msg ? 'block' : 'none';
  }

  function openModal() {
    if (modalEl) modalEl.classList.remove('hidden');
    if (backdropEl) backdropEl.classList.remove('hidden');
    showError('');
  }

  function closeModal() {
    if (modalEl) modalEl.classList.add('hidden');
    if (backdropEl) backdropEl.classList.add('hidden');
  }

  /** Вайп карты и глобальный вайп требуют server_id на бэкенде. */
  function eventTypeNeedsServer(t) {
    return t === 'map_wipe' || t === 'global_wipe';
  }

  function getFormFields() {
    return {
      id: document.getElementById('wipe-cal-field-id'),
      date: document.getElementById('wipe-cal-field-date'),
      time: document.getElementById('wipe-cal-field-time'),
      type: document.getElementById('wipe-cal-field-type'),
      server: document.getElementById('wipe-cal-field-server'),
      title: document.getElementById('wipe-cal-field-title'),
      serverWrap: document.getElementById('wipe-cal-field-server-wrap'),
      titleWrap: document.getElementById('wipe-cal-field-title-wrap'),
      modalTitle: document.getElementById('wipe-cal-modal-title'),
      btnDelete: document.getElementById('wipe-cal-btn-delete'),
    };
  }

  function syncFieldVisibility() {
    var f = getFormFields();
    if (!f.type) return;
    var t = f.type.value;
    if (f.serverWrap) {
      f.serverWrap.style.display = eventTypeNeedsServer(t) ? 'block' : 'none';
    }
    if (f.server) {
      f.server.required = eventTypeNeedsServer(t);
    }
    if (f.titleWrap) {
      f.titleWrap.style.display = 'block';
    }
    if (f.title) {
      f.title.required = t === 'custom';
    }
    if (f.server && !eventTypeNeedsServer(t)) {
      f.server.value = '';
    }
  }

  function fillModal(opts) {
    opts = opts || {};
    var f = getFormFields();
    var isEdit = !!opts.id;
    if (f.modalTitle) {
      f.modalTitle.textContent = isEdit ? 'Редактировать событие' : 'Новое событие';
    }
    if (f.btnDelete) {
      if (isEdit) {
        f.btnDelete.classList.remove('hidden');
      } else {
        f.btnDelete.classList.add('hidden');
      }
    }
    if (f.id) f.id.value = opts.id ? String(opts.id) : '';
    if (f.date) {
      var d = opts.date || new Date();
      f.date.value = typeof d === 'string' ? d.substring(0, 10) : ymd(d);
    }
    if (f.time) {
      if (opts.time) {
        f.time.value = opts.time;
      } else if (opts.date && opts.date instanceof Date) {
        f.time.value = hm(opts.date);
      } else {
        f.time.value = '16:00';
      }
    }
    if (f.type) f.type.value = opts.eventType || 'map_wipe';
    if (f.server) {
      f.server.value = opts.serverId != null && opts.serverId !== '' ? String(opts.serverId) : '';
    }
    if (f.title) f.title.value = opts.title != null ? opts.title : '';
    syncFieldVisibility();
    openModal();
  }

  function fetchJson(url, options) {
    options = options || {};
    var headers = Object.assign(
      { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      options.headers || {}
    );
    if (options.method && options.method.toUpperCase() === 'POST' && cfg.csrf) {
      headers['X-CSRF-Token'] = cfg.csrf;
    }
    return fetch(url, Object.assign({}, options, { headers: headers, credentials: 'same-origin' })).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function postForm(body) {
    var fd = new FormData();
    Object.keys(body).forEach(function (k) {
      if (body[k] !== undefined && body[k] !== null) fd.append(k, body[k]);
    });
    if (cfg.csrfParam && cfg.csrf) {
      fd.append(cfg.csrfParam, cfg.csrf);
    }
    return fetch(cfg.saveUrl, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': cfg.csrf || '' },
    }).then(function (r) {
      return r.json();
    });
  }

  function postDelete(id) {
    var fd = new FormData();
    fd.append('id', String(id));
    if (cfg.csrfParam && cfg.csrf) fd.append(cfg.csrfParam, cfg.csrf);
    return fetch(cfg.deleteUrl, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': cfg.csrf || '' },
    }).then(function (r) {
      return r.json();
    });
  }

  function loadHighlights(start, end, done) {
    var url =
      cfg.highlightsUrl +
      '?start=' +
      encodeURIComponent(ymd(start)) +
      '&end=' +
      encodeURIComponent(ymd(end));
    fetchJson(url)
      .then(function (data) {
        highlights = data.days || {};
        if (done) done();
      })
      .catch(function () {
        highlights = {};
        if (done) done();
      });
  }

  function renderServers(servers) {
    if (!serverListEl) return;
    var sel = document.getElementById('wipe-cal-field-server');
    if (sel) {
      sel.innerHTML = '<option value="">—</option>';
      (servers || []).forEach(function (s) {
        var o = document.createElement('option');
        o.value = String(s.id);
        o.textContent = s.name + ' (' + (s.statusLabel || s.status) + ')';
        sel.appendChild(o);
      });
    }
    serverListEl.innerHTML = '';
    (servers || []).forEach(function (s) {
      var card = document.createElement('div');
      card.className = 'wipe-cal-server-card';
      card.setAttribute('data-server-id', String(s.id));
      card.setAttribute('data-name', s.name);
      if (s.status === 1) {
        card.classList.add('wipe-cal-server-card--active');
      } else {
        card.classList.add('wipe-cal-server-card--off');
      }
      card.innerHTML =
        '<div class="wipe-cal-server-name">' +
        escapeHtml(s.name) +
        '</div><div class="wipe-cal-server-meta">' +
        escapeHtml(s.statusLabel || '') +
        (s.tag ? ' · ' + escapeHtml(s.tag) : '') +
        '</div>';
      serverListEl.appendChild(card);
    });

    if (typeof FullCalendar !== 'undefined' && FullCalendar.Interaction && FullCalendar.Interaction.Draggable) {
      new FullCalendar.Interaction.Draggable(serverListEl, {
        itemSelector: '.wipe-cal-server-card',
        eventData: function (el) {
          return {
            title: el.getAttribute('data-name') || 'Сервер',
            duration: '01:00',
            create: true,
            extendedProps: {
              server_id: el.getAttribute('data-server-id')
                ? parseInt(el.getAttribute('data-server-id'), 10)
                : null,
            },
          };
        },
      });
    }
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function initCalendar() {
    if (!calendarEl || typeof FullCalendar === 'undefined') return;

    var loadedHlKey = '';
    var hlLoadingKey = null;
    var hlReq = 0;

    calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'ru',
      firstDay: 1,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: '',
      },
      buttonText: {
        today: 'Сегодня',
      },
      editable: true,
      droppable: true,
      eventDurationEditable: false,
      displayEventTime: true,
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
      events: function (info, successCallback, failureCallback) {
        var startDay = info.startStr.substring(0, 10);
        var endExclusive = new Date(info.endStr.substring(0, 10) + 'T12:00:00');
        var endInclusive = new Date(endExclusive.getTime());
        endInclusive.setDate(endInclusive.getDate() - 1);
        var endDay = ymd(endInclusive);
        var url =
          cfg.eventsUrl +
          '?start=' +
          encodeURIComponent(startDay) +
          '&end=' +
          encodeURIComponent(endDay);
        fetchJson(url)
          .then(function (data) {
            successCallback(data.events || []);
          })
          .catch(function (e) {
            failureCallback(e);
          });
      },
      datesSet: function (info) {
        var key = info.startStr + '_' + info.endStr;
        if (key === loadedHlKey) {
          return;
        }
        if (hlLoadingKey === key) {
          return;
        }
        hlLoadingKey = key;
        var myReq = ++hlReq;
        var hlEnd = new Date(info.end.getTime());
        hlEnd.setDate(hlEnd.getDate() - 1);
        loadHighlights(info.start, hlEnd, function () {
          if (myReq !== hlReq) {
            if (hlLoadingKey === key) {
              hlLoadingKey = null;
            }
            return;
          }
          hlLoadingKey = null;
          loadedHlKey = key;
          calendar.render();
        });
      },
      dayCellClassNames: function (arg) {
        var key = ymd(arg.date);
        var h = highlights[key];
        if (h === 'holiday') return ['wipe-cal-day--holiday'];
        if (h === 'weekend') return ['wipe-cal-day--weekend'];
        return [];
      },
      select: function (info) {
        calendar.unselect();
        fillModal({ date: info.start, eventType: 'custom', serverId: '', title: '' });
        syncFieldVisibility();
      },
      selectable: true,
      eventClick: function (info) {
        info.jsEvent.preventDefault();
        var ev = info.event;
        var id = ev.id;
        var start = ev.start;
        var xp = ev.extendedProps || {};
        fillModal({
          id: id,
          date: start,
          time: hm(start),
          eventType: xp.event_type || 'custom',
          serverId: xp.server_id != null ? xp.server_id : '',
          title: xp.title != null ? xp.title : '',
        });
      },
      eventDrop: function (info) {
        var ev = info.event;
        var xp = ev.extendedProps || {};
        postForm({
          id: ev.id,
          event_type: xp.event_type,
          server_id: xp.server_id != null && xp.server_id !== '' ? xp.server_id : '',
          title: xp.title != null ? xp.title : '',
          date: ymd(ev.start),
          time: hm(ev.start),
        })
          .then(function (data) {
            if (!data.success) {
              info.revert();
              alert(data.message || 'Ошибка сохранения');
            }
          })
          .catch(function () {
            info.revert();
          });
      },
      eventReceive: function (info) {
        var ev = info.event;
        var xp = ev.extendedProps || {};
        var serverId = xp.server_id;
        info.revert();
        fillModal({
          date: ev.start,
          time: hm(ev.start),
          eventType: 'map_wipe',
          serverId: serverId,
          title: '',
        });
      },
    });

    calendar.render();
  }

  function bindModal() {
    var f = getFormFields();
    if (f.type) {
      f.type.addEventListener('change', syncFieldVisibility);
    }
    document.querySelectorAll('.wipe-cal-time-preset').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var t = btn.getAttribute('data-time');
        if (f.time && t) f.time.value = t;
      });
    });
    var btnClose = document.getElementById('wipe-cal-btn-close');
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (backdropEl) backdropEl.addEventListener('click', closeModal);

    var btnSave = document.getElementById('wipe-cal-btn-save');
    if (btnSave) {
      btnSave.addEventListener('click', function () {
        showError('');
        var fld = getFormFields();
        postForm({
          id: fld.id && fld.id.value ? fld.id.value : '',
          event_type: fld.type ? fld.type.value : '',
          server_id: fld.server ? fld.server.value : '',
          title: fld.title ? fld.title.value : '',
          date: fld.date ? fld.date.value : '',
          time: fld.time ? fld.time.value : '16:00',
        }).then(function (data) {
          if (data.success) {
            closeModal();
            if (calendar) calendar.refetchEvents();
          } else {
            showError(data.message || 'Ошибка');
          }
        });
      });
    }

    if (f.btnDelete) {
      f.btnDelete.addEventListener('click', function () {
        var fld = getFormFields();
        if (!fld.id || !fld.id.value) return;
        if (!confirm('Удалить событие?')) return;
        postDelete(fld.id.value).then(function (data) {
          if (data.success) {
            closeModal();
            if (calendar) calendar.refetchEvents();
          } else {
            alert(data.message || 'Не удалось удалить');
          }
        });
      });
    }

    var btnAdd = document.getElementById('wipe-cal-btn-add');
    if (btnAdd) {
      btnAdd.addEventListener('click', function () {
        fillModal({ date: new Date(), eventType: 'custom', title: '' });
        syncFieldVisibility();
      });
    }
  }

  function boot() {
    if (!cfg.eventsUrl) return;
    bindModal();
    fetchJson(cfg.serversUrl)
      .then(function (data) {
        renderServers(data.servers || []);
        initCalendar();
      })
      .catch(function () {
        initCalendar();
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
