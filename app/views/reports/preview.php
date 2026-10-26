<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$csv = site_url('reports/' . $key) . qs(['format' => 'csv']);
$total = count($report['rows']); $shown = array_slice($report['rows'], 0, 50);
?>
<?php ui_heading($title, $desc, '<a class="btn" href="' . e(site_url('reports')) . '">All reports</a><a class="btn btn-primary" href="' . e($csv) . '">' . icon('download', 14) . ' Download CSV</a>'); ?>

<?php ui_filters_open('reports/' . $key); ?>
  <?php if (in_array('grade', $uses, true)): ?><select class="field" name="grade" aria-label="Grade"><?= ui_options(array_combine(range(7, 12), array_map(function ($g) { return 'Grade ' . $g; }, range(7, 12))), $f['grade'], 'All grades') ?></select><?php endif; ?>
  <?php if (in_array('section_id', $uses, true)): ?><select class="field" name="section" aria-label="Section"><?= ui_sections_options($sections, $f['section_id']) ?></select><?php endif; ?>
  <?php if (in_array('status', $uses, true)): ?><select class="field" name="status" aria-label="Status"><?= ui_options(array_combine($statuses, $statuses), $f['status'], 'Any status') ?></select><?php endif; ?>
  <?php if (in_array('from', $uses, true)): ?><label class="muted small">From <input class="field" type="date" name="from" value="<?= e($f['from']) ?>"></label>
  <label class="muted small">To <input class="field" type="date" name="to" value="<?= e($f['to']) ?>"></label><?php endif; ?>
  <button class="btn btn-primary" type="submit">Apply filters</button>
  <?php if (array_filter($f)): ?><a class="btn" href="<?= e(site_url('reports/' . $key)) ?>">Clear</a><?php endif; ?>
</form>

<?php ui_panel($total . ' row' . ($total === 1 ? '' : 's'), $total > 50 ? 'Showing the first 50 — the CSV has all rows' : ''); ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><?php foreach ($report['headers'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?php foreach ($shown as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
      <?php if (!$shown): ?><tr><td colspan="<?= count($report['headers']) ?>"><?= ui_empty('No rows match these filters.', 'search') ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
<?php ui_panel_end(); ?>
