<?php /** @var array $albums @var array $loose @var string $only @var array $topTags @var int $page @var int $pages @var string $base @var array $cur */
$shown = $only === '' ? $albums : array_values(array_filter($albums, fn($a) => $a['slug'] === $only)); ?>
<p class="eyebrow">A personal visual collection</p>
<h1>The gallery</h1>
<?php include __DIR__ . '/partials/interest_links.php'; ?>
<p class="lead">Collected images, arranged by mood and fascination.</p>
<?php if ($albums): ?>
<nav aria-label="Albums"><ul class="chips">
  <li><a class="chip" href="/gallery"<?= $only === '' ? ' aria-current="true"' : '' ?>>All images</a></li>
  <?php foreach ($albums as $a): ?><li><a class="chip" href="/gallery?album=<?= e($a['slug']) ?>"<?= $only === $a['slug'] ? ' aria-current="true"' : '' ?>><?= e($a['title']) ?></a></li><?php endforeach; ?>
</ul></nav>
<?php foreach ($shown as $a): if (!$a['items']) { continue; } ?>
<section class="band" aria-labelledby="a-<?= e($a['slug']) ?>">
  <h2 id="a-<?= e($a['slug']) ?>"><?= e($a['title']) ?></h2>
  <?php if ($a['intro'] !== ''): ?><p class="lead"><?= e($a['intro']) ?></p><?php endif; ?>
  <ul class="gallery"><?php foreach ($a['items'] as $g) { include __DIR__ . '/partials/gallery_item.php'; } ?></ul>
  <?php if ($only === '' && $a['total'] > count($a['items'])): ?><p><a class="btn" href="/gallery?album=<?= e($a['slug']) ?>">See all <?= number_format($a['total']) ?> images in <?= e($a['title']) ?> &rarr;</a></p><?php endif; ?>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?php if ($only !== ''): include __DIR__ . '/partials/pager.php'; endif; ?>
<?php if ($only === '' && $loose): ?>
<section class="band" aria-labelledby="loose-h"><h2 id="loose-h">More images</h2>
  <ul class="gallery"><?php foreach ($loose as $g) { include __DIR__ . '/partials/gallery_item.php'; } ?></ul>
</section>
<?php endif; ?>
<?php if (!$albums && !$loose): ?><p>Nothing here yet.</p><?php endif; ?>
