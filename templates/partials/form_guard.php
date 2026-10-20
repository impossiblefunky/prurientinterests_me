<?php /* Hidden protection fields shared by the contact and comment forms. */ use App\Web\Forms; ?>
<div class="hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label></div>
<input type="hidden" name="form_token" value="<?= e(Forms::token()) ?>">
<?php if (Forms::turnstileOn()): ?>
<div class="turnstile-wrap"><div class="cf-turnstile" data-sitekey="<?= e(Forms::turnstileKey()) ?>" data-theme="dark"></div>
<noscript><p class="meta">This check needs JavaScript. Please turn it on to send the form.</p></noscript></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
