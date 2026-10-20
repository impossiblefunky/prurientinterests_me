<?php /** @var array $g */ ?>
<li><figure class="shot">
  <a href="/uploads/<?= e($g['file']) ?>"><img src="/uploads/<?= e($g['thumb'] !== '' ? $g['thumb'] : $g['file']) ?>" alt="<?= e($g['alt_text'] !== '' && $g['alt_text'] === $g['caption'] ? '' : $g['alt_text']) ?>" loading="lazy"<?= $g['width'] ? ' width="' . (int) $g['width'] . '" height="' . (int) $g['height'] . '"' : '' ?>></a>
  <?php if ($g['caption'] !== '' || $g['credit'] !== '' || $g['source_url'] !== ''): ?><figcaption>
    <?= e($g['caption']) ?>
    <?php if ($g['credit'] !== '' || $g['source_url'] !== ''): ?><span class="credit"><?php
      $label = $g['credit'] !== '' ? $g['credit'] : 'Source';
      if ($g['source_url'] !== ''): ?><a href="<?= e($g['source_url']) ?>" target="_blank" rel="noopener noreferrer"><span class="nowrap"><?= e($label) ?><span class="sr-only"> (opens in a new tab)</span></span></a><?php else: ?><?= e($label) ?><?php endif; ?></span><?php endif; ?>
  </figcaption><?php endif; ?>
</figure></li>
