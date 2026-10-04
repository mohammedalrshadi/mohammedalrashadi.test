<?php
// Expects $relatedItems to be defined in scope as an array of normalized associative arrays
// ['title' => string, 'url' => string, 'image' => string, 'category' => string, 'type_label' => string]

if (!empty($relatedItems)):
?>
<section class="mt-space-2xl pt-space-xl border-t border-border-subtle" aria-label="Continue Exploring">
  <div class="flex flex-col gap-space-md">
    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Continue Exploring</h2>
    <div class="flex flex-col gap-space-sm">
      <?php foreach ($relatedItems as $item): ?>
        <!-- Using native anchor tag as requested in step 4 to avoid div with onclick -->
        <a href="<?= htmlspecialchars($item['url']) ?>" class="card card-interactive p-space-md flex flex-col md:flex-row md:items-center justify-between gap-space-md group text-decoration-none">
          <div class="flex flex-col gap-space-xs max-w-3xl">
            <div class="metadata-row">
              <span class="text-primary font-mono text-xs font-semibold"><?= htmlspecialchars($item['type_label']) ?></span>
              <?php if (!empty($item['category'])): ?>
                <span class="metadata-divider">•</span>
                <span class="uppercase font-mono text-xs text-text-muted"><?= htmlspecialchars($item['category']) ?></span>
              <?php endif; ?>
            </div>
            <h3 class="font-headline-sm text-on-surface group-hover:text-primary transition-colors font-semibold">
              <?= htmlspecialchars($item['title']) ?>
            </h3>
          </div>
          <div class="flex items-center gap-1 text-primary font-mono text-xs self-start md:self-auto shrink-0">
            <span>Explore</span>
            <span class="material-symbols-outlined text-[16px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
