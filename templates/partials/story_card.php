<?php /** @var array $s */
$mins = reading_minutes((int) $s['word_count']); ?>
<article class="card">
  <h3><a href="<?= e(story_url($s)) ?>"><?= e($s['title']) ?></a></h3>
  <p class="meta"><time datetime="<?= e(gmdate('Y-m-d', strtotime($s['published_at'] . ' UTC'))) ?>"><?= e(format_date($s['published_at'])) ?></time> &middot; <?= $mins ?> min read</p>
  <p><?= e($s['summary'] !== '' ? $s['summary'] : excerpt($s['body_html'], 200)) ?></p>
</article>
