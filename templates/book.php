<?php /** @var array $book @var array $others */
$buy = array_values(array_filter($book['editions'], fn($e) => $e['purchase_url'] !== '')); ?>
<article class="book" id="<?= e($book['slug']) ?>">
  <?= View_cover($book) ?>
  <div>
    <p class="eyebrow">Book</p>
    <h1><?= e($book['title']) ?></h1>
    <?php if ($book['subtitle'] !== ''): ?><p class="meta"><?= e($book['subtitle']) ?></p><?php endif; ?>
    <?php if ($buy): ?>
    <h2 class="small">Where to get it</h2>
    <ul class="buy-list"><?php foreach ($buy as $ed): $fine = trim(($ed['price_display'] !== '' ? $ed['price_display'] : '') . ($ed['isbn'] !== '' ? ($ed['price_display'] !== '' ? ' · ' : '') . 'ISBN ' . preg_replace('/[^0-9Xx]/', '', (string) $ed['isbn']) : '')); ?>
      <li><a class="btn primary" href="<?= e($ed['purchase_url']) ?>" target="_blank" rel="noopener noreferrer sponsored"><?= ext_label(e(buy_label($ed))) ?></a><?php if ($fine !== ''): ?><span class="meta"><?= e($fine) ?></span><?php endif; ?></li>
    <?php endforeach; ?></ul>
    <?php if (has_amazon_link([$book])): ?><p class="meta">As an Amazon Associate I earn from qualifying purchases.</p><?php endif; ?>
    <?php endif; ?>
    <?php if (trim((string) $book['description_html']) !== ''): ?><h2 class="small">About this book</h2><div class="prose"><?= prose($book['description_html']) ?></div><?php endif; ?>
    <?php if (!empty($book['excerpt_html'])): ?><details class="excerpt"><summary class="btn">Read an excerpt</summary><div class="prose"><?= prose($book['excerpt_html']) ?></div></details><?php endif; ?>
  </div>
</article>
<?php if ($others): ?>
<section class="band" aria-labelledby="more-books"><h2 id="more-books">More books</h2>
  <ul class="boxes"><?php foreach ($others as $o): ?><li><a href="/books/<?= e($o['slug']) ?>"><?= e($o['title']) ?></a></li><?php endforeach; ?></ul>
  <p><a class="btn" href="/books">All books &rarr;</a></p>
</section>
<?php endif; ?>
