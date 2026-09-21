<?php
/**
 * Schemes, enquiries, appointments, feedback, metal rates, visit counter,
 * quick-contact config. Shared by the storefront and the admin panel.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/workflow.php';

/* ==================== METAL RATES ==================== */

function getLatestRates() {
    try {
        $rows = getDB()->query(
            'SELECT m.* FROM metal_rates m
             JOIN (SELECT metal, MAX(effective_date) d FROM metal_rates GROUP BY metal) x
               ON x.metal = m.metal AND x.d = m.effective_date
             ORDER BY FIELD(m.metal, "gold_24k","gold_22k","gold_18k","silver"), m.metal'
        )->fetchAll();
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function getRateHistory($limit = 60) {
    $stmt = getDB()->prepare('SELECT * FROM metal_rates ORDER BY effective_date DESC, id DESC LIMIT ' . (int) $limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

function saveRate($metal, $label, $rate, $date, $unit = 'gram') {
    $stmt = getDB()->prepare(
        'INSERT INTO metal_rates (metal, label, rate_per_gram, unit, effective_date)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), rate_per_gram = VALUES(rate_per_gram), unit = VALUES(unit)'
    );
    $stmt->execute([$metal, $label, (float) $rate, $unit, $date]);
}

/* ==================== VISIT COUNTER ==================== */

/**
 * Count one visit per browser session per day. Returns the running total.
 */
function record_visit() {
    if (empty($_SESSION['visit_counted_on']) || $_SESSION['visit_counted_on'] !== date('Y-m-d')) {
        try {
            getDB()->prepare(
                'INSERT INTO site_visits (visit_date, hits) VALUES (CURDATE(), 1)
                 ON DUPLICATE KEY UPDATE hits = hits + 1'
            )->execute();
            $_SESSION['visit_counted_on'] = date('Y-m-d');
        } catch (Throwable $e) {
            // ignore
        }
    }
}

function visit_totals() {
    try {
        $db = getDB();
        return [
            'total' => (int) $db->query('SELECT COALESCE(SUM(hits),0) FROM site_visits')->fetchColumn(),
            'today' => (int) $db->query('SELECT COALESCE(hits,0) FROM site_visits WHERE visit_date = CURDATE()')->fetchColumn(),
        ];
    } catch (Throwable $e) {
        return ['total' => 0, 'today' => 0];
    }
}

/* ==================== QUICK CONTACT ==================== */

function quick_contact() {
    $waNumber = preg_replace('/\D+/', '', (string) get_setting('whatsapp_number', ''));
    $waMsg = get_setting('whatsapp_message', 'Hello!');
    return [
        'enabled'   => get_setting('quick_contact_enabled', '1') === '1',
        'whatsapp'  => $waNumber ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waMsg) : '',
        'call'      => get_setting('call_number', SITE_PHONE),
        'call_href' => 'tel:' . preg_replace('/[^\d+]/', '', (string) get_setting('call_number', SITE_PHONE)),
        'instagram' => get_setting('instagram_url', SOCIAL_INSTAGRAM),
    ];
}

function digigold_config() {
    return [
        'enabled' => get_setting('digigold_enabled', '1') === '1',
        'blurb'   => get_setting('digigold_blurb', ''),
        'ios'     => trim((string) get_setting('app_ios_url', '')),
        'android' => trim((string) get_setting('app_android_url', '')),
    ];
}

/* ==================== SCHEMES ==================== */

function getSchemeCategories($onlyActive = true) {
    $sql = 'SELECT * FROM scheme_categories';
    if ($onlyActive) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY sort_order, name';
    try {
        return getDB()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getSchemeCategory($id) {
    $stmt = getDB()->prepare('SELECT * FROM scheme_categories WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

function getSchemeCategoryBySlug($slug) {
    $stmt = getDB()->prepare('SELECT * FROM scheme_categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function getSchemes($filters = []) {
    $where = [];
    $params = [];
    if (($filters['status'] ?? 'active') !== 'any') {
        $where[] = 's.status = ?';
        $params[] = $filters['status'] ?? 'active';
    }
    if (!empty($filters['category_id'])) {
        $where[] = 's.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    $sql = 'SELECT s.*, c.name AS category_name, c.slug AS category_slug
            FROM schemes s LEFT JOIN scheme_categories c ON c.id = s.category_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    /* Group by the category order the admin set, then by the scheme's own
       order inside it. Sorting on s.sort_order alone ignored the category
       sort_order entirely, so re-ordering categories in the admin panel
       changed nothing on the storefront. Schemes with no category sort
       last rather than jumping to the front on a NULL. */
    $sql .= ' ORDER BY (c.sort_order IS NULL), c.sort_order, c.name, s.sort_order, s.name';
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function getScheme($id) {
    $stmt = getDB()->prepare('SELECT s.*, c.name AS category_name, c.slug AS category_slug FROM schemes s LEFT JOIN scheme_categories c ON c.id = s.category_id WHERE s.id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

function getSchemeBySlug($slug) {
    $stmt = getDB()->prepare('SELECT s.*, c.name AS category_name, c.slug AS category_slug FROM schemes s LEFT JOIN scheme_categories c ON c.id = s.category_id WHERE s.slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/* ==================== ENQUIRIES ==================== */

function createEnquiry(array $d) {
    $stmt = getDB()->prepare(
        'INSERT INTO enquiries (product_id, scheme_id, user_id, name, email, phone, subject, message)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $d['product_id'] ?? null,
        $d['scheme_id'] ?? null,
        $d['user_id'] ?? null,
        $d['name'],
        $d['email'] ?? null,
        $d['phone'],
        $d['subject'] ?? null,
        $d['message'] ?? null,
    ]);
    return (int) getDB()->lastInsertId();
}

function getEnquiries($status = null) {
    $sql = 'SELECT e.*, p.name AS product_name, s.name AS scheme_name
            FROM enquiries e
            LEFT JOIN products p ON p.id = e.product_id
            LEFT JOIN schemes s ON s.id = e.scheme_id';
    $params = [];
    if ($status && $status !== 'all') {
        $sql .= ' WHERE e.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY e.created_at DESC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateEnquiry($id, $status, $note = null) {
    $db = getDB();
    $row = $db->prepare('SELECT status FROM enquiries WHERE id = ?');
    $row->execute([(int) $id]);
    $current = $row->fetchColumn();
    if ($current === false) { return false; }

    /* An empty status means "only the note changed" — the dropdown's first
       option posts that. Anything else has to be a legal next step; see
       includes/workflow.php. */
    if ($status !== null && $status !== '' && !workflow_allows('enquiry', $current, $status)) {
        return false;
    }
    $status = ($status === null || $status === '') ? $current : $status;

    $db->prepare('UPDATE enquiries SET status = ?, admin_note = ? WHERE id = ?')
       ->execute([$status, $note, (int) $id]);
    return true;
}

/* ==================== APPOINTMENTS ==================== */

function appointment_time_slots() {
    return array_values(array_filter(array_map('trim', explode(',', (string) get_setting('appointment_times', '')))));
}

function createAppointment(array $d) {
    $stmt = getDB()->prepare(
        'INSERT INTO appointments (user_id, name, email, phone, appointment_date, appointment_time, purpose, message, items_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $d['user_id'] ?? null,
        $d['name'],
        $d['email'] ?? null,
        $d['phone'],
        $d['appointment_date'],
        $d['appointment_time'],
        $d['purpose'] ?? null,
        $d['message'] ?? null,
        !empty($d['items']) ? json_encode(array_values($d['items'])) : null,
    ]);
    return (int) getDB()->lastInsertId();
}

function getAppointments($status = null) {
    $sql = 'SELECT a.*, u.membership_no FROM appointments a LEFT JOIN users u ON u.id = a.user_id';
    $params = [];
    if ($status && $status !== 'all') {
        $sql .= ' WHERE a.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY a.appointment_date DESC, a.appointment_time DESC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAppointment($id) {
    $stmt = getDB()->prepare('SELECT a.*, u.membership_no, u.email AS user_email FROM appointments a LEFT JOIN users u ON u.id = a.user_id WHERE a.id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    if ($row && $row['items_json']) {
        $row['items'] = json_decode($row['items_json'], true) ?: [];
    } else if ($row) {
        $row['items'] = [];
    }
    return $row ?: null;
}

function getUserAppointments($userId) {
    $stmt = getDB()->prepare('SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC');
    $stmt->execute([(int) $userId]);
    return $stmt->fetchAll();
}

function updateAppointment($id, $status, $note = null) {
    $db = getDB();
    $row = $db->prepare('SELECT status FROM appointments WHERE id = ?');
    $row->execute([(int) $id]);
    $current = $row->fetchColumn();
    if ($current === false) { return false; }

    if ($status !== null && $status !== '' && !workflow_allows('appointment', $current, $status)) {
        return false;
    }
    $status = ($status === null || $status === '') ? $current : $status;

    $db->prepare('UPDATE appointments SET status = ?, admin_note = ? WHERE id = ?')
       ->execute([$status, $note, (int) $id]);
    return true;
}

/* ==================== FEEDBACK ==================== */

const FEEDBACK_CATEGORIES = [
    'service'         => 'Store Service',
    'product_quality' => 'Product Quality',
    'website'         => 'Website Experience',
    'pricing'         => 'Pricing',
    'other'           => 'Other',
];

function createFeedback(array $d) {
    $stmt = getDB()->prepare(
        'INSERT INTO feedback (user_id, name, email, phone, category, rating, message) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $d['user_id'] ?? null,
        $d['name'],
        $d['email'] ?? null,
        $d['phone'] ?? null,
        array_key_exists($d['category'] ?? '', FEEDBACK_CATEGORIES) ? $d['category'] : 'other',
        !empty($d['rating']) ? max(1, min(5, (int) $d['rating'])) : null,
        $d['message'],
    ]);
    return (int) getDB()->lastInsertId();
}

function getFeedback($status = null) {
    $sql = 'SELECT * FROM feedback';
    $params = [];
    if ($status && $status !== 'all') {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY created_at DESC';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function updateFeedbackStatus($id, $status) {
    $db = getDB();
    $row = $db->prepare('SELECT status FROM feedback WHERE id = ?');
    $row->execute([(int) $id]);
    $current = $row->fetchColumn();
    if ($current === false) { return false; }

    if ($status === null || $status === '') { return true; }          // nothing asked for
    if (!workflow_allows('feedback', $current, $status)) { return false; }

    $db->prepare('UPDATE feedback SET status = ? WHERE id = ?')->execute([$status, (int) $id]);
    return true;
}

/* ==================== MEMBERSHIP ==================== */

function membership_number_for($userId) {
    return 'AJM' . str_pad((string) $userId, 6, '0', STR_PAD_LEFT);
}
