<?php /** @var array $books @var array $topTags */ ?>
<p class="eyebrow">The bookshelf</p>
<h1>Take the stories<br>with you.</h1>
<?php include __DIR__ . '/partials/interest_links.php'; ?>
<p class="lead">Books by <?= e(setting('author_name', setting('site_title'))) ?>. Choose a title to read about it and see where to get it.</p>
<p><a href="https://www.amazon.com/stores/Louis-Friend/author/B007IJ8WAQ/allbooks?tag=proboothcas0f-20" target="_blank" rel="noopener noreferrer sponsored"><?= ext_label('See every title on the Amazon author page') ?></a></p>
<?php if (has_amazon_link($books)): ?><p class="meta">As an Amazon Associate I earn from qualifying purchases.</p><?php endif; ?>
<?php if ($books): ?>
<ul class="catalog">
<?php foreach ($books as $b): $blurb = excerpt((string) $b['description_html'], 170); ?>
  <li class="catalog-item">
    <a class="catalog-cover" href="/books/<?= e($b['slug']) ?>" aria-hidden="true" tabindex="-1"><?= View_cover($b) ?></a>
    <div>
      <h2><a href="/books/<?= e($b['slug']) ?>"><?= e($b['title']) ?></a></h2>
      <?php if ($b['subtitle'] !== ''): ?><p class="meta"><?= e($b['subtitle']) ?></p><?php endif; ?>
      <?php if ($blurb !== ''): ?><p class="summary"><?= e($blurb) ?></p><?php endif; ?>
      <?php if ($b['editions']): ?><p class="meta"><span class="sr-only">Formats: </span><?= e(implode(' · ', array_map(fn($e) => $e['name'], $b['editions']))) ?></p><?php endif; ?>
      <p><a class="btn" href="/books/<?= e($b['slug']) ?>">View book<span class="sr-only">: <?= e($b['title']) ?></span> &rarr;</a></p>
    </div>
  </li>
<?php endforeach; ?>
</ul>
<?php else: ?><p>Nothing here yet.</p><?php endif; ?>
