<?php /** @var array $tags */ ?>
<h1>Tags</h1>
<?php if ($tags): ?>
<ul class="taglist"><?php foreach ($tags as $t): ?>
  <li><a href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a> <span>(<?= (int) $t['n'] ?>)</span><?php if ($t['description'] !== ''): ?><br><small><?= e($t['description']) ?></small><?php endif; ?></li>
<?php endforeach; ?></ul>
<?php else: ?><p>No tags yet.</p><?php endif; ?>
