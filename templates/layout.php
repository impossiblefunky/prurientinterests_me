<?php /** @var string $content @var array $meta @var array $d */
$title = $meta['title'] ?? setting('site_title');
$desc = $meta['description'] ?? '';
$canon = $meta['canonical'] ?? null;
$robots = $meta['robots'] ?? null;
$crumbs = $meta['breadcrumbs'] ?? [];
$jsonld = $meta['jsonld'] ?? [];
$here = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($crumbs) {
    $items = array_merge([['Home', '/']], $crumbs);
    $jsonld[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(
        fn($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => site_url($c[1])], $items, array_keys($items))];
}
$current = fn(string $url) => $url === '/' ? $here === '/' : ($here === $url || str_starts_with($here, rtrim($url, '/') . '/'));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (($gsv = trim((string) setting('google_site_verification', ''))) !== '' && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $gsv)): ?><meta name="google-site-verification" content="<?= e($gsv) ?>"><?php endif; ?>
<meta name="rating" content="adult">
<?php if ($robots): ?><meta name="robots" content="<?= e($robots) ?>">
<?php endif; ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="theme-color" content="#0f0d10">
<link rel="alternate" type="application/atom+xml" title="<?= e(setting('site_title')) ?>" href="/feed">
<?php if ($canon): ?><link rel="canonical" href="<?= e($canon) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e(setting('site_title')) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
<?php if ($canon): ?><meta property="og:url" content="<?= e($canon) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary">
<link rel="stylesheet" href="<?= e(asset('/assets/site.css')) ?>">
<?php foreach ($jsonld as $j): ?><script type="application/ld+json"><?= json_encode($j, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endforeach; ?>
</head>
<body<?= !empty($meta['bare']) ? ' class="bare"' : (!empty($d['gate']) ? ' class="gated"' : '') ?>>
<?php if (!empty($d['gate'])): ?>
<div class="gate-overlay" role="dialog" aria-modal="true" aria-labelledby="gate-h"><?php $return = $d['gateReturn']; $headingTag = 'h2'; include __DIR__ . '/partials/gate_form.php'; ?></div>
<?php endif; ?>
<div class="page"<?= !empty($d['gate']) ? ' inert="inert" aria-hidden="true"' : '' ?>>
<a class="skip" href="#main">Skip to main content</a>
<?php if (empty($meta['bare'])): ?>
<header class="site-header">
  <a class="brand" href="/"><span class="brand-name"><?= e(setting('site_title')) ?></span><span class="brand-tag"><?= e(setting('tagline')) ?></span></a>
  <form class="site-search" method="get" action="/search" role="search"><label for="hq" class="sr-only">Search the site</label><input id="hq" name="q" type="search" placeholder="Search…" value="<?= e((string) ($_GET['q'] ?? '')) ?>"><button class="btn primary search-btn" type="submit"><svg class="search-icon" aria-hidden="true" focusable="false" viewBox="0 0 16 16"><circle cx="6.5" cy="6.5" r="4.5"/><path d="m10 10 4.5 4.5"/></svg>Search</button></form>
  <nav aria-label="Main"><ul>
    <?php foreach ($d['nav']['header'] as $n): ?><li><a class="navbtn" href="<?= e($n['url']) ?>"<?= $current($n['url']) ? ' aria-current="page"' : '' ?>><?= e($n['label']) ?></a></li>
    <?php endforeach; ?>
  </ul></nav>
</header>
<?php endif; ?>
<main id="main" tabindex="-1">
<?php if ($crumbs && empty($meta['bare'])): ?>
<nav class="crumbs" aria-label="Breadcrumb"><ol>
  <li><a href="/">Home</a></li>
  <?php foreach ($crumbs as $i => $c): ?><li><?= $i === count($crumbs) - 1 ? '<span aria-current="page">' . e($c[0]) . '</span>' : '<a href="' . e($c[1]) . '">' . e($c[0]) . '</a>' ?></li>
  <?php endforeach; ?>
</ol></nav>
<?php endif; ?>
<?= $content ?>
</main>
<?php if (empty($meta['bare'])): ?>
<footer class="site-footer">
  <p><strong><?= e(setting('site_title')) ?> · <?= e(setting('author_name')) ?></strong><br>A personal archive, since 2006</p>
  <nav aria-label="Footer"><ul>
    <?php foreach ($d['nav']['footer'] as $n): ?><li><a href="<?= e($n['url']) ?>"><?= e($n['label']) ?></a></li>
    <?php endforeach; ?>
    <li><a href="/tags">Interests</a></li><li><a href="/stories">Stories</a></li><li><a href="/gallery">Gallery</a></li><li><a href="/books">Books</a></li><li><a href="/contact">Contact</a></li><li><a href="/leave">Leave site</a></li>
  </ul><p><?= e(setting('footer_note')) ?></p></nav>
</footer>
<?php endif; ?>
</div>
<script src="<?= e(asset('/assets/site.js')) ?>" defer></script>
</body>
</html>
