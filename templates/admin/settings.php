<?php /** @var array $names @var array $values @var array $errors @var string $csrf */ ?>
<?php if ($errors): ?><div class="error" role="alert"><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/admin/settings"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
<?php foreach ($names as $n => $label): ?><div class="field"><label for="<?= e($n) ?>"><?= e($label) ?><?= $n === 'site_title' ? ' <span class="req">(required)</span>' : '' ?></label>
<?php if ($n === 'turnstile_secret_key'): ?><input id="<?= e($n) ?>" name="<?= e($n) ?>" type="password" autocomplete="off" value=""><?php if (!empty($values['turnstile_secret_saved'])): ?><p class="meta">A secret key is saved. Leave this empty to keep it.</p><?php endif; ?>
<?php elseif ($n === 'home_intro'): ?><textarea id="<?= e($n) ?>" name="<?= e($n) ?>" rows="3"><?= e($values[$n] ?? '') ?></textarea>
<?php else: ?><input id="<?= e($n) ?>" name="<?= e($n) ?>" type="text" value="<?= e($values[$n] ?? '') ?>"<?= $n === 'site_title' ? ' required="required"' : '' ?>><?php endif; ?></div>
<?php endforeach; ?>
<p><button class="btn primary" type="submit">Save settings</button></p></form>
