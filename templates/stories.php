<?php /** @var array $stories @var ?array $tag @var string $q @var string $sort @var int $page @var int $pages @var int $total @var string $base */
$qs = fn(array $o) => '?' . http_build_query(array_filter(['q' => $q, 'sort' => $sort === 'old' ? 'old' : null] + $o, fn($v) => $v !== null && $v !== '' && $v !== 1)); ?>
<h1><?= e($tag ? $tag['name'] : 'Stories') ?></h1>
<?php if ($tag && $tag['description'] !== ''): ?><p class="lead"><?= e($tag['description']) ?></p><?php endif; ?>
<?php if ($total > 12 || $q !== ''): ?>
<form class="filters" method="get" action="<?= e($base) ?>" role="search">
  <label for="q">Search stories</label>
  <input id="q" name="q" type="search" value="<?= e($q) ?>">
  <label for="sort">Order</label>
  <select id="sort" name="sort"><option value="new"<?= $sort === 'new' ? ' selected' : '' ?>>Newest first</option><option value="old"<?= $sort === 'old' ? ' selected' : '' ?>>Oldest first</option></select>
  <button type="submit">Apply</button>
</form>
<?php endif; ?>
<p role="status"><?= $total ?> <?= $total === 1 ? 'story' : 'stories' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?></p>
<div class="grid"><?php foreach ($stories as $s) { include __DIR__ . '/partials/story_card.php'; } ?></div>
<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Pages"><ul>
  <?php if ($page > 1): ?><li><a rel="prev" href="<?= e($base . $qs(['page' => $page - 1])) ?>">&larr; Newer</a></li><?php endif; ?>
  <li><span aria-current="page">Page <?= $page ?> of <?= $pages ?></span></li>
  <?php if ($page < $pages): ?><li><a rel="next" href="<?= e($base . $qs(['page' => $page + 1])) ?>">Older &rarr;</a></li><?php endif; ?>
</ul></nav>
<?php endif; ?>
