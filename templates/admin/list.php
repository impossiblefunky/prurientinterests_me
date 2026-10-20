<?php /** @var string $key @var array $def @var array $rows @var string $csrf @var DateTimeZone $tz @var int $page @var int $pages @var int $total */
$fmt = function (string $col, $v) use ($tz) {
    if (in_array($col, ['published_at', 'updated_at', 'created_at'], true) && $v) { return (new DateTimeImmutable((string) $v, new DateTimeZone('UTC')))->setTimezone($tz)->format('M j, Y g:i a'); }
    if ($col === 'featured' || $col === 'hidden') { return $v ? 'Yes' : ''; }
    return (string) $v;
}; ?>
<?php if (!empty($def['help'])): ?><p class="meta"><?= e($def['help']) ?></p><?php endif; ?>
<p class="actions"><a class="btn primary" href="/admin/<?= e($key) ?>/new">New <?= e($def['singular']) ?></a><?php if (!empty($def['bulk'])): ?> <a class="btn" href="/admin/<?= e($key) ?>/upload">Add many images</a> <a class="btn" href="/admin/<?= e($key) ?>/duplicates">Find duplicates</a><?php endif; ?></p>
<?php foreach ($def['filters'] ?? [] as $col => $options): ?>
<nav aria-label="Filter by <?= e($col) ?>"><ul class="chips small">
  <li><a class="chip" href="/admin/<?= e($key) ?>"<?= empty($_GET[$col]) ? ' aria-current="true"' : '' ?>>All</a></li>
  <?php foreach ($options as $ov => $ol): ?><li><a class="chip" href="/admin/<?= e($key) ?>?<?= e($col) ?>=<?= e((string) $ov) ?>"<?= ($_GET[$col] ?? '') === (string) $ov ? ' aria-current="true"' : '' ?>><?= e($ol) ?></a></li><?php endforeach; ?>
</ul></nav>
<?php endforeach; ?>
<?php $bulk = !empty($def['bulk_status']); $drafts = $bulk ? count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'draft')) : 0; ?>
<?php if ($rows && $bulk): ?>
<form id="bulk-form" class="bulkbar" method="post" action="/admin/<?= e($key) ?>/bulk"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <span class="meta">With the ticked rows:</span>
  <?php if ($key === 'albums'): ?><button class="btn primary" type="submit" name="do" value="publish_with_images">Publish albums and all their images</button><?php else: ?><button class="btn primary" type="submit" name="do" value="publish">Publish</button><?php endif; ?>
  <button class="btn" type="submit" name="do" value="draft">Move back to draft</button>
  <?php if ($drafts): ?><button class="btn" type="submit" name="do" value="publish_all_drafts" data-confirm-click="Publish ALL <?= (int) $drafts ?> drafts? Everything will be visible to visitors. You can move items back to draft afterwards.">Publish all <?= (int) $drafts ?> drafts</button><?php endif; ?>
</form>
<?php endif; ?>
<?php if ($rows): ?>
<table data-sortable><thead><tr>
<?php if ($bulk): ?><th scope="col" data-nosort><input type="checkbox" data-select-all aria-label="Tick every row"></th><?php endif; ?>
<?php foreach ($def['columns'] as $col => $label): ?><th scope="col"<?= $col === 'thumb' ? ' data-nosort' : '' ?>><?= e($label) ?></th><?php endforeach; ?>
<th scope="col" data-nosort>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php if ($bulk): ?><td><input type="checkbox" form="bulk-form" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="Tick <?= e((string) ($r['title'] ?? '')) ?>"></td><?php endif; ?>
<?php $first = true; foreach ($def['columns'] as $col => $label): $v = $r[$col] ?? ''; ?>
<?php if ($col === 'thumb'): $img = ($r['thumb'] ?? '') !== '' ? $r['thumb'] : ($r['file'] ?? ''); ?>
<td class="thumbcell"><a href="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>"><?php if ($img): ?><img src="/uploads/<?= e((string) $img) ?>" alt="" width="72" height="72" loading="lazy"><?php endif; ?><span class="sr-only">Edit <?= e((string) ($r['title'] ?? '')) ?></span></a></td>
<?php continue; endif; ?>
<td data-sort="<?= e((string) $v) ?>"><?php if ($first): ?><a href="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>"><?= e($fmt($col, $v)) ?: '(untitled)' ?></a><?php else: ?><?= e($fmt($col, $v)) ?><?php endif; ?></td>
<?php $first = false; endforeach; ?>
<td><div class="rowactions"><a class="btn" href="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>">Edit</a>
<form class="inline" method="post" action="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>/delete" data-confirm="Delete this <?= e($def['singular']) ?>? This cannot be undone."><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="btn danger" type="submit">Delete</button></form></div></td>
</tr><?php endforeach; ?></tbody></table>
<?php if (($pages ?? 1) > 1): $cur = $_GET; $base = '/admin/' . $key; include dirname(__DIR__) . '/partials/pager.php'; endif; ?>
<p class="meta"><?= ($pages ?? 1) > 1 ? (int) $total : count($rows) ?> <?= e(strtolower($def['label'])) ?><?= count($rows) >= 3000 ? ' (showing the first 3,000)' : '' ?></p>
<?php else: ?><p>Nothing here yet.</p><?php endif; ?>
