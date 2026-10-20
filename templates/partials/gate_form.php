<?php /** @var string $return @var string $headingTag */ $tag = $headingTag ?? 'h2'; ?>
<section class="gate" aria-labelledby="gate-h">
  <p class="gate-brand"><?= e(setting('site_title')) ?></p>
  <p class="eyebrow"><?= e(setting('tagline')) ?></p>
  <<?= $tag ?> id="gate-h" class="gate-h">Adults only.<br>18+</<?= $tag ?>>
  <p class="lead">This site contains sexually explicit writing and imagery intended for adults. By entering, you confirm that you are 18 years of age or older and wish to view adult content.</p>
  <form method="post" action="/enter">
    <input type="hidden" name="return" value="<?= e($return) ?>">
    <input type="hidden" name="over18" value="1">
    <p class="actions"><button class="btn primary" type="submit" autofocus="autofocus">I am 18 or older — Enter</button> <a class="btn" href="/leave">Leave this site</a></p>
  </form>
  <p class="meta">Your acknowledgment is remembered for this browsing session.</p>
</section>
