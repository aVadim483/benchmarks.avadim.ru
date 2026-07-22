(function () {
  'use strict';

  // --- Переключение набора данных ---
  document.querySelectorAll('[data-tabs]').forEach(function (tabs) {
    var group = document.querySelector('[data-tabgroup="' + tabs.dataset.tabs + '"]');
    if (!group) return;

    tabs.addEventListener('click', function (event) {
      var button = event.target.closest('button[data-tab]');
      if (!button) return;

      tabs.querySelectorAll('button[data-tab]').forEach(function (b) {
        b.setAttribute('aria-pressed', String(b === button));
      });
      group.querySelectorAll('[data-panel]').forEach(function (panel) {
        panel.hidden = panel.dataset.panel !== button.dataset.tab;
      });
    });
  });

  // --- Переключение метрики (время / память) ---
  function applyMetric(metric) {
    document.querySelectorAll('[data-metric-panel]').forEach(function (panel) {
      panel.hidden = panel.dataset.metricPanel !== metric;
    });
    document.querySelectorAll('[data-metric-switch] button').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.dataset.metric === metric));
    });
  }

  document.querySelectorAll('[data-metric-switch]').forEach(function (group) {
    group.addEventListener('click', function (event) {
      var button = event.target.closest('button[data-metric]');
      if (button) applyMetric(button.dataset.metric);
    });
  });

  // --- Форма загрузки ---
  var input = document.querySelector('[data-file-input]');
  var picked = document.querySelector('[data-picked]');
  var dropzone = document.querySelector('[data-dropzone]');
  var form = document.querySelector('[data-upload-form]');

  function showPicked() {
    if (!input || !picked) return;
    if (input.files && input.files.length) {
      var file = input.files[0];
      picked.textContent = file.name + ' — ' + (file.size / 1048576).toFixed(2).replace('.', ',') + ' МБ';
      picked.hidden = false;
    } else {
      picked.hidden = true;
    }
  }

  if (input) input.addEventListener('change', showPicked);

  if (dropzone) {
    ['dragenter', 'dragover'].forEach(function (type) {
      dropzone.addEventListener(type, function (e) {
        e.preventDefault();
        dropzone.classList.add('is-over');
      });
    });
    ['dragleave', 'drop'].forEach(function (type) {
      dropzone.addEventListener(type, function (e) {
        e.preventDefault();
        dropzone.classList.remove('is-over');
      });
    });
    dropzone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files.length && input) {
        input.files = e.dataTransfer.files;
        showPicked();
      }
    });
  }

  if (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('[data-submit]');
      if (button) {
        button.disabled = true;
        button.textContent = 'Загружаем файл…';
      }
    });
  }

  // --- Опрос состояния прогона ---
  var poller = document.querySelector('[data-poll]');
  if (poller) {
    var id = poller.dataset.poll;
    var fill = poller.querySelector('[data-progress-fill]');
    var text = poller.querySelector('[data-progress-text]');
    var statusText = poller.querySelector('[data-status-text]');
    var misses = 0;
    var polls = 0;

    // Интервал растёт: прогон большого файла идёт минутами, а каждый опрос
    // занимает воркер FPM, которых на сайте немного.
    var nextDelay = function () {
      polls += 1;
      if (polls < 10) return 1500;
      if (polls < 30) return 3000;
      return 6000;
    };

    var tick = function () {
      fetch('/api/run/' + id, { headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error('http ' + response.status);
          return response.json();
        })
        .then(function (job) {
          misses = 0;
          if (job.status === 'done') {
            window.location.reload();
            return;
          }
          var progress = job.progress || {};
          var total = progress.total || 12;
          var doneCount = progress.done || 0;
          if (fill) fill.style.width = Math.round((doneCount / total) * 100) + '%';
          if (statusText) statusText.textContent = job.status === 'queued' ? 'Прогон поставлен в очередь…' : 'Идёт прогон…';
          if (text) {
            var current = progress.current
              ? ' Сейчас: ' + progress.current.adapter + ' / ' + progress.current.mode + '.'
              : '';
            text.textContent = 'Завершено ' + doneCount + ' из ' + total + ' замеров.' + current;
          }
          setTimeout(tick, nextDelay());
        })
        .catch(function () {
          misses += 1;
          if (misses < 20) setTimeout(tick, 3000);
          else if (text) text.textContent = 'Не удалось получить состояние прогона. Обновите страницу вручную.';
        });
    };

    setTimeout(tick, 1200);
  }
})();
