<?php /** @var array $items @var array $albums @var string $only @var string $q @var int $total @var array $topTags @var int $page @var int $pages @var string $base @var array $cur */ ?>
<p class="eyebrow">A personal visual collection</p>
<h1>The gallery</h1>
<?php include __DIR__ . '/partials/interest_links.php'; ?>
<p class="lead">Collected images, newest first. Each caption is the title of the blog post it first appeared in.</p>
<form class="filters" method="get" action="/gallery" role="search">
  <div class="filter-row">
    <div class="grow"><label for="gq" class="sr-only">Search the gallery</label><input id="gq" name="q" type="search" value="<?= e($q) ?>" placeholder="Search captions…"></div>
    <?php if ($only !== ''): ?><input type="hidden" name="album" value="<?= e($only) ?>"><?php endif; ?>
    <button class="btn primary" type="submit">Search images</button>
  </div>
</form>
<?php if ($albums): ?>
<nav aria-label="Albums"><ul class="chips small">
  <li><a class="chip" href="/gallery"<?= $only === '' ? ' aria-current="true"' : '' ?>>All images</a></li>
  <?php foreach ($albums as $a): ?><li><a class="chip" href="/gallery?album=<?= e($a['slug']) ?>"<?= $only === $a['slug'] ? ' aria-current="true"' : '' ?>><?= e($a['title']) ?></a></li><?php endforeach; ?>
</ul></nav>
<?php endif; ?>
<p class="meta" role="status"><?= number_format($total) ?> image<?= $total === 1 ? '' : 's' ?><?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?></p>
<?php if ($items): ?>
<ul class="gallery"><?php foreach ($items as $g) { include __DIR__ . '/partials/gallery_item.php'; } ?></ul>
<?php include __DIR__ . '/partials/pager.php'; ?>
<?php else: ?><p>No images found.</p><?php endif; ?>
