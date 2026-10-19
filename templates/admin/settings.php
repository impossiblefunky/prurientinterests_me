<?php /** @var array $names @var array $values @var array $errors @var string $csrf */ ?>
<?php if ($errors): ?><div class="error" role="alert"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/admin/settings"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<?php foreach ($names as $n => $label): ?><p><label for="<?= e($n) ?>"><?= e($label) ?></label><br><input id="<?= e($n) ?>" name="<?= e($n) ?>" type="text" value="<?= e($values[$n] ?? '') ?>"<?= $n === 'site_title' ? ' required="required"' : '' ?>></p>
<?php endforeach; ?>
<p><button type="submit">Save settings</button></p></form>
