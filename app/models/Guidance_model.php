<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/** Guidance_model - support notes and intervention timeline for authorized personnel. */
class Guidance_model extends Campus_model
{
    public function notes($student_id, $limit = 20)
    {
        return $this->rows(
            'SELECT * FROM guidance_notes WHERE student_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, (int) $limit),
            [(int) $student_id]
        );
    }

    const KINDS = ['Note', 'Commendation', 'Parent consultation', 'Support case'];

    public function add_note(array $user, $student_id, $note, $kind = 'Note')
    {
        if (!in_array($kind, self::KINDS, true)) { $kind = 'Note'; }
        $note = trim((string) $note);
        if ($note === '') { return $this->fail('Write a short, factual support note.'); }
        if (mb_strlen($note) > 2000) { return $this->fail('The note is too long (max 2,000 characters).'); }
        $id = $this->insert_row('guidance_notes', [
            'student_id' => (int) $student_id, 'author_id' => $user['id'], 'author_name' => $user['full_name'],
            'kind' => $kind, 'note' => $note, 'created_at' => now(),
        ]);
        return $this->ok($kind === 'Note' ? 'Support note saved.' : $kind . ' saved.', ['id' => $id]);
    }

    public function interventions($student_id)
    {
        return $this->rows('SELECT * FROM interventions WHERE student_id = ? ORDER BY due_date, id', [(int) $student_id]);
    }

    public function add_intervention(array $user, $student_id, $title, $detail, $due)
    {
        $title = trim((string) $title);
        if ($title === '') { return $this->fail('Give the intervention a short title.'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $due) || !strtotime($due)) { return $this->fail('Choose a valid due date.'); }
        $id = $this->insert_row('interventions', [
            'student_id' => (int) $student_id, 'title' => mb_substr($title, 0, 160), 'detail' => mb_substr(trim((string) $detail), 0, 255) ?: null,
            'due_date' => $due, 'created_by' => $user['id'], 'created_at' => now(),
        ]);
        return $this->ok('Intervention added.', ['id' => $id]);
    }

    public function set_done($id, $done)
    {
        $this->run('UPDATE interventions SET is_done = ? WHERE id = ?', [$done ? 1 : 0, (int) $id]);
        return $this->val('SELECT student_id FROM interventions WHERE id = ?', [(int) $id]);
    }

    public function open_count()
    {
        return (int) $this->val('SELECT COUNT(*) FROM interventions WHERE is_done = 0');
    }

    /** Latest recorded commendation-style/guidance facts are not modelled; counts only. */
    public function students_with_notes()
    {
        return (int) $this->val('SELECT COUNT(DISTINCT student_id) FROM guidance_notes');
    }
}
