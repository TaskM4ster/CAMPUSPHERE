<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Notifications - role/section/student targeted notices with read state and announcements. */
class Notifications extends Base_controller
{
    public function index()
    {
        $cat = $this->query('category');
        if ($cat !== '' && !in_array($cat, Notice_model::CATEGORIES, true)) { $cat = ''; }
        $feed = $this->notices->feed($this->user, $cat ?: null, 200);
        foreach ($feed as &$n) { $n['audience'] = $this->notices->audience_label($n); }
        unset($n);
        $can_post = in_array($this->user['role'], ['Administrator', 'Teacher'], true);
        $sections = $this->section_options();
        if ($this->user['role'] === 'Teacher') {
            $ids = $this->teachers->section_ids($this->user['teacher_id']);
            $sections = array_values(array_filter($sections, function ($s) use ($ids) { return in_array((int) $s['id'], $ids, true); }));
        }
        $this->render('notifications/index', [
            'feed' => $feed, 'category' => $cat, 'categories' => Notice_model::CATEGORIES,
            'can_post' => $can_post, 'sections' => $sections, 'is_admin' => $this->user['role'] === 'Administrator',
        ], 'Notifications');
    }

    public function read($id)
    {
        $this->notices->mark_read($this->user, (int) $id);
        go('notifications' . ($this->post('category') ? '?category=' . urlencode($this->post('category')) : ''));
    }

    public function read_all()
    {
        $n = $this->notices->mark_all_read($this->user);
        $this->flash('success', $n ? "{$n} notification" . ($n === 1 ? '' : 's') . ' marked as read.' : 'You are all caught up.');
        go('notifications');
    }

    public function announce()
    {
        $this->require_role(['Administrator', 'Teacher']);
        $title = $this->post('title'); $body = $this->post('body'); $cat = $this->post('category');
        $audience = $this->post('audience', 'all');
        $keep = ['title', 'body', 'category', 'audience'];
        if ($title === '' || mb_strlen($title) > 160) { $this->respond(['ok' => false, 'error' => 'A title is required (max 160 characters).'], 'notifications', $keep); }
        if ($body === '' || mb_strlen($body) > 2000) { $this->respond(['ok' => false, 'error' => 'A message is required (max 2,000 characters).'], 'notifications', $keep); }
        if (!in_array($cat, Notice_model::CATEGORIES, true)) { $cat = 'Announcement'; }

        $aud = [];
        if (strpos($audience, 'section:') === 0) {
            $sid = (int) substr($audience, 8);
            if (!$this->school->section($sid)) { $this->respond(['ok' => false, 'error' => 'Choose a valid audience.'], 'notifications', $keep); }
            if ($this->user['role'] === 'Teacher' && !$this->teachers->has_section($this->user['teacher_id'], $sid)) { $this->forbidden(); }
            $aud = ['section_id' => $sid, 'roles' => ['Student', 'Teacher']];
        } elseif (strpos($audience, 'role:') === 0) {
            $role = substr($audience, 5);
            if ($this->user['role'] !== 'Administrator') { $this->forbidden(); }
            if (!in_array($role, User_model::ROLES, true)) { $this->respond(['ok' => false, 'error' => 'Choose a valid audience.'], 'notifications', $keep); }
            $aud = ['roles' => [$role]];
        } elseif ($audience === 'all') {
            if ($this->user['role'] !== 'Administrator') { $this->forbidden(); }
        } else {
            $this->respond(['ok' => false, 'error' => 'Choose a valid audience.'], 'notifications', $keep);
        }
        $id = $this->notices->publish($title, $body, $cat, $aud, $this->user['id']);
        $this->audit('notice.publish', 'notice', $id, $title);
        $this->respond(['ok' => true, 'message' => 'Announcement published.'], 'notifications');
    }
}
