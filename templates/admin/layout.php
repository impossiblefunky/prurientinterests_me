<?php /** @var string $title @var string $body @var string $flash @var string $csrf @var ?array $user @var array $resources */ ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> | Admin</title>
<link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="admin">
<a class="skip" href="#main">Skip to main content</a>
<header class="site-header"><a class="brand" href="/admin">Admin</a>
<nav aria-label="Admin"><ul>
<?php foreach ($resources as $k => $r): ?><li><a href="/admin/<?= e($k) ?>"><?= e($r['label']) ?></a></li>
<?php endforeach; ?>
<li><a href="/admin/settings">Settings</a></li><li><a href="/" target="_blank" rel="noopener"><span class="nowrap">View site<span class="sr-only"> (opens in a new tab)</span></span></a></li>
<li><form method="get" action="/admin/logout"><button class="secondary" type="submit">Sign out</button></form></li>
</ul></nav></header>
<main id="main" tabindex="-1">
<div role="status" aria-live="polite"><?php if ($flash): ?><p class="flash"><?= e($flash) ?></p><?php endif; ?></div>
<h1><?= e($title) ?></h1>
<?= $body ?>
</main>
<script src="/assets/admin.js" defer></script>
</body></html>
