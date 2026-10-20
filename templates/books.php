<?php /** @var array $books @var array $topTags */ ?>
<p class="eyebrow">The bookshelf</p>
<h1>Take the stories<br>with you.</h1>
<?php include __DIR__ . '/partials/interest_links.php'; ?>
<p class="lead">Books by <?= e(setting('author_name', setting('site_title'))) ?>.</p>
<?php foreach ($books as $b): $buy = array_values(array_filter($b['editions'], fn($e) => $e['purchase_url'] !== '')); ?>
<article class="book" id="<?= e($b['slug']) ?>">
  <?= View_cover($b) ?>
  <div>
    <p class="eyebrow">Book</p>
    <h2><?= e($b['title']) ?></h2>
    <?php if ($b['subtitle'] !== ''): ?><p class="meta"><?= e($b['subtitle']) ?></p><?php endif; ?>
    <div class="prose"><?= prose($b['description_html']) ?></div>
    <?php if ($b['editions']): ?>
    <h3 class="small">Available editions</h3>
    <ul class="editions"><?php foreach ($b['editions'] as $ed): ?>
      <li><?= e($ed['name']) ?><?= $ed['price_display'] !== '' ? ' · ' . e($ed['price_display']) : '' ?><?= $ed['isbn'] !== '' ? ' · ISBN ' . e($ed['isbn']) : '' ?>
        <?php if ($ed['purchase_url'] !== ''): ?> <a class="btn primary" href="<?= e($ed['purchase_url']) ?>" target="_blank" rel="noopener noreferrer"><span class="nowrap">Purchase options<span class="sr-only"> for <?= e($ed['name']) ?> (opens in a new tab)</span></span></a><?php endif; ?></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if (!empty($b['excerpt_html'])): ?><details class="excerpt"><summary class="btn">Read an excerpt</summary><div class="prose"><?= prose($b['excerpt_html']) ?></div></details><?php endif; ?>
  </div>
</article>
<?php endforeach; ?>
<?php if (!$books): ?><p>Nothing here yet.</p><?php endif; ?>
