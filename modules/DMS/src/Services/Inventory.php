<?php
namespace Modules\DMS\Services;

use PDO;

final class Inventory {
  public static function post(
      PDO $pdo, int $orgId, int $productId,
      string $type, float $in, float $out,
      string $note='', ?string $refType=null, ?int $refId=null
  ): void {
    $pdo->prepare("
      INSERT INTO dms_inventory_moves
        (org_id, product_id, move_type, in_qty, out_qty, note, ref_type, ref_id, created_at)
      VALUES (?,?,?,?,?,?,?, ?, NOW())
    ")->execute([$orgId,$productId,$type,$in,$out,$note,$refType,$refId]);
  }
}