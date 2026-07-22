<?php

declare(strict_types=1);

use App\Web\View;

/** @var list<array<string, mixed>> $series */
/** @var string $metric */
?>
<div class="bars">
  <?php foreach ($series as $row): ?>
    <?php
    $failed = $row['value'] === null;
    $classes = 'bar-row';
    if ($row['subject'] === true) {
        $classes .= ' is-subject';
    }
    if ($failed) {
        $classes .= ' is-failed';
    }
    ?>
    <div class="<?= $classes ?>">
      <div class="bar-label">
        <span class="name"><?= View::e($row['label']) ?></span>
        <?php if ($row['best'] === true): ?><span class="badge good">лучший</span><?php endif; ?>
      </div>
      <div class="bar-track">
        <?php if (!$failed): ?>
          <div class="bar-fill" style="--w: <?= number_format((float) $row['percent'], 2, '.', '') ?>%"></div>
        <?php endif; ?>
      </div>
      <div class="bar-value">
        <?php if ($failed): ?>
          <span class="fail <?= $row['status'] === 'skipped' ? 'skipped' : '' ?>"
                title="<?= View::e($row['error'] ?? '') ?>"><?= View::e($row['formatted']) ?></span>
        <?php else: ?>
          <?= View::e($row['formatted']) ?>
          <?php if ($row['ratio'] !== null && $row['ratio'] > 1.005): ?>
            <span class="ratio"><?= View::e($row['ratio_label']) ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
