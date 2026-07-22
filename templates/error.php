<?php

declare(strict_types=1);

use App\Web\View;

/** @var string $title */
/** @var string $message */
/** @var string|null $back */
?>
<section>
  <div class="wrap">
    <h1><?= View::e($title) ?></h1>
    <p class="lede"><?= View::e($message) ?></p>
    <p><a class="btn" href="<?= View::e($back ?? '/') ?>">На главную</a></p>
  </div>
</section>
