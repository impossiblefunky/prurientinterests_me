<?php /** @var string $title @var string $body @var string $flash @var string $csrf @var ?array $user @var array $resources @var array $badges */
$here = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$groups = [];
foreach ($resources as $k => $r) { $groups[$r['group']][] = [$r['label'], '/admin/' . $k]; }
$groups['Pages & homepage'][] = ['Settings', '/admin/settings'];
$on = fn(string $u) => $here === $u || str_starts_with($here, $u . '/'); ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> | Admin</title>
<link rel="stylesheet" href="<?= e(asset('/assets/site.css')) ?>"><link rel="stylesheet" href="<?= e(asset('/assets/admin.css')) ?>"></head>
<body class="admin">
<a class="skip" href="#main">Skip to main content</a>
<header class="site-header"><a class="brand" href="/admin"><span class="brand-name"><?= e(setting('site_title')) ?></span><span class="brand-tag">Admin</span></a>
<nav aria-label="Admin tools"><ul>
<li><a class="navbtn" href="/" target="_blank" rel="noopener"><?= ext_label('View site') ?></a></li>
<li><form method="get" action="/admin/logout"><button class="btn" type="submit">Sign out</button></form></li>
</ul></nav></header>
<div class="desk">
<nav class="sidebar" aria-label="The writing desk"><p class="eyebrow">The writing desk</p>
<p><a class="navbtn<?= $here === '/admin' ? ' on' : '' ?>" href="/admin"<?= $here === '/admin' ? ' aria-current="page"' : '' ?>>Dashboard</a></p>
<?php foreach ([['/admin/comments', 'Comments', (int) ($badges['comments'] ?? 0), 'waiting for approval'], ['/admin/messages', 'Messages', (int) ($badges['messages'] ?? 0), 'unread']] as [$url, $label, $n, $what]): ?>
<p><a class="navbtn<?= $on($url) ? ' on' : '' ?>" href="<?= e($url) ?>"<?= $on($url) ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($n): ?><span class="dot" aria-hidden="true"></span><span class="sr-only"> (<?= $n ?> <?= e($what) ?>)</span><?php endif; ?></a></p>
<?php endforeach; ?>
<?php foreach ($groups as $g => $links): ?>
<h2 class="grp"><?= e($g) ?></h2><ul><?php foreach ($links as [$label, $url]): ?><li><a class="navbtn<?= $on($url) ? ' on' : '' ?>" href="<?= e($url) ?>"<?= $on($url) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li><?php endforeach; ?></ul>
<?php endforeach; ?>
</nav>
<main id="main" tabindex="-1">
<div role="status" aria-live="polite"><?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?></div>
<h1><?= e($title) ?></h1>
<?= $body ?>
</main></div>
<script src="<?= e(asset('/assets/admin.js')) ?>" defer></script>
</body></html>
