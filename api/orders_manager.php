<?php
require_once __DIR__ . "/../config/gateway_guard.php";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/activity.php';

$action  = $_GET['action']  ?? 'export';  // export | delete
$adminId = (int)($_GET['admin_id'] ?? 0); // 0 = all

// ── Helper: fetch orders ──
function fetchOrders(PDO $conn, int $adminId): array {
    if ($adminId > 0) {
        $s = $conn->prepare("
            SELECT o.receipt_id, o.status, o.item_title, o.variant_label, o.suboption,
                   o.price, o.created_at,
                   u.username AS buyer, u.uid AS buyer_uid,
                   a.username AS admin_name
            FROM orders o
            JOIN users u ON u.id = o.user_id
            JOIN users a ON a.id = o.admin_id
            WHERE o.admin_id = :aid
            ORDER BY o.created_at DESC
        ");
        $s->execute([':aid' => $adminId]);
    } else {
        $s = $conn->query("
            SELECT o.receipt_id, o.status, o.item_title, o.variant_label, o.suboption,
                   o.price, o.created_at,
                   u.username AS buyer, u.uid AS buyer_uid,
                   a.username AS admin_name
            FROM orders o
            JOIN users u ON u.id = o.user_id
            JOIN users a ON a.id = o.admin_id
            ORDER BY o.created_at DESC
        ");
    }
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// ── XLSX builder (pure PHP, no libraries needed) ──
function buildXlsx(array $rows): string {
    $headers = ['Receipt ID','Status','Admin','Buyer','Buyer UID','Item','Variant','Sub-option','Price (₱)','Date'];

    // Escape XML special chars
    $x = fn($v) => htmlspecialchars((string)$v, ENT_XML1, 'UTF-8');

    // Build shared strings
    $strings = [];
    $si = function(string $v) use (&$strings): int {
        $k = array_search($v, $strings, true);
        if ($k === false) { $strings[] = $v; $k = count($strings)-1; }
        return $k;
    };

    // Header row
    $headerCols = $headers;
    $dataRows   = [];
    foreach ($rows as $r) {
        $dataRows[] = [
            $r['receipt_id'],
            strtoupper($r['status']),
            $r['admin_name'],
            $r['buyer'],
            $r['buyer_uid'] ?? '',
            $r['item_title'],
            $r['variant_label'],
            $r['suboption'],
            (float)$r['price'],
            $r['created_at'],
        ];
    }

    // Sheet XML
    $rowXml  = '';
    $rowNum  = 1;

    // Header
    $cells = '';
    foreach ($headerCols as $ci => $h) {
        $col = chr(65 + $ci);
        $sid = $si($h);
        $cells .= "<c r=\"{$col}{$rowNum}\" t=\"s\"><v>{$sid}</v></c>";
    }
    $rowXml .= "<row r=\"{$rowNum}\">{$cells}</row>";
    $rowNum++;

    foreach ($dataRows as $dr) {
        $cells = '';
        foreach ($dr as $ci => $val) {
            $col = chr(65 + $ci);
            if ($ci === 8) { // price — number
                $cells .= "<c r=\"{$col}{$rowNum}\"><v>{$val}</v></c>";
            } else {
                $sid = $si((string)$val);
                $cells .= "<c r=\"{$col}{$rowNum}\" t=\"s\"><v>{$sid}</v></c>";
            }
        }
        $rowXml .= "<row r=\"{$rowNum}\">{$cells}</row>";
        $rowNum++;
    }

    $lastCol = chr(64 + count($headers));
    $lastRow = $rowNum - 1;

    $sheetXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>{$rowXml}</sheetData>
</worksheet>
XML;

    // Shared strings XML
    $ssEntries = '';
    foreach ($strings as $s) {
        $ssEntries .= '<si><t>' . $x($s) . '</t></si>';
    }
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
           . ' count="' . count($strings) . '" uniqueCount="' . count($strings) . '">'
           . $ssEntries . '</sst>';

    $wbXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
           . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
           . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
           . '<sheets><sheet name="Orders" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    // Build ZIP in memory
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml',          $contentTypes);
    $zip->addFromString('_rels/.rels',                  $rootRels);
    $zip->addFromString('xl/workbook.xml',              $wbXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels',   $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',     $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml',         $ssXml);
    $zip->close();

    $data = file_get_contents($tmp);
    unlink($tmp);
    return $data;
}

// ── EXPORT ──
if ($action === 'export') {
    $orders = fetchOrders($conn, $adminId);
    $xlsx   = buildXlsx($orders);

    $label    = $adminId > 0 ? "admin{$adminId}" : "all";
    $filename = "orders_{$label}_" . date('Ymd_His') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsx));
    header('Cache-Control: no-cache');
    echo $xlsx;
    exit;
}

// ── DELETE (only after export confirmed) ──
if ($action === 'delete') {
    header('Content-Type: application/json');

    // Verify a valid admin session
    $adminName = $_SESSION['username'] ?? 'superadmin';

    try {
        if ($adminId > 0) {
            // Before deleting, restore all slot counts for non-cancelled orders
            $slots = $conn->prepare("
                SELECT variant_id, COUNT(*) as cnt
                FROM orders
                WHERE admin_id = :aid AND status != 'cancelled'
                GROUP BY variant_id
            ");
            $slots->execute([':aid' => $adminId]);
            foreach ($slots->fetchAll(PDO::FETCH_ASSOC) as $sl) {
                $conn->prepare("UPDATE inventory_item_variants SET slots_used = MAX(0, slots_used - :cnt) WHERE id = :id")
                     ->execute([':cnt' => $sl['cnt'], ':id' => $sl['variant_id']]);
            }
            $count = $conn->prepare("SELECT COUNT(*) FROM orders WHERE admin_id = :aid");
            $count->execute([':aid' => $adminId]);
            $total = (int)$count->fetchColumn();
            $conn->prepare("DELETE FROM orders WHERE admin_id = :aid")->execute([':aid' => $adminId]);
            log_activity($conn, $adminName, 'Deleted orders', "admin_id=$adminId", "count=$total");
        } else {
            // Restore all non-cancelled slot counts
            $slots = $conn->query("
                SELECT variant_id, COUNT(*) as cnt
                FROM orders WHERE status != 'cancelled'
                GROUP BY variant_id
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($slots as $sl) {
                $conn->prepare("UPDATE inventory_item_variants SET slots_used = MAX(0, slots_used - :cnt) WHERE id = :id")
                     ->execute([':cnt' => $sl['cnt'], ':id' => $sl['variant_id']]);
            }
            $total = (int)$conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
            $conn->exec("DELETE FROM orders");
            log_activity($conn, $adminName, 'Deleted ALL orders', 'all', "count=$total");
        }

        echo json_encode(['status' => 'success', 'deleted' => $total]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>