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
<meta name="rating" content="adult">
<?php if ($robots): ?><meta name="robots" content="<?= e($robots) ?>">
<?php endif; ?>
<?php if ($canon): ?><link rel="canonical" href="<?= e($canon) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e(setting('site_title')) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
<?php if ($canon): ?><meta property="og:url" content="<?= e($canon) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary">
<link rel="stylesheet" href="/assets/site.css">
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
  <form class="site-search" method="get" action="/search" role="search"><label for="hq" class="sr-only">Search the site</label><input id="hq" name="q" type="search" placeholder="Search…" value="<?= e((string) ($_GET['q'] ?? '')) ?>"><button class="btn" type="submit">Search</button></form>
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
    <li><a href="/tags">Interests</a></li><li><a href="/stories">Stories</a></li><li><a href="/gallery">Gallery</a></li><li><a href="/books">Books</a></li><li><a href="/leave">Leave site</a></li>
  </ul><p><?= e(setting('footer_note')) ?></p></nav>
</footer>
<?php endif; ?>
</div>
<script src="/assets/site.js" defer></script>
</body>
</html>
