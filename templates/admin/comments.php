<?php /** @var array $waiting @var array $approved @var string $csrf @var DateTimeZone $tz */
$when = fn($v) => $v ? (new DateTimeImmutable((string) $v, new DateTimeZone('UTC')))->setTimezone($tz)->format('M j, Y g:i a') : '';
$btn = fn(string $url, string $label, string $cls = '', string $confirm = '') => '<form class="inline" method="post" action="' . e($url) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '><input type="hidden" name="csrf" value="' . e($csrf) . '"><button class="btn ' . $cls . '" type="submit">' . e($label) . '</button></form>';
$link = fn(array $c) => !empty($c['legacy_path']) ? $c['legacy_path'] : (!empty($c['slug']) ? '/story/' . $c['slug'] : ''); ?>
<p class="lead">Comments visitors leave on stories wait here until you approve them. Nothing appears on the site before that.</p>
<section aria-labelledby="w-h"><h2 id="w-h">Waiting for approval (<?= count($waiting) ?>)</h2>
<?php if (!$waiting): ?><p>Nothing waiting.</p><?php endif; ?>
<?php foreach ($waiting as $c): ?>
<article class="inbox-item">
  <p class="meta"><strong><?= e($c['author'] !== '' ? $c['author'] : 'Anonymous') ?></strong><?= $c['author_email'] !== '' ? ' · ' . e($c['author_email']) : '' ?> · on <?php if ($link($c) !== ''): ?><a href="<?= e($link($c)) ?>" target="_blank" rel="noopener"><?= ext_label(e((string) $c['story'])) ?></a><?php else: ?><em><?= e((string) $c['story']) ?></em><?php endif; ?> · <?= e($when($c['commented_at'])) ?></p>
  <div class="prose"><?= $c['body_html'] ?></div>
  <div class="rowactions"><?= $btn('/admin/comments/' . (int) $c['id'] . '/approve', 'Approve', 'primary') ?><?= $btn('/admin/comments/' . (int) $c['id'] . '/delete', 'Delete', 'danger', 'Delete this comment? This cannot be undone.') ?></div>
</article>
<?php endforeach; ?></section>
<section aria-labelledby="a-h"><h2 id="a-h">Approved comments on this site (latest <?= count($approved) ?>)</h2>
<p class="meta">The 215 comments imported from the old blog are not listed here.</p>
<?php if (!$approved): ?><p>None yet.</p><?php endif; ?>
<?php foreach ($approved as $c): ?>
<article class="inbox-item">
  <p class="meta"><strong><?= e($c['author'] !== '' ? $c['author'] : 'Anonymous') ?></strong> · on <em><?= e((string) $c['story']) ?></em> · <?= e($when($c['commented_at'])) ?></p>
  <div class="prose"><?= $c['body_html'] ?></div>
  <div class="rowactions"><?= $btn('/admin/comments/' . (int) $c['id'] . '/hide', 'Hide again') ?><?= $btn('/admin/comments/' . (int) $c['id'] . '/delete', 'Delete', 'danger', 'Delete this comment? This cannot be undone.') ?></div>
</article>
<?php endforeach; ?></section>
