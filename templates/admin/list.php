<?php /** @var string $key @var array $def @var array $rows @var string $csrf @var DateTimeZone $tz */
$fmt = function (string $col, $v) use ($tz) {
    if (in_array($col, ['published_at', 'updated_at', 'created_at'], true) && $v) { return (new DateTimeImmutable((string) $v, new DateTimeZone('UTC')))->setTimezone($tz)->format('M j, Y g:i a'); }
    if ($col === 'featured') { return $v ? 'Yes' : ''; }
    return (string) $v;
}; ?>
<?php if (!empty($def['help'])): ?><p class="meta"><?= e($def['help']) ?></p><?php endif; ?>
<p class="actions"><a class="btn primary" href="/admin/<?= e($key) ?>/new">New <?= e($def['singular']) ?></a><?php if (!empty($def['bulk'])): ?> <a class="btn" href="/admin/<?= e($key) ?>/upload">Add many images</a><?php endif; ?></p>
<?php foreach ($def['filters'] ?? [] as $col => $options): ?>
<nav aria-label="Filter by <?= e($col) ?>"><ul class="chips small">
  <li><a class="chip" href="/admin/<?= e($key) ?>"<?= empty($_GET[$col]) ? ' aria-current="true"' : '' ?>>All</a></li>
  <?php foreach ($options as $ov => $ol): ?><li><a class="chip" href="/admin/<?= e($key) ?>?<?= e($col) ?>=<?= e((string) $ov) ?>"<?= ($_GET[$col] ?? '') === (string) $ov ? ' aria-current="true"' : '' ?>><?= e($ol) ?></a></li><?php endforeach; ?>
</ul></nav>
<?php endforeach; ?>
<?php if ($rows): ?>
<table data-sortable><thead><tr>
<?php foreach ($def['columns'] as $col => $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach; ?>
<th scope="col" data-nosort>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php $first = true; foreach ($def['columns'] as $col => $label): $v = $r[$col] ?? ''; ?>
<td data-sort="<?= e((string) $v) ?>"><?php if ($first): ?><a href="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>"><?= e($fmt($col, $v)) ?: '(untitled)' ?></a><?php else: ?><?= e($fmt($col, $v)) ?><?php endif; ?></td>
<?php $first = false; endforeach; ?>
<td><a href="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>">Edit</a>
<form class="inline" method="post" action="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>/delete" data-confirm="Delete this <?= e($def['singular']) ?>? This cannot be undone."><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="btn danger" type="submit">Delete</button></form></td>
</tr><?php endforeach; ?></tbody></table>
<p class="meta"><?= count($rows) ?> <?= e(strtolower($def['label'])) ?><?= count($rows) >= 3000 ? ' (showing the first 3,000)' : '' ?></p>
<?php else: ?><p>Nothing here yet.</p><?php endif; ?>
