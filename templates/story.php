<?php /** @var array $s @var array $tags @var array $related @var ?array $prev @var ?array $next @var int $chapters @var string $author @var array $comments @var array $form */ ?>
<article class="reading">
  <div class="reading-tools" role="group" aria-label="Reading options" hidden>
    <button type="button" class="linkbtn" data-toggle="paper" aria-pressed="false">Paper reading mode</button>
    <button type="button" class="linkbtn" data-toggle="large" aria-pressed="false">Larger text</button>
  </div>
  <p><a class="btn" href="/stories">&larr; The story library</a></p>
  <header>
    <?php if ($s['series_id']): ?><p class="eyebrow"><a href="/series/<?= e($s['series_slug']) ?>"><?= e($s['series_title']) ?></a> · Chapter <?= (int) $s['chapter_order'] ?> of <?= (int) $chapters ?></p><?php endif; ?>
    <h1><?= e($s['title']) ?></h1>
    <p class="meta">By <?= e($author) ?> · <time datetime="<?= e(gmdate('Y-m-d', strtotime($s['published_at'] . ' UTC'))) ?>"><?= e(format_date($s['published_at'])) ?></time><?php if ($s['word_count'] >= 100): ?> · <?= reading_minutes((int) $s['word_count']) ?> min read<?php endif; ?></p>
  </header>
  <div class="prose<?= $s['kind'] === 'story' ? ' dropcap' : '' ?>"><?= prose($s['body_html']) ?></div>
  <?php if ($tags): ?>
  <footer class="tagfoot"><h2 class="sr-only">Interests</h2>
    <p class="interest-links"><?php foreach ($tags as $t): ?><a href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a> <?php endforeach; ?><a href="/tags">More interests &rarr;</a></p>
  </footer>
  <?php endif; ?>
  <?php if ($prev || $next): ?>
  <nav class="series-nav" aria-label="Series">
    <div><?php if ($prev): ?><p class="eyebrow">Previous chapter</p><p><a class="btn" href="<?= e(story_url($prev)) ?>">&larr; Chapter <?= (int) $prev['chapter_order'] ?></a></p><?php endif; ?></div>
    <div class="right"><?php if ($next): ?><p class="eyebrow">Continue the series</p><p class="h3"><?= e($next['title']) ?></p><p><a class="btn" href="<?= e(story_url($next)) ?>">Next chapter &rarr;</a></p><?php endif; ?></div>
  </nav>
  <?php endif; ?>
</article>
<section class="band comments" aria-labelledby="comments-h" id="comments"><h2 id="comments-h">Comments<?= $comments ? ' (' . count($comments) . ')' : '' ?></h2>
  <?php foreach ($comments as $c): ?>
  <article class="comment">
    <p class="meta"><strong><?= e($c['author'] !== '' ? $c['author'] : 'Anonymous') ?></strong><?php if ($c['commented_at']): ?> · <time datetime="<?= e(gmdate('Y-m-d', strtotime($c['commented_at'] . ' UTC'))) ?>"><?= e(format_date($c['commented_at'])) ?></time><?php endif; ?></p>
    <div class="prose"><?= prose($c['body_html']) ?></div>
  </article>
  <?php endforeach; ?>
  <?php include __DIR__ . '/partials/comment_form.php'; ?>
</section>
<?php if ($related): ?>
<section class="band" aria-labelledby="more"><h2 id="more">More to read</h2>
  <div class="grid"><?php foreach ($related as $s) { include __DIR__ . '/partials/story_card.php'; } ?></div>
</section>
<?php endif; ?>
