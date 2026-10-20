<?php /** @var string $key @var array $def @var int $id @var array $values @var array $errors @var string $csrf @var array $revisions @var array $allTags @var string $tz @var array $mergeTargets */
$local = function ($utc) use ($tz) { if (!$utc) { return ''; } return (new DateTimeImmutable((string) $utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($tz))->format('Y-m-d\TH:i'); };
$tzLabel = ['America/New_York' => 'Eastern time'][$tz] ?? $tz;
$field = function (array $f) use ($values, $local, $tzLabel, $allTags): void {
    [$name, $label, $type] = $f; $v = $values[$name] ?? ''; $req = !empty($f['required']);
    $help = isset($f['help']) ? '<small id="h-' . e($name) . '">' . e($f['help']) . '</small>' : ''; $desc = $help ? ' aria-describedby="h-' . e($name) . '"' : ''; ?>
<div class="field">
<?php if ($type === 'checkbox'): ?>
  <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1"<?= $v ? ' checked="checked"' : '' ?>> <?= e($label) ?></label>
<?php else: ?>
  <label for="f-<?= e($name) ?>"><?= e($label) ?><?= $type === 'datetime' ? ' · ' . e($tzLabel) : '' ?><?= $req ? ' <span class="req">(required)</span>' : '' ?></label>
  <?php if ($type === 'text' || $type === 'url'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= $type === 'url' ? 'url' : 'text' ?>" value="<?= e((string) $v) ?>"<?= $req ? ' required="required"' : '' ?><?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $desc ?>>
  <?php elseif ($type === 'number'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="number" step="1" value="<?= e((string) $v) ?>"<?= $desc ?>>
  <?php elseif ($type === 'date'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="date" value="<?= e((string) $v) ?>"<?= $desc ?>>
  <?php elseif ($type === 'datetime'): ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="datetime-local" value="<?= e($local($v)) ?>"<?= $req ? ' required="required"' : '' ?><?= $desc ?>>
  <?php elseif ($type === 'textarea'): ?>
    <textarea id="f-<?= e($name) ?>" name="<?= e($name) ?>" rows="<?= (int) ($f['rows'] ?? 5) ?>"<?= $req ? ' required="required"' : '' ?><?= isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '' ?><?= $desc ?>><?= e((string) $v) ?></textarea>
  <?php elseif ($type === 'html'): ?>
    <div class="toolbar" role="toolbar" aria-label="Formatting for <?= e($label) ?>" data-for="f-<?= e($name) ?>">
      <button type="button" class="btn" data-wrap="strong">Bold</button><button type="button" class="btn" data-wrap="em">Italic</button><button type="button" class="btn" data-wrap="h2">Heading</button><button type="button" class="btn" data-wrap="blockquote">Quote</button><button type="button" class="btn" data-wrap="hr">Divider</button>
    </div>
    <textarea id="f-<?= e($name) ?>" name="<?= e($name) ?>" class="html" rows="<?= $name === 'body_html' ? 24 : 9 ?>"<?= $req ? ' required="required"' : '' ?> aria-describedby="h-<?= e($name) ?>"><?= e((string) $v) ?></textarea>
    <small id="h-<?= e($name) ?>">Paragraphs go in &lt;p&gt; tags; bold, italic, headings, quotes, lists and links are allowed. Anything else is removed when you save.</small>
  <?php elseif ($type === 'select'): ?>
    <select id="f-<?= e($name) ?>" name="<?= e($name) ?>"<?= $req ? ' required="required"' : '' ?><?= $desc ?>><?php foreach ($f['options'] as $ov => $ol): ?><option value="<?= e((string) $ov) ?>"<?= (string) $v === (string) $ov ? ' selected="selected"' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
  <?php elseif ($type === 'image'): ?>
    <?php if ($v): ?><p><img class="thumb" src="/uploads/<?= e((string) $v) ?>" alt="Current image" width="160"></p><?php endif; ?>
    <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" type="file" accept="image/jpeg,image/png,image/webp,image/gif"<?= $req && !$v ? ' required="required"' : '' ?><?= $desc ?>>
    <?php if ($v && !$req): ?><label class="check"><input type="checkbox" name="<?= e($name) ?>_remove" value="1"> Remove this image</label><?php endif; ?>
  <?php elseif ($type === 'tags'): ?>
    <input id="f-<?= e($name) ?>" name="tags" type="text" value="<?= e((string) $v) ?>" list="taglist" autocomplete="off" aria-describedby="h-tags">
    <datalist id="taglist"><?php foreach ($allTags as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?></datalist>
    <small id="h-tags">Separate tags with commas. New tags are created automatically. The same tags are used for stories, images, albums and books.</small>
  <?php elseif ($type === 'aliases'): ?>
    <input id="f-<?= e($name) ?>" name="aliases" type="text" value="<?= e((string) $v) ?>"<?= $desc ?>>
  <?php endif; ?>
  <?= $help && !in_array($type, ['html', 'tags'], true) ? $help : '' ?>
<?php endif; ?>
</div>
<?php };
$main = array_filter($def['fields'], fn($f) => empty($f['panel']));
$panel = array_filter($def['fields'], fn($f) => !empty($f['panel']));
$viewUrl = $id && $key === 'stories' ? story_url(['slug' => $values['slug'] ?? '', 'legacy_path' => $values['legacy_path'] ?? '']) : null; ?>
<p class="eyebrow"><?= e(strtoupper($def['label'])) ?></p>
<?php if ($errors): ?><div class="error" role="alert"><p>Please fix the following:</p><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/admin/<?= e($key) ?>/<?= $id ?: 'new' ?>" enctype="multipart/form-data" data-validate>
<input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<div class="editor<?= $panel ? ' has-panel' : '' ?>">
  <div class="main-col"><?php foreach ($main as $f) { $field($f); } ?></div>
  <?php if ($panel): ?><aside class="panel-col" aria-label="Publication"><h2>Publication</h2><?php foreach ($panel as $f) { $field($f); } ?>
    <?php if ($revisions): ?><h2>Revision history</h2><p class="meta"><?= count($revisions) ?> earlier version<?= count($revisions) === 1 ? '' : 's' ?> saved. See below.</p><?php elseif ($key === 'stories'): ?><h2>Revision history</h2><p class="meta">Saved versions will appear here after you edit.</p><?php endif; ?>
  </aside><?php endif; ?>
</div>
<p class="actions"><button class="btn primary" type="submit">Save</button> <a class="btn" href="/admin/<?= e($key) ?>">Cancel</a><?php if ($viewUrl): ?> <a class="btn" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener"><?= ext_label('Preview layout') ?></a><?php endif; ?></p>
</form>
<?php if ($id && !empty($def['merge'])): ?>
<section class="boxed"><h2>Merge duplicates</h2>
<p class="meta">Moves everything with this tag onto another tag and keeps this tag's address working through a redirect. This cannot be undone.</p>
<form method="post" action="/admin/<?= e($key) ?>/<?= $id ?>/merge" data-confirm="Merge this tag into the chosen one? This cannot be undone."><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<div class="field"><label for="target_id">Merge into</label><select id="target_id" name="target_id" required="required"><?php foreach ($mergeTargets as $tid => $tn): ?><option value="<?= e((string) $tid) ?>"><?= e($tn) ?></option><?php endforeach; ?></select></div>
<p><button class="btn danger" type="submit">Merge into this tag</button></p></form></section>
<?php endif; ?>
<?php if ($revisions): ?>
<h2>Earlier versions</h2>
<table data-sortable><thead><tr><th scope="col">Saved</th><th scope="col">Title</th><th scope="col" data-nosort>Actions</th></tr></thead><tbody>
<?php foreach ($revisions as $r): ?><tr><td data-sort="<?= e($r['saved_at']) ?>"><?= e($r['saved_at']) ?> UTC</td><td><?= e($r['title']) ?></td>
<td><form class="inline" method="post" action="/admin/stories/<?= $id ?>/restore/<?= (int) $r['id'] ?>" data-confirm="Replace the current text with this version? The current text is kept as a version too."><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="btn" type="submit">Restore</button></form></td></tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
