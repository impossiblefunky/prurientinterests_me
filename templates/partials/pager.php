<?php /** @var int $page @var int $pages @var string $base @var array $cur */ if ($pages > 1): ?>
<nav class="pager" aria-label="Pages"><ul>
  <?php if ($page > 1): ?><li><a class="btn" rel="prev" href="<?= e($base . qs($cur, ['page' => $page - 1])) ?>">&larr; Newer</a></li><?php endif; ?>
  <li><span aria-current="page">Page <?= $page ?> of <?= $pages ?></span></li>
  <?php if ($page < $pages): ?><li><a class="btn" rel="next" href="<?= e($base . qs($cur, ['page' => $page + 1])) ?>">Older &rarr;</a></li><?php endif; ?>
</ul></nav>
<?php endif; ?>
