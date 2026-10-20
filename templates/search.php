<?php /** @var string $q @var array $terms @var array $stories @var int $total @var array $images @var int $imageTotal @var array $books @var array $tags @var int $page @var int $pages @var string $base @var array $cur */
$nothing = $q !== '' && !$stories && !$images && !$books && !$tags; ?>
<p class="eyebrow">Find something</p>
<h1>Search</h1>
<form class="filters" method="get" action="/search" role="search">
  <div class="filter-row">
    <div class="grow"><label for="sq" class="sr-only">Search the whole site</label><input id="sq" name="q" type="search" value="<?= e($q) ?>" placeholder="Search stories, images, books and interests…" autofocus="autofocus"></div>
    <button class="btn primary" type="submit">Search</button>
  </div>
</form>
<?php if ($q === ''): ?>
  <p class="lead">Type a word or a few words. Every word must appear in the story.</p>
<?php elseif (!$terms): ?>
  <p role="status">Please use at least two letters.</p>
<?php else: ?>
  <p class="meta" role="status"><?= number_format($total) ?> stor<?= $total === 1 ? 'y' : 'ies' ?>, <?= number_format($imageTotal) ?> image<?= $imageTotal === 1 ? '' : 's' ?> for “<?= e($q) ?>”</p>
  <?php if ($nothing): ?><p>Nothing matched. Try fewer or shorter words, or check the spelling.</p><?php endif; ?>
  <?php if ($tags): ?>
  <section aria-labelledby="s-tags"><h2 id="s-tags" class="small">Interests</h2>
    <ul class="chips"><?php foreach ($tags as $t): ?><li><a class="chip" href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a></li><?php endforeach; ?></ul>
  </section>
  <?php endif; ?>
  <?php if ($books): ?>
  <section aria-labelledby="s-books"><h2 id="s-books" class="small">Books</h2>
    <ul class="boxes"><?php foreach ($books as $b): ?><li><a href="/books#<?= e($b['slug']) ?>"><?= e($b['title']) ?></a></li><?php endforeach; ?></ul>
  </section>
  <?php endif; ?>
  <?php if ($stories): ?>
  <section aria-labelledby="s-stories"><h2 id="s-stories" class="small">Stories</h2>
    <ol class="results"><?php foreach ($stories as $s): ?>
      <li>
        <h3><a href="<?= e(story_url($s)) ?>"><?= search_mark((string) $s['title'], $terms) ?></a></h3>
        <p class="meta"><?= e(format_date($s['published_at'])) ?><?= $s['word_count'] >= 100 ? ' · ' . reading_minutes((int) $s['word_count']) . ' min read' : '' ?><?= $s['kind'] === 'video' ? ' · Video' : '' ?></p>
        <p><?= search_snippet((string) $s['search_text'], $terms) ?></p>
      </li>
    <?php endforeach; ?></ol>
    <?php include __DIR__ . '/partials/pager.php'; ?>
  </section>
  <?php endif; ?>
  <?php if ($images): ?>
  <section class="band" aria-labelledby="s-images"><h2 id="s-images" class="small">Images</h2>
    <ul class="gallery"><?php foreach ($images as $g) { include __DIR__ . '/partials/gallery_item.php'; } ?></ul>
    <?php if ($imageTotal > count($images)): ?><p><a class="btn" href="/gallery?q=<?= e(rawurlencode($q)) ?>">See all <?= number_format($imageTotal) ?> images &rarr;</a></p><?php endif; ?>
  </section>
  <?php endif; ?>
<?php endif; ?>
