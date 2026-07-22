<?php

declare(strict_types=1);

use App\Bench\Mode;
use App\Support\Format;
use App\Web\Presenter;
use App\Web\View;

/** @var list<array<string, mixed>> $datasets */
/** @var list<array<string, mixed>> $libraries */

$metrics = [
    Presenter::METRIC_TIME   => 'Время чтения всех строк, медиана',
    Presenter::METRIC_MEMORY => 'Прирост памяти процесса (RSS) при чтении всех строк',
];
?>
<?php foreach ($metrics as $metric => $caption): ?>
  <div data-metric-panel="<?= $metric ?>" <?= $metric === Presenter::METRIC_TIME ? '' : 'hidden' ?>>
    <div class="table-scroll">
      <table class="data">
        <caption class="small muted" style="text-align:left; padding:0 0 8px"><?= View::e($caption) ?></caption>
        <thead>
          <tr>
            <th>Набор</th>
            <?php foreach ($libraries as $lib): ?>
              <th class="num"><?= View::e($lib['label']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($datasets as $dataset): ?>
          <?php
          $modeResults = $dataset['results'][Mode::ReadAll->value] ?? [];
          $byId = [];
          foreach (Presenter::series($modeResults, $metric) as $row) {
              $byId[$row['id']] = $row;
          }
          ?>
          <tr>
            <td>
              <span class="mono small"><?= View::e($dataset['key']) ?></span><br>
              <span class="small muted">
                <?= Format::number((int) ($dataset['file']['rows'] ?? 0)) ?> ×
                <?= Format::number((int) ($dataset['file']['cols'] ?? 0)) ?>,
                <?= Format::bytes((int) ($dataset['file']['size'] ?? 0)) ?>
              </span>
            </td>
            <?php foreach ($libraries as $lib): ?>
              <?php $cell = $byId[$lib['id']] ?? null; ?>
              <td class="num" style="<?= ($cell['best'] ?? false) ? 'font-weight:650' : '' ?>">
                <?php if ($cell === null): ?>
                  <span class="muted">—</span>
                <?php elseif ($cell['value'] === null): ?>
                  <span class="muted" title="<?= View::e($cell['error'] ?? '') ?>"><?= View::e($cell['formatted']) ?></span>
                <?php else: ?>
                  <?= View::e($cell['formatted']) ?>
                  <?php if (($cell['ratio'] ?? 1) > 1.005): ?>
                    <br><span class="small muted"><?= View::e($cell['ratio_label']) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>
