<?php /** @var array $p @var bool $isAbout @var bool $hasContact @var bool $hasBooks */ ?>
<article class="reading">
  <?php if ($isAbout): ?><p class="eyebrow">Behind the collection</p><?php endif; ?>
  <h1><?= e($p['title']) ?></h1>
  <div class="prose"><?= prose($p['body_html']) ?></div>
  <?php if ($isAbout): ?>
  <section aria-labelledby="way-h"><h2 id="way-h">Find your way in</h2>
    <p class="actions"><a class="btn" href="/stories">Explore the stories</a><?php if ($hasBooks): ?> <a class="btn" href="/books">Browse the books</a><?php endif; ?></p>
  </section>
  <?php if ($hasContact): ?><section aria-labelledby="touch-h"><h2 id="touch-h">Get in touch</h2><p><a href="/p/contact.html">Contact <?= e(setting('author_name')) ?> &rarr;</a></p></section><?php endif; ?>
  <?php endif; ?>
</article>
