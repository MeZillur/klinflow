<?php
declare(strict_types=1);

namespace Modules\HotelFlow\Controllers;

use PDO;

final class BookingsController extends BaseController
{
    /** GET /bookings */
    public function index(array $ctx): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);
        $st = $pdo->prepare("
            SELECT b.id, b.booking_no, b.checkin_date, b.checkout_date, b.status,
                   c.name AS customer_name, r.room_no
            FROM hms_bookings b
            LEFT JOIN hms_customers c ON c.org_id=b.org_id AND c.id=b.customer_id
            LEFT JOIN hms_rooms r     ON r.org_id=b.org_id AND r.id=b.room_id
            WHERE b.org_id=?
            ORDER BY b.id DESC LIMIT 200
        "); $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('bookings/index', [
            'title' => 'Bookings',
            'rows'  => $rows,
            'active'=> 'bookings',
        ], $ctx);
    }

    /** GET /bookings/create */
    public function create(array $ctx): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);
        $rooms = $pdo->query("SELECT id, room_no, room_type FROM hms_rooms WHERE org_id={$orgId} AND status='available' ORDER BY room_no")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $this->view('bookings/create', [
            'title' => 'Create Booking',
            'rooms' => $rooms,
            'active'=> 'bookings',
        ], $ctx);
    }

    /** POST /bookings */
    public function store(array $ctx): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);

        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $room_id     = (int)($_POST['room_id'] ?? 0);
        $checkin     = (string)($_POST['checkin_date'] ?? date('Y-m-d'));
        $checkout    = (string)($_POST['checkout_date'] ?? date('Y-m-d', strtotime('+1 day')));
        $status      = in_array(($_POST['status'] ?? 'pending'), ['pending','confirmed','checked_in','checked_out','cancelled'], true) ? $_POST['status'] : 'pending';
        $rate        = (float)($_POST['nightly_rate'] ?? 0);
        $notes       = trim((string)($_POST['notes'] ?? ''));

        if ($room_id <= 0 || $customer_id <= 0) $this->abort400('Customer and room are required.');

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("
                INSERT INTO hms_bookings (org_id, booking_no, room_id, customer_id, checkin_date, checkout_date, nightly_rate, status, notes, created_at)
                VALUES (?,?,?,?,?,?,?,?,?, NOW())
            ");
            $booking_no = 'BK-' . date('Ymd') . '-' . substr((string)time(), -5);
            $ins->execute([$orgId, $booking_no, $room_id, $customer_id, $checkin, $checkout, $rate, $status, $notes ?: null]);

            // Optionally block room if confirmed
            if ($status === 'confirmed') {
                $u = $pdo->prepare("UPDATE hms_rooms SET status='reserved', updated_at=NOW() WHERE org_id=? AND id=? AND status IN ('available','reserved')");
                $u->execute([$orgId, $room_id]);
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx).'/bookings');
        } catch (\Throwable $e) {
            $pdo->rollBack(); $this->abort500($e);
        }
    }

    /** GET /bookings/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);
        $h = $pdo->prepare("
            SELECT b.*, c.name AS customer_name, r.room_no, r.room_type
            FROM hms_bookings b
            LEFT JOIN hms_customers c ON c.org_id=b.org_id AND c.id=b.customer_id
            LEFT JOIN hms_rooms r     ON r.org_id=b.org_id AND r.id=b.room_id
            WHERE b.org_id=? AND b.id=?");
        $h->execute([$orgId,$id]);
        $b = $h->fetch(PDO::FETCH_ASSOC);
        if (!$b) $this->abort404('Booking not found.');

        $this->view('bookings/show', [
            'title'   => 'Booking #'.($b['booking_no'] ?? $id),
            'booking' => $b,
            'active'  => 'bookings',
        ], $ctx);
    }

    /** GET /bookings/{id}/edit */
    public function edit(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);
        $h = $pdo->prepare("SELECT * FROM hms_bookings WHERE org_id=? AND id=?");
        $h->execute([$orgId,$id]);
        $b = $h->fetch(PDO::FETCH_ASSOC);
        if (!$b) $this->abort404('Booking not found.');

        $rooms = $pdo->query("SELECT id, room_no, room_type FROM hms_rooms WHERE org_id={$orgId} ORDER BY room_no")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('bookings/edit', [
            'title'   => 'Edit Booking',
            'booking' => $b,
            'rooms'   => $rooms,
            'active'  => 'bookings',
        ], $ctx);
    }

    /** POST /bookings/{id} */
    public function update(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);

        $customer_id = (int)($_POST['customer_id'] ?? 0);
        $room_id     = (int)($_POST['room_id'] ?? 0);
        $checkin     = (string)($_POST['checkin_date'] ?? date('Y-m-d'));
        $checkout    = (string)($_POST['checkout_date'] ?? date('Y-m-d', strtotime('+1 day')));
        $status      = in_array(($_POST['status'] ?? 'pending'), ['pending','confirmed','checked_in','checked_out','cancelled'], true) ? $_POST['status'] : 'pending';
        $rate        = (float)($_POST['nightly_rate'] ?? 0);
        $notes       = trim((string)($_POST['notes'] ?? ''));

        if ($room_id <= 0 || $customer_id <= 0) $this->abort400('Customer and room are required.');

        $u = $pdo->prepare("
            UPDATE hms_bookings
               SET room_id=?, customer_id=?, checkin_date=?, checkout_date=?, nightly_rate=?, status=?, notes=?, updated_at=NOW()
             WHERE org_id=? AND id=?
        ");
        $u->execute([$room_id, $customer_id, $checkin, $checkout, $rate, $status, $notes ?: null, $orgId, $id]);

        $this->redirect($this->moduleBase($ctx).'/bookings/'.$id);
    }

    /** POST /bookings/{id}/delete */
    public function destroy(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);
        $pdo->prepare("DELETE FROM hms_bookings WHERE org_id=? AND id=?")->execute([$orgId, $id]);
        $this->redirect($this->moduleBase($ctx).'/bookings');
    }

    /** GET /bookings/search.json?q=... */
    public function searchJson(array $ctx): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo = $this->pdo(); $orgId = $this->orgId($ctx);
        $q = trim((string)($_GET['q'] ?? ''));
        $like = '%'.$q.'%';
        $st = $pdo->prepare("
            SELECT b.id, b.booking_no, c.name AS customer, r.room_no
            FROM hms_bookings b
            LEFT JOIN hms_customers c ON c.org_id=b.org_id AND c.id=b.customer_id
            LEFT JOIN hms_rooms r     ON r.org_id=b.org_id AND r.id=b.room_id
            WHERE b.org_id=? AND (b.booking_no LIKE ? OR c.name LIKE ? OR r.room_no LIKE ?)
            ORDER BY b.id DESC LIMIT 50
        ");
        $st->execute([$orgId, $like, $like, $like]);
        echo json_encode($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        exit;
    }
}