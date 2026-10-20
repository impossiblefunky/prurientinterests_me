<?php /** @var int $page @var int $pages @var string $base @var array $cur */
if ($pages > 1):
    // First, last, the current page and two either side; gaps become an ellipsis.
    $pageNums = array_unique(array_filter([1, $pages, $page - 2, $page - 1, $page, $page + 1, $page + 2], fn($n) => $n >= 1 && $n <= $pages));
    sort($pageNums);
    $href = fn(int $n) => e($base . qs($cur, ['page' => $n]));
?>
<nav class="pager" aria-label="Pages">
  <ul>
    <li><?php if ($page > 1): ?><a class="btn" rel="prev" href="<?= $href($page - 1) ?>"><span aria-hidden="true">&larr;</span> Newer</a><?php else: ?><span class="btn off" aria-disabled="true"><span aria-hidden="true">&larr;</span> Newer</span><?php endif; ?></li>
    <?php $last = 0; foreach ($pageNums as $n): if ($n - $last > 1): ?><li class="gap" aria-hidden="true">&hellip;</li><?php endif; $last = $n; ?>
    <li><?php if ($n === $page): ?><span class="btn here" aria-current="page"><span class="sr-only">Page </span><?= $n ?></span><?php else: ?><a class="btn" href="<?= $href($n) ?>"><span class="sr-only">Page </span><?= $n ?></a><?php endif; ?></li>
    <?php endforeach; ?>
    <li><?php if ($page < $pages): ?><a class="btn" rel="next" href="<?= $href($page + 1) ?>">Older <span aria-hidden="true">&rarr;</span></a><?php else: ?><span class="btn off" aria-disabled="true">Older <span aria-hidden="true">&rarr;</span></span><?php endif; ?></li>
  </ul>
  <p class="meta">Page <?= $page ?> of <?= $pages ?></p>
  <?php if ($pages > 7): ?>
  <form class="pager-jump" method="get" action="<?= e($base) ?>">
    <?php foreach ($cur as $k => $v): if ($k !== 'page' && $v !== '' && $v !== null): ?><input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>"><?php endif; endforeach; ?>
    <label for="jump-<?= (int) $pages ?>">Go to page</label>
    <input id="jump-<?= (int) $pages ?>" name="page" type="number" min="1" max="<?= (int) $pages ?>" value="<?= (int) $page ?>" required="required" inputmode="numeric">
    <button class="btn" type="submit">Go</button>
  </form>
  <?php endif; ?>
</nav>
<?php endif; ?>
