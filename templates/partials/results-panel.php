<?php

declare(strict_types=1);

use App\Bench\Mode;
use App\Support\Format;
use App\Web\Presenter;
use App\Web\View;

/** @var string $key */
/** @var string $panelTitle */
/** @var string|null $about */
/** @var array<string, mixed> $file */
/** @var array<string, array<string, mixed>> $results */
/** @var bool $downloadable */
/** @var bool $active */
?>
<div class="panel" data-panel="<?= View::e($key) ?>" <?= $active ? '' : 'hidden' ?>>
  <div class="panel-head">
    <h3><?= View::e($panelTitle) ?></h3>
    <?php if ($downloadable): ?>
      <a class="small" href="/fixtures/<?= View::e($key) ?>.xlsx">Скачать этот файл ↓</a>
    <?php endif; ?>
  </div>

  <?php if (($about ?? null) !== null): ?>
    <p class="muted small" style="margin:0"><?= View::e($about) ?></p>
  <?php endif; ?>

  <div class="file-facts">
    <span><b><?= Format::bytes((int) $file['size']) ?></b> на диске</span>
    <span>распакованный XML — <b><?= Format::bytes((int) $file['unpacked']) ?></b></span>
    <?php if (($file['rows'] ?? null) !== null): ?>
      <span><b><?= Format::number((int) $file['rows']) ?></b> ×
        <b><?= Format::number((int) ($file['cols'] ?? 0)) ?></b> (по dimension)</span>
    <?php endif; ?>
    <span>листов — <b><?= (int) $file['sheets'] ?></b></span>
    <span>shared strings — <b><?= $file['shared_strings'] ? Format::bytes((int) $file['shared_strings_size']) : 'нет' ?></b></span>
  </div>

  <?php foreach (Mode::all() as $mode): ?>
    <?php $modeResults = $results[$mode->value] ?? []; ?>
    <?php if ($modeResults === []) { continue; } ?>
    <div class="mode-block" data-mode="<?= $mode->value ?>">
      <h4><?= View::e($mode->title()) ?></h4>
      <p class="mode-desc"><?= View::e($mode->description()) ?></p>

      <div data-metric-panel="time_ms">
        <?= View::partial('partials/bars', [
            'series' => Presenter::series($modeResults, Presenter::METRIC_TIME),
            'metric' => Presenter::METRIC_TIME,
        ]) ?>
      </div>
      <div data-metric-panel="peak_bytes" hidden>
        <?= View::partial('partials/bars', [
            'series' => Presenter::series($modeResults, Presenter::METRIC_MEMORY),
            'metric' => Presenter::METRIC_MEMORY,
        ]) ?>
      </div>
    </div>
  <?php endforeach; ?>

  <details style="margin-top:22px">
    <summary class="small muted" style="cursor:pointer">Все цифры таблицей</summary>
    <div class="table-scroll">
      <table class="data">
        <thead>
          <tr>
            <th>Библиотека</th>
            <th>Сценарий</th>
            <th class="num">Время (медиана)</th>
            <th class="num">Мин</th>
            <th class="num">Макс</th>
            <th class="num">Пик памяти</th>
            <th class="num">Прочитано строк</th>
            <th class="num">Непустых ячеек</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (Mode::all() as $mode): ?>
          <?php foreach (Presenter::series($results[$mode->value] ?? [], Presenter::METRIC_TIME) as $row): ?>
            <?php $raw = $results[$mode->value][$row['id']] ?? []; ?>
            <tr class="<?= $row['subject'] ? 'is-subject' : '' ?>">
              <td><?= View::e($row['label']) ?></td>
              <td class="muted"><?= View::e($mode->title()) ?></td>
              <?php if ($row['value'] === null): ?>
                <td colspan="6" class="muted"><?= View::e($row['formatted']) ?><?= ($row['error'] ?? '') !== '' ? ' — ' . View::e($row['error']) : '' ?></td>
              <?php else: ?>
                <td class="num"><?= Format::ms((float) $raw['time_ms']) ?></td>
                <td class="num muted"><?= Format::ms((float) $raw['time_min']) ?></td>
                <td class="num muted"><?= Format::ms((float) $raw['time_max']) ?></td>
                <td class="num"><?= Format::bytes((int) $raw['peak_bytes']) ?></td>
                <td class="num muted"><?= Format::number((int) $raw['rows']) ?></td>
                <td class="num muted"><?= Format::number((int) $raw['cells']) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
</div>
