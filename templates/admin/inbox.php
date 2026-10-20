<?php /** @var array $comments @var array $messages @var string $csrf @var DateTimeZone $tz */
$when = fn($v) => $v ? (new DateTimeImmutable((string) $v, new DateTimeZone('UTC')))->setTimezone($tz)->format('M j, Y g:i a') : '';
$btn = fn(string $url, string $label, string $cls = '', string $confirm = '') => '<form class="inline" method="post" action="' . e($url) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '><input type="hidden" name="csrf" value="' . e($csrf) . '"><button class="btn ' . $cls . '" type="submit">' . e($label) . '</button></form>'; ?>
<section aria-labelledby="cm-h"><h2 id="cm-h">Comments waiting for approval (<?= count($comments) ?>)</h2>
<?php if (!$comments): ?><p>Nothing waiting.</p><?php endif; ?>
<?php foreach ($comments as $c): ?>
<article class="inbox-item">
  <p class="meta"><strong><?= e($c['author'] !== '' ? $c['author'] : 'Anonymous') ?></strong><?= $c['author_email'] !== '' ? ' · ' . e($c['author_email']) : '' ?> · on <em><?= e((string) $c['story']) ?></em> · <?= e($when($c['commented_at'])) ?></p>
  <div class="prose"><?= $c['body_html'] ?></div>
  <div class="rowactions"><?= $btn('/admin/inbox/comment/' . (int) $c['id'] . '/approve', 'Approve', 'primary') ?><?= $btn('/admin/inbox/comment/' . (int) $c['id'] . '/delete', 'Delete', 'danger', 'Delete this comment? This cannot be undone.') ?></div>
</article>
<?php endforeach; ?></section>
<section aria-labelledby="ms-h"><h2 id="ms-h">Messages from the contact form (<?= count($messages) ?>)</h2>
<?php if (!$messages): ?><p>No messages yet.</p><?php endif; ?>
<?php foreach ($messages as $m): ?>
<article class="inbox-item">
  <p class="meta"><strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a> · <?= e($when($m['created_at'])) ?><?= $m['status'] === 'new' ? ' · <strong>New</strong>' : '' ?></p>
  <p><?= nl2br(e($m['body'])) ?></p>
  <div class="rowactions"><?php if ($m['status'] === 'new') { echo $btn('/admin/inbox/message/' . (int) $m['id'] . '/read', 'Mark as read'); } ?><?= $btn('/admin/inbox/message/' . (int) $m['id'] . '/delete', 'Delete', 'danger', 'Delete this message? This cannot be undone.') ?></div>
</article>
<?php endforeach; ?></section>
