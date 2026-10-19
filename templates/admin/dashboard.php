<?php /** @var array $stats @var array $recent */ ?>
<ul class="stats"><?php foreach ($stats as $label => $n): ?><li><strong><?= (int) $n ?></strong> <?= e($label) ?></li><?php endforeach; ?></ul>
<p><a class="btn" href="/admin/stories/new">New story</a></p>
<h2>Recently edited</h2>
<?php if ($recent): ?>
<table data-sortable><thead><tr><th scope="col">Title</th><th scope="col">Status</th><th scope="col">Updated</th></tr></thead><tbody>
<?php foreach ($recent as $r): ?><tr><td><a href="/admin/stories/<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></td><td><?= e($r['status']) ?></td><td data-sort="<?= e($r['updated_at']) ?>"><?= e($r['updated_at']) ?> UTC</td></tr>
<?php endforeach; ?></tbody></table>
<?php else: ?><p>No stories yet. Add one, or run the importers from the command line (see docs/SETUP.md).</p><?php endif; ?>
