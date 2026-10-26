<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$is_teacher = $user['role'] === 'Teacher';
?>
<?php ui_heading($is_teacher ? 'My students' : 'Students', number_format($total) . ' enrolled learner' . ($total === 1 ? '' : 's') . ($is_teacher ? ' in your classes' : ''),
    $can_export ? '<a class="btn" href="' . e(site_url('students/export' . qs([], ''))) . '">' . icon('download', 14) . ' Export CSV</a>' : ''); ?>

<?php ui_filters_open('students'); ?>
  <label class="search-field"><?= icon('search', 15) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search name, student no. or section" aria-label="Search students"></label>
  <select name="grade" aria-label="Grade" data-autosubmit><?= ui_options(array_combine(range(7, 12), array_map(function ($g) { return 'Grade ' . $g; }, range(7, 12))), $f['grade'], 'All grades') ?></select>
  <select name="section" aria-label="Section" data-autosubmit><?= ui_sections_options($sections, $f['section_id']) ?></select>
  <button class="btn" type="submit">Search</button>
  <?php if ($f['q'] || $f['grade'] || $f['section_id']): ?><a class="btn" href="<?= e(site_url('students')) ?>">Clear</a><?php endif; ?>
</form>

<section class="panel">
  <?php if (!$rows): ?><?= ui_empty('No learners match your filters.', 'users') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Learner</th><th>Section</th><th class="right">Average</th><th>Attendance</th><th class="right">Missing</th><th>Standing</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr>
        <td><?= ui_student($s['full_name'], $s['student_no'], $s['initials'], site_url('students/' . $s['id'])) ?></td>
        <td><?= e($s['section_label']) ?></td>
        <td class="right num"><?= $s['average'] === null ? '—' : e($s['average']) ?></td>
        <td><?= $s['attendance'] === null ? '—' : '<span class="num">' . e($s['attendance']) . '%</span>' ?></td>
        <td class="right num"><?= (int) $s['missing'] ?></td>
        <td><?= badge($s['standing']['label'], standing_tone($s['standing']['label'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($total, $page, $per) ?>
  <?php endif; ?>
</section>
