<?php /** @var array $groups @var int $waiting @var string $csrf */ ?>
<p class="meta">Looks for pictures that are the same image, even when one copy is a different size. Nothing is deleted until you press Delete. Keep the copy you want and delete the others; a picture file that another entry still uses is never removed from the server.</p>
<?php if ($waiting): ?>
<p role="status"><?= (int) $waiting ?> images still need to be fingerprinted. <a class="btn primary" href="/admin/gallery/duplicates">Keep going</a></p>
<?php elseif (!$groups): ?>
<p role="status">No duplicates found.</p>
<?php else: ?>
<p role="status"><?= count($groups) ?> sets of duplicates found.</p>
<?php foreach ($groups as $i => $g): ?>
<section class="dupe-set" aria-labelledby="set<?= $i ?>"><h2 id="set<?= $i ?>" class="h3">Set <?= $i + 1 ?> · <?= count($g['items']) ?> pictures · <?= $g['exact'] ? 'identical' : 'very similar' ?></h2>
  <ul class="dupe-list">
  <?php foreach ($g['items'] as $it): ?>
    <li>
      <a href="/uploads/<?= e((string) $it['file']) ?>" target="_blank" rel="noopener"><img src="/uploads/<?= e((string) ($it['thumb'] !== '' ? $it['thumb'] : $it['file'])) ?>" alt="<?= e((string) $it['title']) ?> (opens in a new tab)" loading="lazy"></a>
      <p><strong><?= e((string) $it['title']) ?></strong><br><span class="meta"><?= (int) $it['width'] ?>×<?= (int) $it['height'] ?> · <?= e((string) $it['status']) ?><?= $it['album'] ? ' · ' . e((string) $it['album']) : '' ?></span></p>
      <div class="rowactions"><a class="btn" href="/admin/gallery/<?= (int) $it['id'] ?>">Edit</a>
        <form class="inline" method="post" action="/admin/gallery/<?= (int) $it['id'] ?>/delete" data-confirm="Delete this picture? This cannot be undone."><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="back" value="/admin/gallery/duplicates"><button class="btn danger" type="submit">Delete</button></form></div>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; endif; ?>
