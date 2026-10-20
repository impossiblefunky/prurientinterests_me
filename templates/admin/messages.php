<?php /** @var array $messages @var string $csrf @var DateTimeZone $tz */
$when = fn($v) => $v ? (new DateTimeImmutable((string) $v, new DateTimeZone('UTC')))->setTimezone($tz)->format('M j, Y g:i a') : '';
$btn = fn(string $url, string $label, string $cls = '', string $confirm = '') => '<form class="inline" method="post" action="' . e($url) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '><input type="hidden" name="csrf" value="' . e($csrf) . '"><button class="btn ' . $cls . '" type="submit">' . e($label) . '</button></form>';
$new = count(array_filter($messages, fn($m) => $m['status'] === 'new')); ?>
<p class="lead">Messages sent from the Contact page. They also arrive in your email, and your reply goes straight to the sender.</p>
<section aria-labelledby="m-h"><h2 id="m-h">Messages (<?= count($messages) ?><?= $new ? ', ' . $new . ' unread' : '' ?>)</h2>
<?php if ($new > 1): ?><div class="rowactions"><?= $btn('/admin/messages/all/read', 'Mark all as read') ?></div><?php endif; ?>
<?php if (!$messages): ?><p>No messages yet.</p><?php endif; ?>
<?php foreach ($messages as $m): ?>
<article class="inbox-item">
  <p class="meta"><strong><?= e($m['name']) ?></strong> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: your message to ' . setting('site_title')) ?>"><?= e($m['email']) ?></a> · <?= e($when($m['created_at'])) ?><?= $m['status'] === 'new' ? ' · <strong>New</strong>' : '' ?></p>
  <p><?= nl2br(e($m['body'])) ?></p>
  <div class="rowactions"><?= $m['status'] === 'new' ? $btn('/admin/messages/' . (int) $m['id'] . '/read', 'Mark as read') : $btn('/admin/messages/' . (int) $m['id'] . '/unread', 'Mark as unread') ?><?= $btn('/admin/messages/' . (int) $m['id'] . '/delete', 'Delete', 'danger', 'Delete this message? This cannot be undone.') ?></div>
</article>
<?php endforeach; ?></section>
