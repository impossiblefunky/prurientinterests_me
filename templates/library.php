<?php /** @var array $rows @var string $q @var string $format @var int $year @var array $years @var string $sort @var int $page @var int $pages @var int $total @var array $cur @var string $base @var array $topTags */ ?>
<p class="eyebrow">The writing · 2006 — present</p>
<h1>The story library</h1>
<?php include __DIR__ . '/partials/interest_links.php'; ?>
<p class="lead">Find a beginning. Return to a favorite. Follow a story through the years.</p>
<form class="filters" method="get" action="/stories" role="search">
  <div class="filter-row">
    <div class="grow"><label for="q" class="sr-only">Search stories</label><input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Search stories…"></div>
    <div><label for="format" class="sr-only">Format</label><select id="format" name="format"><option value="">All formats</option><option value="standalone"<?= $format === 'standalone' ? ' selected' : '' ?>>Standalone</option><option value="series"<?= $format === 'series' ? ' selected' : '' ?>>Series</option></select></div>
    <div><label for="sort" class="sr-only">Order</label><select id="sort" name="sort"><option value="new">Newest first</option><option value="old"<?= $sort === 'old' ? ' selected' : '' ?>>Oldest first</option></select></div>
    <?php if ($year): ?><input type="hidden" name="year" value="<?= (int) $year ?>"><?php endif; ?>
    <button class="btn primary" type="submit">Apply</button>
  </div>
</form>
<nav aria-label="Filter by year"><ul class="chips small">
  <li><a class="chip" href="/stories<?= e(qs($cur, ['year' => null, 'page' => null])) ?>"<?= !$year ? ' aria-current="true"' : '' ?>>All years</a></li>
  <?php foreach ($years as $y): ?><li><a class="chip" href="/stories<?= e(qs($cur, ['year' => $y, 'page' => null])) ?>"<?= (int) $y === $year ? ' aria-current="true"' : '' ?>><?= (int) $y ?></a></li>
  <?php endforeach; ?>
</ul></nav>
<p class="meta" role="status"><?= number_format($total) ?> <?= $total === 1 ? 'entry' : 'entries' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?></p>
<?php if ($rows): ?><div class="grid"><?php foreach ($rows as $s) { include __DIR__ . '/partials/story_card.php'; } ?></div>
<?php else: ?><p>Nothing matches. Try fewer filters.</p><?php endif; ?>
<?php include __DIR__ . '/partials/pager.php'; ?>
