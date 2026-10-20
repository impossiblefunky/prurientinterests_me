<?php /** @var array $picks @var array $tags @var array $albums @var ?array $book @var array $yearCounts @var array $stats */ ?>
<section class="hero" aria-labelledby="hero-h">
  <div class="hero-text">
    <p class="eyebrow"><?= e(setting('home_eyebrow')) ?></p>
    <h1 id="hero-h"><?= nl2br(e(str_replace('. ', ".\n", setting('home_headline', setting('site_title'))))) ?></h1>
    <p class="lead"><?= e(setting('home_intro')) ?></p>
    <p class="actions"><a class="btn primary" href="/stories">Enter the story library &rarr;</a><?php if ($book): ?> <a class="btn" href="/books">Explore the books &rarr;</a><?php endif; ?></p>
  </div>
  <?php if ($book): ?>
  <div class="hero-book">
    <?= View_cover($book) ?>
    <p class="meta">Featured book</p>
    <p><a class="btn" href="/books/<?= e($book['slug']) ?>">About this book &rarr;</a></p>
  </div>
  <?php endif; ?>
</section>
<?php if (!empty($stats['written'])): ?>
<section class="stats" aria-label="The archive in numbers">
  <ul>
    <li><a href="/stories"><span class="num"><?= number_format($stats['written']) ?></span><span class="lbl">stories and posts</span></a></li>
    <?php if ($stats['pictures']): ?><li><a href="/gallery"><span class="num"><?= number_format($stats['pictures']) ?></span><span class="lbl">pictures</span></a></li><?php endif; ?>
    <?php if ($stats['books']): ?><li><a href="/books"><span class="num"><?= number_format($stats['books']) ?></span><span class="lbl">books</span></a></li><?php endif; ?>
    <?php if ($stats['since']): ?><li><span class="num"><?= max(1, (int) gmdate('Y') - (int) $stats['since']) ?></span><span class="lbl">years of writing, since <?= (int) $stats['since'] ?></span></li><?php endif; ?>
  </ul>
</section>
<?php endif; ?>
<?php if ($tags): ?>
<section class="band" aria-labelledby="draw-h">
  <p class="eyebrow">Explore by interest</p>
  <h2 id="draw-h">What draws you in?</h2>
  <p class="lead">Follow a tag through the writing, the images, and the years.</p>
  <ul class="chips"><?php foreach ($tags as $t): ?><li><a class="chip" href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a></li><?php endforeach; ?></ul>
  <p><a class="btn" href="/tags">Explore every tag &rarr;</a></p>
</section>
<?php endif; ?>
<?php if ($picks): ?>
<section class="band" aria-labelledby="start-h">
  <div class="band-head"><h2 id="start-h">Start somewhere memorable</h2><a class="btn" href="/stories">Browse all stories &rarr;</a></div>
  <div class="grid"><?php foreach ($picks as $s) { include __DIR__ . '/partials/story_card.php'; } ?></div>
</section>
<?php endif; ?>
<section class="panel years" aria-labelledby="years-h">
  <div>
    <p class="eyebrow">2006 — present</p><h2 id="years-h">The years are part of the story.</h2>
    <p class="lead">Browse by year, explore a series, or let chance choose.</p>
    <?php if ($yearCounts): ?><ul class="chips year-chips" aria-label="Browse the archive by year"><?php foreach ($yearCounts as $y): ?><li><a class="chip" href="/stories?year=<?= (int) $y['y'] ?>"><?= (int) $y['y'] ?> <span class="meta">(<?= (int) $y['n'] ?>)</span></a></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
  <p class="actions"><a class="btn primary" href="/random">Surprise me with a story &rarr;</a> <a class="btn" href="/stories">Open the archive &rarr;</a></p>
</section>
<?php if ($albums): ?>
<section class="band" aria-labelledby="fasc-h">
  <div class="band-head"><h2 id="fasc-h">Collected fascinations</h2><a class="btn" href="/gallery">Visit the gallery &rarr;</a></div>
  <ul class="boxes"><?php foreach ($albums as $a): ?><li><a href="/gallery?album=<?= e($a['slug']) ?>"><?= e($a['title']) ?></a></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php if ($book): ?>
<section class="panel" aria-labelledby="shelf-h">
  <div><p class="eyebrow">On the bookshelf</p><h2 id="shelf-h"><?= e($book['title']) ?></h2><p class="lead">Discover the books behind the collection.</p></div>
  <p><a class="btn" href="/books">View books &rarr;</a></p>
</section>
<?php endif; ?>
