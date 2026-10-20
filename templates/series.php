<?php /** @var array $series @var array $chapters */ ?>
<p class="eyebrow">Series · <?= count($chapters) ?> chapter<?= count($chapters) === 1 ? '' : 's' ?></p>
<h1><?= e($series['title']) ?></h1>
<?php if ($series['description'] !== ''): ?><p class="lead"><?= e($series['description']) ?></p><?php endif; ?>
<ol class="chapters"><?php foreach ($chapters as $c): ?>
  <li><a href="<?= e(story_url($c)) ?>"><strong>Chapter <?= (int) $c['chapter_order'] ?>: <?= e($c['title']) ?></strong></a><br><span class="meta"><?= e(format_date($c['published_at'])) ?> · <?= reading_minutes((int) $c['word_count']) ?> min read</span></li>
<?php endforeach; ?></ol>
