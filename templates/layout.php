<?php

declare(strict_types=1);

use App\Web\View;

/** @var string $title */
/** @var string $content */
?><!doctype html>
<html lang="ru" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($title) ?></title>
<meta name="description" content="Сравнение скорости и потребления памяти PHP-библиотек для чтения XLSX: FastExcelReader, OpenSpout, PhpSpreadsheet, SimpleXLSX. Можно загрузить свой файл.">
<meta name="color-scheme" content="light dark">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= View::e($title) ?>">
<meta property="og:description" content="Скорость и потребление памяти при чтении XLSX в PHP: FastExcelReader, OpenSpout, PhpSpreadsheet, SimpleXLSX. Замеры в изолированных процессах, можно проверить на своём файле.">
<meta name="twitter:card" content="summary">
<link rel="stylesheet" href="/assets/app.css?v=3">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>&#9889;</text></svg>">
<script>document.documentElement.className = 'js';</script>
</head>
<body>
<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="/">
      <span class="brand-mark">XLSX</span>
      <span class="brand-text">Бенчмарк чтения Excel в PHP</span>
    </a>
    <nav class="site-nav">
      <a href="/#results">Результаты</a>
      <a href="/#upload">Свой файл</a>
      <a href="/methodology">Методика</a>
      <a href="/api/baseline">API</a>
    </nav>
  </div>
</header>

<main><?= $content ?></main>

<footer class="site-footer">
  <div class="wrap">
    <p>
      Открытый бенчмарк чтения XLSX в PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?>.
      Все замеры выполняются в изолированных процессах, исходники сценариев — в каталоге <code>src/Bench/Adapter</code>.
    </p>
    <p class="muted">
      Загруженные файлы удаляются сразу после прогона, результаты хранятся ограниченное время.
      Цифры зависят от железа: сравнивать имеет смысл библиотеки между собой, а не абсолютные значения с другими стендами.
    </p>
  </div>
</footer>
<script src="/assets/app.js?v=3" defer></script>
</body>
</html>
