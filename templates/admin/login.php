<?php /** @var string $error @var string $csrf */ ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Sign in | Admin</title><link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="admin"><a class="skip" href="#main">Skip to main content</a>
<main id="main" tabindex="-1" class="narrow"><h1>Sign in</h1>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="/admin/login" novalidate="novalidate" data-validate>
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<p><label for="email">Email</label><br><input id="email" name="email" type="email" required="required" minlength="5" pattern="[^@\s]+@[^@\s]+\.[^@\s]+" autocomplete="username"></p>
<p><label for="password">Password</label><br><input id="password" name="password" type="password" required="required" autocomplete="current-password"></p>
<p><button type="submit">Sign in</button></p>
</form></main></body></html>
