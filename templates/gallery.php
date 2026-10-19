<?php /** @var array $items */ ?>
<h1>Gallery</h1>
<?php if ($items): ?>
<ul class="gallery"><?php foreach ($items as $g): ?>
  <li><figure>
    <a href="/uploads/<?= e($g['file']) ?>"><img src="/uploads/<?= e($g['thumb'] !== '' ? $g['thumb'] : $g['file']) ?>" alt="<?= e($g['alt_text']) ?>" loading="lazy"<?= $g['width'] ? ' width="' . (int) $g['width'] . '" height="' . (int) $g['height'] . '"' : '' ?>></a>
    <?php if ($g['caption'] !== ''): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif; ?>
  </figure></li>
<?php endforeach; ?></ul>
<?php else: ?><p>Nothing here yet.</p><?php endif; ?>
