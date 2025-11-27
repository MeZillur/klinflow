<?php
declare(strict_types=1);

namespace Modules\hotelflow\Services;

use PDO;
use DateTimeImmutable;
use Throwable;

final class BiometricService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Queue biometric files for async processing.
     *
     * @param int   $orgId
     * @param int   $guestId
     * @param int   $reservationId
     * @param array $paths ['id_front' => '...', 'id_back' => '...', 'face' => '...']
     */
    public function queueForReservation(
        int $orgId,
        int $guestId,
        int $reservationId,
        array $paths
    ): void {
        // Nothing to queue
        if (!$paths) {
            return;
        }

        $now = new DateTimeImmutable('now');
        $ts  = $now->format('Y-m-d H:i:s');

        // ONE INSERT PER FILE:
        // Table assumed:
        // hms_biometric_logs(
        //   id, org_id, guest_id, reservation_id,
        //   kind, file_path, status, error,
        //   created_at, processed_at
        // )
        $sql = "
            INSERT INTO hms_biometric_logs (
                org_id,
                guest_id,
                reservation_id,
                kind,
                file_path,
                status,
                error,
                created_at,
                processed_at
            ) VALUES (
                :o,
                :guest_id,
                :res_id,
                :kind,
                :file_path,
                :status,
                :error,
                :created_at,
                :processed_at
            )
        ";

        $stmt = $this->pdo->prepare($sql);

        foreach (['id_front', 'id_back', 'face'] as $kind) {
            $path = trim((string)($paths[$kind] ?? ''));
            if ($path === '') {
                continue;
            }

            // ❗ IMPORTANT: placeholders list exactly matches execute() keys
            $stmt->execute([
                ':o'           => $orgId,
                ':guest_id'    => $guestId,
                ':res_id'      => $reservationId,
                ':kind'        => $kind,
                ':file_path'   => $path,
                ':status'      => 'queued',
                ':error'       => null,
                ':created_at'  => $ts,
                ':processed_at'=> null,
            ]);
        }
    }
}