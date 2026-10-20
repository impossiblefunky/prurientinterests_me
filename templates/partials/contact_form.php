<?php /** @var array $form */
$old = $form['old'] ?? []; $errors = $form['errors'] ?? []; ?>
<section class="contact-form" aria-labelledby="contact-h"><h2 id="contact-h">Send a message</h2>
<?php if (!empty($_GET['sent'])): ?><p class="notice" role="status">Thank you. Your message was sent, and I will reply when I can.</p><?php endif; ?>
<?php if ($errors): ?><div class="notice bad" role="alert"><p>Please fix this:</p><ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/contact" novalidate="novalidate" class="stack">
  <div class="field"><label for="c-name">Your name <span class="req">(required)</span></label>
    <input id="c-name" name="name" type="text" required="required" maxlength="120" autocomplete="name" value="<?= e($old['name'] ?? '') ?>"></div>
  <div class="field"><label for="c-email">Your email <span class="req">(required, so I can reply)</span></label>
    <input id="c-email" name="email" type="email" required="required" minlength="5" maxlength="190" pattern="[^@\s]+@[^@\s]+\.[^@\s]+" autocomplete="email" value="<?= e($old['email'] ?? '') ?>"></div>
  <div class="field"><label for="c-msg">Message <span class="req">(required)</span></label>
    <textarea id="c-msg" name="message" rows="7" required="required" minlength="10" maxlength="5000"><?= e($old['message'] ?? '') ?></textarea></div>
  <?php include __DIR__ . '/form_guard.php'; ?>
  <p><button class="btn primary" type="submit">Send message</button></p>
  <p class="meta">Your email is only used to answer you. It is never shown on the site.</p>
</form></section>
