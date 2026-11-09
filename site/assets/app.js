(function () {
  'use strict';

  var csrf = document.querySelector('meta[name=csrf]').content;

  function post(url, form) {
    return fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: form }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok) { throw new Error(data.error || 'Something went wrong. Please try again.'); }
        return data;
      });
    });
  }

    var compress = function (file) {
      if (!window.createImageBitmap) { return Promise.resolve(file); }
      return createImageBitmap(file).then(function (bitmap) {
        var scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
        var canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        return new Promise(function (resolve) {
          canvas.toBlob(function (blob) { resolve(blob || file); }, 'image/jpeg', 0.82);
        });
      }).catch(function () { return file; });
    };

  /* Dates are always shown and typed as dd/mm/yyyy, whatever the device language. */
  document.querySelectorAll('input[data-date]').forEach(function (input) {
    input.placeholder = 'dd/mm/yyyy';
    input.maxLength = 10;
    input.inputMode = 'numeric';
    input.autocomplete = 'off';
    input.pattern = '\\d{2}/\\d{2}/\\d{4}';
    input.title = 'Enter the date as dd/mm/yyyy';
    input.addEventListener('input', function () {
      var digits = input.value.replace(/\D/g, '').slice(0, 8);
      input.value = digits.replace(/^(\d{2})(\d)/, '$1/$2').replace(/^(\d{2}\/\d{2})(\d)/, '$1/$2');
    });
    var wrap = document.createElement('span');
    wrap.className = 'date-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var picker = document.createElement('input');
    picker.type = 'date';
    picker.className = 'sr-input';
    picker.tabIndex = -1;
    picker.setAttribute('aria-hidden', 'true');
    picker.addEventListener('change', function () {
      if (picker.value) {
        input.value = picker.value.split('-').reverse().join('/');
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'date-pick';
    button.setAttribute('aria-label', 'Open calendar');
    button.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>';
    button.addEventListener('click', function () {
      var match = input.value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
      picker.value = match ? match[3] + '-' + match[2] + '-' + match[1] : '';
      if (picker.showPicker) { picker.showPicker(); } else { picker.focus(); picker.click(); }
    });
    wrap.appendChild(picker);
    wrap.appendChild(button);
  });

  /* Photos open in a popup on the same page. */
  var lightbox = null;
  var openPhoto = function (url, title) {
    if (!lightbox) {
      lightbox = document.createElement('dialog');
      lightbox.className = 'lightbox';
      lightbox.innerHTML = '<div class="lightbox-head"><strong></strong><button type="button" class="btn">Close</button></div><img alt="">';
      lightbox.querySelector('button').addEventListener('click', function () { lightbox.close(); });
      lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) { lightbox.close(); }
      });
      lightbox.addEventListener('close', function () { lightbox.querySelector('img').removeAttribute('src'); });
      document.body.appendChild(lightbox);
    }
    lightbox.querySelector('strong').textContent = title;
    var image = lightbox.querySelector('img');
    image.alt = title;
    image.src = url;
    lightbox.showModal();
  };
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-photo]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey) { return; }
    event.preventDefault();
    openPhoto(link.getAttribute('href'), link.dataset.photo);
  });

  /* Show or hide the left menu; the choice is remembered on this device. */
  var menuToggle = document.getElementById('menu-toggle');
  if (menuToggle) {
    var root = document.documentElement;
    var drawToggle = function () {
      var hidden = root.classList.contains('menu-hidden');
      menuToggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
      menuToggle.setAttribute('aria-label', hidden ? 'Show menu' : 'Hide menu');
      menuToggle.title = hidden ? 'Show menu' : 'Hide menu';
    };
    menuToggle.addEventListener('click', function () {
      var hidden = root.classList.toggle('menu-hidden');
      try { localStorage.setItem('menuHidden', hidden ? '1' : '0'); } catch (error) { /* Works for this page only. */ }
      drawToggle();
    });
    drawToggle();
  }

  /* Filters apply on their own, without reloading the page. */
  document.querySelectorAll('form[data-live-filter]').forEach(function (form) {
    var results = document.getElementById('live-results');
    var timer = null;
    var latest = 0;
    var apply = function () {
      var params = new URLSearchParams();
      new FormData(form).forEach(function (value, key) {
        var field = form.elements[key];
        if (String(value).trim() !== '' && !(field && field.dataset && field.dataset.default === value)) { params.append(key, value); }
      });
      var url = location.pathname + (params.toString() ? '?' + params.toString() : '');
      var request = ++latest;
      fetch(url, { headers: { 'X-Requested-With': 'fetch' } }).then(function (response) { return response.text(); }).then(function (html) {
        if (request !== latest) { return; }
        var next = new DOMParser().parseFromString(html, 'text/html');
        results.innerHTML = next.getElementById('live-results').innerHTML;
        history.replaceState(null, '', url);
      }).catch(function () { location.href = url; });
    };
    var schedule = function (delay) {
      clearTimeout(timer);
      timer = setTimeout(apply, delay);
    };
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      schedule(0);
    });
    form.addEventListener('change', function (event) {
      if (event.target.tagName === 'SELECT') { schedule(0); }
    });
    form.addEventListener('input', function (event) {
      var target = event.target;
      if (target.matches('input[data-date]')) {
        if (target.value === '' || /^\d{2}\/\d{2}\/\d{4}$/.test(target.value)) { schedule(0); }
      } else if (target.type === 'search') {
        schedule(350);
      }
    });
  });

  /* Filter panel on the right of the page. */
  var filterForm = document.querySelector('form[data-filter-form]');
  if (filterForm) {
    var panel = filterForm.querySelector('[data-filter-panel]');
    var openButton = filterForm.querySelector('[data-filter-open]');
    var badge = filterForm.querySelector('[data-filter-count]');
    var fields = ['from', 'to', 'area'].map(function (name) { return filterForm.elements[name]; });
    var drawCount = function () {
      var active = fields.filter(function (field) { return field.value.trim() !== ''; }).length;
      badge.hidden = active === 0;
      badge.textContent = active;
    };
    var setPanel = function (open) {
      panel.hidden = !open;
      openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    openButton.addEventListener('click', function () { setPanel(panel.hidden); });
    filterForm.querySelector('[data-filter-close]').addEventListener('click', function () {
      setPanel(false);
      openButton.focus();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden && !document.querySelector('dialog[open]')) { setPanel(false); }
    });
    filterForm.querySelector('[data-filter-clear]').addEventListener('click', function () {
      fields.forEach(function (field) { field.value = ''; });
      filterForm.elements.group.value = filterForm.elements.group.dataset.default;
      drawCount();
      filterForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });
    filterForm.addEventListener('input', drawCount);
    filterForm.addEventListener('change', drawCount);
    drawCount();
  }

  /* Employee documents */
  document.querySelectorAll('input[data-compress]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files[0];
      if (!file || !window.DataTransfer) { return; }
      compress(file).then(function (blob) {
        var transfer = new DataTransfer();
        transfer.items.add(new File([blob], file.name || 'photo.jpg', { type: blob.type || file.type }));
        input.files = transfer.files;
      });
    });
  });

  /* Update form */
  var page = document.getElementById('document');
  if (page) {
    var id = page.dataset.id;
    var message = document.getElementById('form-message');

    var show = function (ok, text) {
      message.hidden = false;
      message.className = ok ? 'success' : 'error';
      message.textContent = text;
    };

    var refresh = function () {
      return fetch(location.href, { headers: { 'X-Requested-With': 'fetch' } }).then(function (response) { return response.text(); }).then(function (html) {
        var next = new DOMParser().parseFromString(html, 'text/html');
        ['doc-head', 'doc-details'].forEach(function (name) {
          var current = document.getElementById(name);
          if (current && next.getElementById(name)) { current.replaceWith(next.getElementById(name)); }
        });
        document.querySelectorAll('.photo-group').forEach(function (group) {
          group.replaceWith(next.getElementById(group.id));
        });
      });
    };

    var setBusy = function (group, busy) {
      group.querySelectorAll('button').forEach(function (button) { button.disabled = busy; });
    };

    page.addEventListener('click', function (event) {
      var pick = event.target.closest('[data-pick]');
      if (pick) {
        pick.parentElement.querySelectorAll('input[type=file]')[Number(pick.dataset.pick)].click();
        return;
      }
      var remove = event.target.closest('[data-remove]');
      if (remove) {
        var group = remove.closest('.photo-group');
        setBusy(group, true);
        post('/api/documents/' + id + '/attachments/' + remove.dataset.remove + '/delete', new FormData()).then(refresh).catch(function (error) {
          setBusy(group, false);
          show(false, error.message);
        });
        return;
      }
      var form = event.target.closest('#delete-form');
      if (form && event.target.matches('[data-confirm-delete], [data-keep]')) {
        var confirming = event.target.matches('[data-confirm-delete]');
        form.querySelector('[data-confirm-delete]').hidden = confirming;
        form.querySelector('[data-keep]').hidden = !confirming;
        form.querySelector('[data-do-delete]').hidden = !confirming;
      }
    });

    page.addEventListener('change', function (event) {
      var input = event.target;
      if (!input.matches('input[type=file][data-kind]') || !input.files[0]) { return; }
      var file = input.files[0];
      var group = input.closest('.photo-group');
      message.hidden = true;
      setBusy(group, true);
      compress(file).then(function (blob) {
        var form = new FormData();
        form.append('kind', input.dataset.kind);
        form.append('file', blob, file.name || 'photo.jpg');
        return post('/api/documents/' + id + '/attachments', form);
      }).then(refresh).catch(function (error) {
        setBusy(group, false);
        input.value = '';
        show(false, error.message);
      });
    });

    var save = document.getElementById('save-update');
    if (save) {
      save.addEventListener('click', function () {
        var remarks = document.getElementById('remarks');
        if (!remarks) { show(true, 'Update saved.'); return; }
        save.disabled = true;
        var form = new FormData();
        form.append('remarks', remarks.value);
        post('/documents/' + id + '/remarks', form).then(function (data) {
          show(true, data.message);
        }).catch(function (error) {
          show(false, error.message);
        }).finally(function () { save.disabled = false; });
      });
    }
  }

  /* Bulk upload */
  var bulk = document.getElementById('bulk');
  if (bulk) {
    var registers = JSON.parse(bulk.dataset.registers);
    var slug = bulk.dataset.initial;
    var chosen = null;
    var rows = null;
    var finished = false;
    var pageNo = 1;
    var el = function (name) { return document.getElementById('bulk-' + name); };
    var current = function () { return registers.filter(function (item) { return item.slug === slug; })[0]; };
    var outcomeCount = function (outcome) { return rows.filter(function (row) { return row.outcome === outcome; }).length; };

    var csvCell = function (value) {
      var text = String(value === null || value === undefined ? '' : value);
      if (/^[=+\-@\t\r]/.test(text)) { text = "'" + text; }
      return /[",\n\r]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    };

    var drawResult = function () {
      el('result').hidden = !rows;
      if (!rows) { return; }
      var ready = outcomeCount('ready'), skipped = outcomeCount('skipped'), failed = outcomeCount('failed');
      el('result-title').textContent = finished ? 'Upload result' : 'Check the file';
      el('summary').textContent = finished ? rows.length + ' rows processed' : ready + ' ready, ' + skipped + ' skipped, ' + failed + ' failed';
      el('download').hidden = skipped + failed === 0;
      el('save').hidden = finished;
      el('save').disabled = ready === 0;
      var body = el('rows');
      body.textContent = '';
      var last = Math.max(1, Math.ceil(rows.length / 20));
      pageNo = Math.min(pageNo, last);
      rows.slice((pageNo - 1) * 20, pageNo * 20).forEach(function (row) {
        var tr = document.createElement('tr');
        var values = [row.row, row.no, row.date || 'Blank', row.party, row.amount];
        values.forEach(function (value, index) {
          var td = document.createElement('td');
          td.textContent = value;
          if (index === 4) { td.className = 'right'; }
          tr.appendChild(td);
        });
        var result = document.createElement('td');
        result.style.fontWeight = '600';
        result.style.color = row.outcome === 'ready' ? '#0b5c55' : row.outcome === 'skipped' ? '#7a4b00' : '#a32a17';
        result.textContent = row.outcome === 'ready' ? (finished ? 'Saved' : 'Ready') : (row.outcome === 'skipped' ? 'Skipped: ' : 'Failed: ') + row.reason;
        tr.appendChild(result);
        body.appendChild(tr);
      });
      el('pager').hidden = rows.length <= 20;
      el('page').textContent = 'Showing ' + ((pageNo - 1) * 20 + 1) + '-' + Math.min(rows.length, pageNo * 20) + ' of ' + rows.length + ' \u00B7 Page ' + pageNo + ' of ' + last;
      el('prev').hidden = pageNo <= 1;
      el('next').hidden = pageNo >= last;
    };

    var clearStatus = function () {
      el('error').hidden = true;
      el('done').hidden = true;
    };

    var draw = function () {
      var register = current();
      el('title').textContent = register.label + ' template columns';
      el('template').href = '/api/templates/' + slug;
      var chips = el('chips');
      chips.textContent = '';
      register.columns.forEach(function (column, index) {
        var chip = document.createElement('span');
        chip.className = 'chip';
        chip.textContent = (index + 1) + '. ' + column;
        if (register.required[index]) {
          var mark = document.createElement('span');
          mark.className = 'req';
          mark.setAttribute('aria-label', 'required');
          chip.appendChild(mark);
        }
        chips.appendChild(chip);
      });
      drawResult();
    };

    var send = function (mode) {
      var form = new FormData();
      form.append('file', chosen);
      form.append('mode', mode);
      el('pick-text').textContent = 'Checking...';
      el('save').disabled = true;
      clearStatus();
      post('/api/bulk/' + slug, form).then(function (data) {
        rows = data.rows;
        pageNo = 1;
        finished = mode === 'confirm';
        if (finished) {
          var done = el('done');
          done.textContent = 'Upload complete: ' + data.created + ' created, ' + data.skipped + ' skipped, ' + data.failed + ' failed. New rows are Pending. ';
          var view = document.createElement('a');
          view.href = '/r/' + slug;
          view.textContent = 'View ' + current().label;
          done.appendChild(view);
          done.hidden = false;
        }
        drawResult();
      }).catch(function (error) {
        rows = null;
        el('error').textContent = error.message;
        el('error').hidden = false;
        drawResult();
      }).finally(function () { el('pick-text').textContent = 'Upload filled file'; });
    };

    el('file').addEventListener('change', function (event) {
      chosen = event.target.files[0] || null;
      finished = false;
      if (chosen) {
        el('name').textContent = chosen.name;
        event.target.value = '';
        send('preview');
      }
    });
    el('prev').addEventListener('click', function () { pageNo -= 1; drawResult(); });
    el('next').addEventListener('click', function () { pageNo += 1; drawResult(); });
    el('save').addEventListener('click', function () { if (chosen) { send('confirm'); } });
    el('download').addEventListener('click', function () {
      var header = ['Row'].concat(current().columns, ['Reason']).map(csvCell).join(',');
      var lines = rows.filter(function (row) { return row.outcome !== 'ready'; }).map(function (row) {
        return [row.row].concat(row.cells, [row.reason]).map(csvCell).join(',');
      });
      var url = URL.createObjectURL(new Blob(['﻿' + [header].concat(lines).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
      var link = document.createElement('a');
      link.href = url;
      link.download = slug + '-rows-to-fix.csv';
      link.click();
      URL.revokeObjectURL(url);
    });
    draw();
  }
})();
