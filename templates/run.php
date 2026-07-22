<?php

declare(strict_types=1);

use App\Support\Format;
use App\Web\View;

/** @var array<string, mixed> $job */
/** @var list<array<string, mixed>> $libraries */

$status = (string) ($job['status'] ?? 'queued');
$done = $status === 'done';
$progress = $job['progress'] ?? ['done' => 0, 'total' => 12, 'current' => null];
$percent = ((int) $progress['total']) > 0
    ? round((int) $progress['done'] / (int) $progress['total'] * 100)
    : 0;
?>

<section>
  <div class="wrap">
    <p class="small muted"><a href="/">← К общим результатам</a></p>
    <h1 style="font-size:1.9rem">Ваш файл: <?= View::e($job['file']['name'] ?? 'без имени') ?></h1>

    <?php if (!$done): ?>
      <div class="progress-card" data-poll="<?= View::e($job['id']) ?>">
        <p style="margin:0">
          <span class="spinner"></span>
          <b data-status-text>
            <?= $status === 'queued' ? 'Прогон поставлен в очередь…' : 'Идёт прогон…' ?>
          </b>
        </p>
        <div class="progress-track"><div class="progress-fill" style="width: <?= $percent ?>%" data-progress-fill></div></div>
        <p class="small muted" style="margin:0" data-progress-text>
          Завершено <?= (int) $progress['done'] ?> из <?= (int) $progress['total'] ?> замеров.
          Страница обновится сама.
        </p>
        <p class="small muted" style="margin-top:14px">
          Ссылку можно сохранить и вернуться позже — результаты хранятся ограниченное время.
        </p>
      </div>
    <?php else: ?>
      <p class="lede">
        Прогон занял <?= Format::ms(((float) ($job['duration_s'] ?? 0)) * 1000) ?>,
        по <?= (int) ($job['settings']['repeats'] ?? 3) ?>
        <?= Format::plural((int) ($job['settings']['repeats'] ?? 3), 'повтору', 'повтора', 'повторов') ?>
        на замер (медленные замеры повторяются меньше раз). Показана медиана.
      </p>

      <div class="controls">
        <div class="control-group">
          <span>Метрика</span>
          <div class="segmented" data-metric-switch>
            <button type="button" data-metric="time_ms" aria-pressed="true">Время</button>
            <button type="button" data-metric="memory_bytes" aria-pressed="false">Память</button>
          </div>
        </div>
      </div>

      <?= View::partial('partials/results-panel', [
          'key'          => 'upload',
          'panelTitle'   => (string) ($job['file']['name'] ?? 'Ваш файл'),
          'about'        => null,
          'file'         => $job['file'],
          'results'      => $job['results'] ?? [],
          'downloadable' => false,
          'active'       => true,
      ]) ?>

      <div class="notice" style="margin-top:24px">
        Исходный файл уже удалён с сервера. Сохранился только этот отчёт — им можно поделиться ссылкой:
        <code><?= View::e(($_SERVER['HTTP_HOST'] ?? 'localhost') . '/r/' . $job['id']) ?></code>
        <br><a class="small" href="/api/run/<?= View::e($job['id']) ?>">Те же данные в JSON</a>
      </div>

      <?php if (($job['environment'] ?? []) !== []): ?>
        <details style="margin-top:22px">
          <summary class="small muted" style="cursor:pointer">Окружение</summary>
          <dl class="env-list" style="margin-top:12px">
            <?php foreach ($job['environment'] as $key => $value): ?>
              <div><dt><?= View::e($key) ?></dt><dd><?= View::e($value) ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </details>
      <?php endif; ?>

      <p style="margin-top:26px"><a class="btn" href="/#upload">Проверить другой файл</a></p>
    <?php endif; ?>
  </div>
</section>
