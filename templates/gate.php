<?php /** @var string $return */ ?>
<section class="gate">
  <h1><?= e(setting('site_title')) ?></h1>
  <p class="lead">This site contains sexually explicit writing and images intended for adults.</p>
  <form method="post" action="/enter">
    <input type="hidden" name="return" value="<?= e($return) ?>">
    <p><label class="check"><input type="checkbox" name="over18" value="1" required="required"> I am 18 or older, and it is legal for me to view adult material where I live.</label></p>
    <p><button type="submit">Enter</button> <a class="btn secondary" href="/leave">Leave</a></p>
  </form>
</section>
