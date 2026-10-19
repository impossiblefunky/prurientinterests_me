<?php /** @var array $books */ ?>
<h1>Books</h1>
<?php foreach ($books as $b): ?>
<article class="book">
  <?php if ($b['cover_image']): ?><img src="/uploads/<?= e($b['cover_image']) ?>" alt="Cover of <?= e($b['title']) ?>" width="200" loading="lazy"><?php endif; ?>
  <div>
    <h2><?= e($b['title']) ?></h2>
    <?php if ($b['subtitle'] !== ''): ?><p class="meta"><?= e($b['subtitle']) ?></p><?php endif; ?>
    <div class="prose"><?= $b['description_html'] ?></div>
    <?php if ($b['buy_url'] !== ''): ?><p><a class="btn" href="<?= e($b['buy_url']) ?>" target="_blank" rel="noopener"><span class="nowrap"><?= e($b['buy_label'] !== '' ? $b['buy_label'] : 'Buy') ?><span class="sr-only"> (opens in a new tab)</span></span></a></p><?php endif; ?>
  </div>
</article>
<?php endforeach; ?>
<?php if (!$books): ?><p>Nothing here yet.</p><?php endif; ?>
