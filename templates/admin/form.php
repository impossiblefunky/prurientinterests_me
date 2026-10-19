<?php /** @var string $key @var array $def @var int $id @var array $values @var array $errors @var string $csrf @var array $revisions @var array $allTags @var string $tz */
$local = function ($utc) use ($tz) { if (!$utc) { return ''; } return (new DateTimeImmutable((string) $utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($tz))->format('Y-m-d\TH:i'); }; ?>
<?php if ($errors): ?><div class="error" role="alert"><p>Please fix the following:</p><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/admin/<?= e($key) ?>/<?= $id ?: 'new' ?>" enctype="multipart/form-data" data-validate>
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<?php foreach ($def['fields'] as $f): [$name, $label, $type] = $f; $v = $values[$name] ?? ''; $req = !empty($f['required']);
$help = isset($f['help']) ? '<small id="h-' . e($name) . '">' . e($f['help']) . '</small>' : ''; $desc = $help ? ' aria-describedby="h-' . e($name) . '"' : ''; ?>
<div class="field">
<?php if ($type === 'checkbox'): ?>
  <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= $v ? ' checked="checked"' : '' ?>> <?= e($label) ?></label>
<?php else: ?>
  <label for="f-<?= e($name) ?>"><?= e($label) ?><?= $req ? ' <span class="req">(required)</span>' : '' ?></label>
  <?php if ($type === 'text' || $type === 'url'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= $type === 'url' ? 'url' : 'text' ?>" value="<?= e((string) $v) ?>"<?= $req ? ' required="required"' : '' ?><?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $desc ?>>
  <?php elseif ($type === 'number'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="number" step="1" value="<?= e((string) $v) ?>"<?= $desc ?>>
  <?php elseif ($type === 'date'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="date" value="<?= e((string) $v) ?>"<?= $desc ?>>
  <?php elseif ($type === 'datetime'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="datetime-local" value="<?= e($local($v)) ?>"<?= $req ? ' required="required"' : '' ?><?= $desc ?>><small>Time zone: <?= e($tz) ?></small>
  <?php elseif ($type === 'textarea'): ?>
    <textarea id="f-<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= (int) ($f['rows'] ?? 5) ?>"<?= $req ? ' required="required"' : '' ?><?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $desc ?>><?= e((string) $v) ?></textarea>
  <?php elseif ($type === 'html'): ?>
    <div class="toolbar" role="toolbar" aria-label="Formatting for <?= e($label) ?>" data-for="f-<?= e($name) ?>">
      <button type="button" data-wrap="strong">Bold</button><button type="button" data-wrap="em">Italic</button><button type="button" data-wrap="h2">Heading</button><button type="button" data-wrap="blockquote">Quote</button><button type="button" data-wrap="hr">Divider</button>
    </div>
    <textarea id="f-<?= e($name) ?>" name="<?= e($name) ?>" class="html" rows="26"<?= $req ? ' required="required"' : '' ?> aria-describedby="h-<?= e($name) ?>"><?= e((string) $v) ?></textarea>
    <small id="h-<?= e($name) ?>">Simple HTML: paragraphs in &lt;p&gt;, plus bold, italic, headings, quotes, lists and links. Anything else is stripped when you save.</small>
  <?php elseif ($type === 'select'): ?>
    <select id="f-<?= e($name) ?>" name="<?= e($name) ?>"<?= $desc ?>><?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e((string) $ov) ?>"<?= (string) $v === (string) $ov ? ' selected="selected"' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
  <?php elseif ($type === 'image'): ?>
    <?php if ($v): ?><p><img class="thumb" src="/uploads/<?= e((string) $v) ?>" alt="Current image" width="160"></p><?php endif; ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="file" accept="image/jpeg,image/png,image/webp,image/gif"<?= $req && !$v ? ' required="required"' : '' ?><?= $desc ?>>
    <?php if ($v && !$req): ?><label class="check"><input type="checkbox" name="<?= e($name) ?>_remove" value="1"> Remove this image</label><?php endif; ?>
  <?php elseif ($type === 'tags'): ?>
    <input id="f-<?= e($name) ?>" name="tags" type="text" value="<?= e((string) $v) ?>" list="taglist" autocomplete="off" aria-describedby="h-tags">
    <datalist id="taglist"><?php foreach ($allTags as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
    <small id="h-tags">Separate tags with commas. New tags are created automatically.</small>
  <?php endif; ?>
  <?= $help && !in_array($type, ['html', 'tags'], true) ? $help : '' ?>
<?php endif; ?>
</div>
<?php endforeach; ?>
<p><button type="submit">Save</button> <a class="btn secondary" href="/admin/<?= e($key) ?>">Cancel</a></p>
</form>
<?php if ($id && $key === 'stories'): ?>
<p><a href="<?= e(story_url(['slug' => $values['slug'] ?? '', 'legacy_path' => $values['legacy_path'] ?? ''])) ?>" target="_blank" rel="noopener"><span class="nowrap">View on site<span class="sr-only"> (opens in a new tab)</span></span></a></p>
<?php endif; ?>
<?php if ($revisions): ?>
<h2>Earlier versions</h2>
<table data-sortable><thead><tr><th scope="col">Saved</th><th scope="col">Title</th><th scope="col" data-nosort>Actions</th></tr></thead><tbody>
<?php foreach ($revisions as $r): ?><tr><td data-sort="<?= e($r['saved_at']) ?>"><?= e($r['saved_at']) ?> UTC</td><td><?= e($r['title']) ?></td>
<td><form class="inline" method="post" action="/admin/stories/<?= $id ?>/restore/<?= (int) $r['id'] ?>" data-confirm="Replace the current text with this version? The current text is kept as a version too."><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="secondary" type="submit">Restore</button></form></td></tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
