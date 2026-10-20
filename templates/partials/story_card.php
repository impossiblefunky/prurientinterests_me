<?php /** @var array $s */
$isSeries = !empty($s['series_id']); ?>
<article class="entry">
  <p class="eyebrow"><?= e($s['label'] ?? ($isSeries ? 'Series' : ($s['kind'] === 'video' ? 'Video' : 'Standalone'))) ?></p>
  <h3><a href="<?= e(story_url($s)) ?>"><?= e($isSeries ? $s['series_title'] : $s['title']) ?></a></h3>
  <p class="summary"><?= e($s['summary'] !== '' ? $s['summary'] : excerpt($s['body_html'], 170)) ?></p>
  <p class="meta"><?= e(card_meta($s)) ?></p>
  <p><a class="btn" href="<?= e(story_url($s)) ?>"><?= $isSeries ? 'Begin with chapter one' : 'Read the story' ?> &rarr;</a></p>
</article>
