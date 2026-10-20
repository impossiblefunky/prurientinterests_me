<?php /** @var array $topTags */ if (!empty($topTags)): ?>
<p class="interest-links"><a href="/tags">Browse all interests &rarr;</a> <?php foreach ($topTags as $t): ?><a href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a> <?php endforeach; ?></p>
<?php endif; ?>
