<?php /** @var array $s @var array $tags @var array $related */ ?>
<article class="reading">
  <header>
    <h1><?= e($s['title']) ?></h1>
    <p class="meta"><time datetime="<?= e(gmdate('Y-m-d', strtotime($s['published_at'] . ' UTC'))) ?>"><?= e(format_date($s['published_at'])) ?></time> &middot; <?= reading_minutes((int) $s['word_count']) ?> min read &middot; <?= number_format((int) $s['word_count']) ?> words</p>
  </header>
  <div class="prose"><?= $s['body_html'] ?></div>
  <?php if ($tags): ?>
  <footer><h2 class="small">Tagged</h2>
    <ul class="tagcloud"><?php foreach ($tags as $t): ?><li><a href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a></li><?php endforeach; ?></ul>
  </footer>
  <?php endif; ?>
</article>
<?php if ($related): ?>
<section aria-labelledby="more"><h2 id="more">More to read</h2>
  <div class="grid"><?php foreach ($related as $s) { include __DIR__ . '/partials/story_card.php'; } ?></div>
</section>
<?php endif; ?>
