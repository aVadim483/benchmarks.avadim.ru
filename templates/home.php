<?php

declare(strict_types=1);

use App\Support\Format;
use App\Web\Highlights;
use App\Web\View;

/** @var array<string, mixed>|null $baseline */
/** @var list<array<string, mixed>> $libraries */
/** @var array{max_bytes:int, ext:list<string>, repeats:int} $limits */
/** @var bool $canRun */

$highlights = $baseline !== null ? Highlights::build($baseline) : ['headline' => [], 'takeaways' => [], 'reference' => null];
?>

<section class="hero">
  <div class="wrap">
    <div>
      <h1>Насколько быстро PHP читает Excel</h1>
      <p class="lede">
        Открытое сравнение <b>avadim/fast-excel-reader</b> с основными библиотеками чтения XLSX:
        сколько времени и сколько памяти уходит на один и тот же файл. Замеры выполняются на реальных
        файлах в изолированных процессах — и вы можете прогнать тот же тест на собственном файле,
        без регистрации.
      </p>
      <div class="form-row">
        <a class="btn" href="#upload">Проверить свой файл</a>
        <a class="btn ghost" href="/methodology">Как это измеряется</a>
      </div>

      <?php if ($highlights['headline'] !== []): ?>
        <div class="headline-metrics">
          <?php foreach ($highlights['headline'] as $metric): ?>
            <div class="metric-card">
              <span class="value"><?= View::e($metric['value']) ?></span>
              <span class="label"><?= View::e($metric['label']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <h2 style="font-size:1.1rem">Что сравнивается</h2>
      <div class="lib-grid" style="grid-template-columns:1fr">
        <?php foreach ($libraries as $lib): ?>
          <div class="lib-card <?= $lib['subject'] ? 'is-subject' : '' ?>">
            <h3>
              <a href="<?= View::e($lib['homepage']) ?>" rel="noopener" target="_blank"><?= View::e($lib['label']) ?></a>
              <span class="badge"><?= View::e($lib['version']) ?></span>
              <?php if ($lib['streaming']): ?><span class="badge stream">потоковая</span><?php endif; ?>
              <?php if ($lib['subject']): ?><span class="badge accent">предмет теста</span><?php endif; ?>
            </h3>
            <div class="pkg"><?= View::e($lib['package']) ?></div>
            <p class="note"><?= View::e($lib['note']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php if ($baseline === null): ?>
  <section id="results">
    <div class="wrap">
      <div class="notice warn">
        Эталонные замеры ещё не выполнены. Запустите
        <code>php bin/generate-fixtures.php</code>, затем <code>php bin/benchmark.php</code>.
      </div>
    </div>
  </section>
<?php else: ?>

  <section id="results">
    <div class="wrap">
      <h2>Результаты на подготовленных файлах</h2>
      <p class="section-intro">
        Семь наборов разной формы: от небольшой выгрузки до таблицы на миллион ячеек, отдельно —
        широкая таблица, отдельно — текст в словаре sharedStrings и тот же текст инлайном.
        Каждый файл можно скачать и перепроверить результат у себя.
        Прогон от <?= View::e(date('d.m.Y', strtotime((string) $baseline['created_at']))) ?>,
        по <?= (int) $baseline['settings']['repeats'] ?> <?= Format::plural((int) $baseline['settings']['repeats'], 'повтору', 'повтора', 'повторов') ?> на замер, показана медиана.
      </p>

      <?php if ($highlights['takeaways'] !== []): ?>
        <ul class="takeaways">
          <?php foreach ($highlights['takeaways'] as $note): ?>
            <li><?= View::e($note) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="controls" style="margin-top:28px">
        <div class="control-group">
          <span>Метрика</span>
          <div class="segmented" data-metric-switch>
            <button type="button" data-metric="time_ms" aria-pressed="true">Время</button>
            <button type="button" data-metric="peak_bytes" aria-pressed="false">Память</button>
          </div>
        </div>
      </div>

      <?= View::partial('partials/summary-table', [
          'datasets'  => $baseline['datasets'],
          'libraries' => $baseline['libraries'] ?? $libraries,
      ]) ?>

      <div class="notice" style="margin-top:20px">
        Сравнивается только чтение данных. PhpSpreadsheet проигрывает по скорости и памяти,
        потому что строит полную объектную модель документа: стили, формулы, изображения, диаграммы.
        Там, где эта модель нужна, у него нет замены — а там, где нужно просто прочитать строки,
        за неё приходится платить.
        <a href="/methodology">Подробно о методике →</a>
      </div>

      <h3 style="margin-top:34px">Разбор по наборам</h3>
      <p class="small muted" style="max-width:72ch">
        Внутри каждого набора — все три сценария и подробная таблица с минимумом, максимумом
        и счётчиками прочитанных строк.
      </p>

      <div class="dataset-tabs" data-tabs="baseline">
        <?php foreach ($baseline['datasets'] as $i => $dataset): ?>
          <button type="button" data-tab="<?= View::e($dataset['key']) ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>">
            <?= View::e($dataset['key']) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <div data-tabgroup="baseline">
        <?php foreach ($baseline['datasets'] as $i => $dataset): ?>
          <?= View::partial('partials/results-panel', [
              'key'          => (string) $dataset['key'],
              'panelTitle'   => (string) $dataset['title'],
              'about'        => (string) $dataset['about'],
              'file'         => $dataset['file'],
              'results'      => $dataset['results'],
              'downloadable' => true,
              'active'       => $i === 0,
          ]) ?>
        <?php endforeach; ?>
      </div>

      <details style="margin-top:26px">
        <summary class="small muted" style="cursor:pointer">Окружение стенда</summary>
        <dl class="env-list" style="margin-top:12px">
          <?php foreach ($baseline['environment'] as $key => $value): ?>
            <div><dt><?= View::e($key) ?></dt><dd><?= View::e($value) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </details>
    </div>
  </section>
<?php endif; ?>

<section id="upload">
  <div class="wrap">
    <h2>Проверьте на своём файле</h2>
    <p class="section-intro">
      Загрузите книгу XLSX — те же четыре библиотеки прочитают её на этом сервере, и вы получите
      ссылку на страницу с результатами. Регистрация не нужна. Файл удаляется сразу после прогона,
      его содержимое нигде не показывается — в отчёт попадают только размеры, количество строк и тайминги.
    </p>

    <?php if (!$canRun): ?>
      <div class="notice bad">
        На этом сервере отключена функция <code>proc_open</code>, поэтому запуск изолированных замеров
        недоступен. Локально проект работает командой <code>php bin/benchmark.php</code>.
      </div>
    <?php else: ?>
      <form class="upload-card" method="post" action="/run" enctype="multipart/form-data" data-upload-form>
        <label class="dropzone" data-dropzone>
          <input type="file" name="file" accept=".xlsx,.xlsm" required data-file-input>
          <strong>Выберите файл или перетащите его сюда</strong>
          <div class="hint">
            <?= View::e(implode(', ', array_map(static fn (string $e): string => '.' . $e, $limits['ext']))) ?>,
            до <?= Format::bytes($limits['max_bytes']) ?>
          </div>
          <div class="picked" data-picked hidden></div>
        </label>

        <div class="form-row">
          <button class="btn" type="submit" data-submit>Запустить сравнение</button>
          <span class="small muted">
            Прогон занимает от нескольких секунд до нескольких минут — по <?= (int) $limits['repeats'] ?>
            <?= Format::plural($limits['repeats'], 'повтору', 'повтора', 'повторов') ?> на каждый из 12 замеров.
          </span>
        </div>
      </form>

      <p class="small muted" style="margin-top:14px; max-width:70ch">
        Не загружайте файлы с персональными данными и коммерческой тайной: сервер обрабатывает их
        обычным PHP-процессом, а ссылка на результаты доступна любому, кто её знает.
      </p>
    <?php endif; ?>
  </div>
</section>
