<?php /** @var array $latest @var array $tags */ ?>
<section class="hero">
  <h1><?= e(setting('site_title')) ?></h1>
  <p class="lead"><?= e(setting('tagline')) ?></p>
</section>
<?php if ($latest): ?>
<section aria-labelledby="latest">
  <h2 id="latest">Latest stories</h2>
  <div class="grid"><?php foreach ($latest as $s) { include __DIR__ . '/partials/story_card.php'; } ?></div>
  <p><a href="/stories">All stories &rarr;</a></p>
</section>
<?php endif; ?>
<?php if ($tags): ?>
<section aria-labelledby="browse">
  <h2 id="browse">Browse by tag</h2>
  <ul class="tagcloud"><?php foreach ($tags as $t): ?><li><a href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?> <span>(<?= (int) $t['n'] ?>)</span></a></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
