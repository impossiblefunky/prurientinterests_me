<?php /** @var array $featured @var array $cols @var array $others */ ?>
<p class="eyebrow">Explore the collection</p>
<h1>Follow an interest.</h1>
<p class="lead">One tag can lead across years of writing, pictures, and books.</p>
<?php if ($featured): ?><ul class="chips"><?php foreach ($featured as $t): ?><li><a class="chip" href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<section class="band" aria-labelledby="every-h">
  <h2 id="every-h">Browse every tag</h2>
  <?php if ($cols): ?>
  <div class="cols">
    <?php foreach ($cols as $col): $a = mb_strtoupper(mb_substr($col[0]['name'], 0, 1)); $z = mb_strtoupper(mb_substr(end($col)['name'], 0, 1)); ?>
    <div><h3><?= $a === $z ? e($a) : e($a) . '–' . e($z) ?></h3>
      <ul class="taglist"><?php foreach ($col as $t): ?><li><a href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?> &rarr;</a> <span class="meta">(<?= (int) $t['n'] ?>)</span></li><?php endforeach; ?></ul>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?><p>No tags yet.</p><?php endif; ?>
</section>
<?php if ($others): ?>
<section class="band" aria-labelledby="other-h">
  <h2 id="other-h">Formats and sources</h2>
  <p class="lead">Labels from the old blog: kinds of post, and the sites the writing first appeared on.</p>
  <ul class="chips quiet"><?php foreach ($others as $t): ?><li><a class="chip" href="/tag/<?= e($t['slug']) ?>"><?= e($t['name']) ?></a> <span class="meta">(<?= (int) $t['n'] ?>)</span></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
