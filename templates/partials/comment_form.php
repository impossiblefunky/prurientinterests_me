<?php /** @var array $s @var array $form */
$old = $form['old'] ?? []; $errors = $form['errors'] ?? []; ?>
<div class="comment-form"><h3 id="leave-h">Leave a comment</h3>
<?php if (!empty($_GET['comment'])): ?><p class="notice" role="status">Thank you. Your comment is waiting for approval and will appear once it has been read.</p><?php endif; ?>
<?php if ($errors): ?><div class="notice bad" role="alert"><p>Please fix this:</p><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/comment" novalidate="novalidate" class="stack" aria-labelledby="leave-h">
  <input type="hidden" name="story_id" value="<?= (int) $s['id'] ?>">
  <div class="field"><label for="m-name">Name <span class="meta">(optional)</span></label>
    <input id="m-name" name="name" type="text" maxlength="80" autocomplete="nickname" value="<?= e($old['name'] ?? '') ?>"></div>
  <div class="field"><label for="m-email">Email <span class="meta">(optional, never shown)</span></label>
    <input id="m-email" name="email" type="email" minlength="5" maxlength="190" pattern="[^@\s]+@[^@\s]+\.[^@\s]+" autocomplete="email" value="<?= e($old['email'] ?? '') ?>"></div>
  <div class="field"><label for="m-body">Comment <span class="req">(required)</span></label>
    <textarea id="m-body" name="comment" rows="5" required="required" minlength="2" maxlength="3000"><?= e($old['comment'] ?? '') ?></textarea></div>
  <?php include __DIR__ . '/form_guard.php'; ?>
  <p><button class="btn primary" type="submit">Post comment</button></p>
  <p class="meta">Comments are read before they appear. Plain text only, and no more than two links.</p>
</form></div>
