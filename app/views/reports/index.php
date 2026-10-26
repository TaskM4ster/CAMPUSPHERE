<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Reports', 'Filtered summaries for DepEd documentation and administrative review, with one-click CSV export'); ?>
<div class="module-cards">
  <?php foreach ($catalog as $key => $c): ?>
    <article class="module-card">
      <h3><?= e($c[0]) ?></h3><p><?= e($c[1]) ?></p>
      <div class="card-meta"><a class="btn btn-sm btn-primary" href="<?= e(site_url('reports/' . $key)) ?>"><?= icon('chart-no-axes-combined', 13) ?> Open &amp; filter</a>
        <a class="btn btn-sm" href="<?= e(site_url('reports/' . $key . '?format=csv')) ?>"><?= icon('download', 13) ?> CSV</a></div>
    </article>
  <?php endforeach; ?>
</div>
<p class="muted small" style="margin-top:20px">Exports contain personal data. Handle and store them according to your school's data privacy policy; every download is recorded in the audit log.</p>
