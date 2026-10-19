<?php /** @var string $key @var array $def @var array $rows @var string $csrf @var DateTimeZone $tz */
$fmt = function (string $col, $v) use ($tz) {
    if (in_array($col, ['published_at', 'updated_at', 'created_at'], true) && $v) { return (new DateTimeImmutable((string) $v, new DateTimeZone('UTC')))->setTimezone($tz)->format('M j, Y g:i a'); }
    return (string) $v;
}; ?>
<p><a class="btn" href="/admin/<?= e($key) ?>/new">New <?= e($def['singular']) ?></a></p>
<?php if ($rows): ?>
<table data-sortable><thead><tr>
<?php foreach ($def['columns'] as $col => $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach; ?>
<th scope="col" data-nosort>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php $first = true; foreach ($def['columns'] as $col => $label): $v = $r[$col] ?? ''; ?>
<td data-sort="<?= e((string) $v) ?>"><?php if ($first): ?><a href="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>"><?= e($fmt($col, $v)) ?: '(untitled)' ?></a><?php else: ?><?= e($fmt($col, $v)) ?><?php endif; ?></td>
<?php $first = false; endforeach; ?>
<td><a href="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>">Edit</a>
<form class="inline" method="post" action="/admin/<?= e($key) ?>/<?= (int) $r['id'] ?>/delete" data-confirm="Delete this <?= e($def['singular']) ?>? This cannot be undone."><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="danger" type="submit">Delete</button></form></td>
</tr><?php endforeach; ?></tbody></table>
<p><?= count($rows) ?> <?= e(strtolower($def['label'])) ?></p>
<?php else: ?><p>Nothing here yet.</p><?php endif; ?>
