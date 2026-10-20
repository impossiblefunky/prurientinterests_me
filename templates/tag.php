<?php /** @var array $tag @var string $type @var array $rows @var array $images @var array $books @var int $nStories @var int $page @var int $pages @var string $base @var array $cur */
$types = ['everything' => 'Everything'] + ($nStories ? ['stories' => 'Stories'] : []) + ($images ? ['images' => 'Images'] : []) + ($books ? ['books' => 'Books'] : []);
$showType = fn(string $t) => $type === 'everything' || $type === $t; ?>
<p class="eyebrow">Interest</p>
<h1><?= e($tag['name']) ?></h1>
<p class="lead"><?= e($tag['description'] !== '' ? $tag['description'] : 'Writing, images, and books connected by an interest.') ?></p>
<?php if (count($types) > 2): ?>
<form method="get" action="<?= e($base) ?>"><fieldset class="radios"><legend class="sr-only">Show</legend>
  <?php foreach ($types as $k => $label): ?><label><input type="radio" name="type" value="<?= e($k) ?>"<?= $type === $k ? ' checked="checked"' : '' ?>> <?= e($label) ?></label><?php endforeach; ?>
  <button class="btn" type="submit">Show</button>
</fieldset></form>
<?php endif; ?>
<?php if ($showType('stories') && $rows): ?>
<section aria-labelledby="t-stories"><h2 id="t-stories" class="sr-only">Stories</h2>
  <div class="grid"><?php foreach ($rows as $s) { include __DIR__ . '/partials/story_card.php'; } ?></div>
  <?php include __DIR__ . '/partials/pager.php'; ?>
</section>
<?php endif; ?>
<?php if ($showType('images') && $images): ?>
<section class="band" aria-labelledby="t-images"><h2 id="t-images">Images</h2>
  <ul class="gallery"><?php foreach ($images as $g) { include __DIR__ . '/partials/gallery_item.php'; } ?></ul>
  <p><a class="btn" href="/gallery">Open the gallery &rarr;</a></p>
</section>
<?php endif; ?>
<?php if ($showType('books') && $books): ?>
<section class="band" aria-labelledby="t-books"><h2 id="t-books">Related books</h2>
  <ul class="boxes"><?php foreach ($books as $b): ?><li><a href="/books/<?= e($b['slug']) ?>"><?= e($b['title']) ?></a></li><?php endforeach; ?></ul>
  <p><a class="btn" href="/books">Visit the bookshelf &rarr;</a></p>
</section>
<?php endif; ?>
<?php if (!$rows && !$images && !$books): ?><p>Nothing is tagged <?= e($tag['name']) ?> yet.</p><?php endif; ?>
<section class="band"><div class="band-head"><h2>Keep exploring</h2><a class="btn" href="/tags">All interests &rarr;</a></div></section>
