<?php
session_start();
require_once("../config/db.php");
require_once("../config/activity.php");
$adminName = $_SESSION['username'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId           = (int)($_POST['user_id'] ?? 0);
    $canUserdata      = isset($_POST['can_userdata'])      ? 1 : 0;
    $canActivity      = isset($_POST['can_activity'])      ? 1 : 0;
    $canSettings      = isset($_POST['can_settings'])      ? 1 : 0;
    $canSales         = isset($_POST['can_sales'])         ? 1 : 0;
    $canSalesCatered  = isset($_POST['can_sales_catered']) ? 1 : 0;
    $canSalesDenied   = isset($_POST['can_sales_denied'])  ? 1 : 0;
    $canSalesTickets  = isset($_POST['can_sales_tickets']) ? 1 : 0;
    $canSalesOrders   = isset($_POST['can_sales_orders'])  ? 1 : 0;

    // If master sales toggle is off, clear all sub-permissions
    if (!$canSales) {
        $canSalesCatered = $canSalesDenied = $canSalesTickets = $canSalesOrders = 0;
    }

    if (!$userId) {
        header("Location: index.php?section=admins&error=Invalid+user"); exit;
    }

    $target = $conn->prepare("SELECT username, role FROM users WHERE id = :id");
    $target->execute([':id' => $userId]);
    $target = $target->fetch(PDO::FETCH_ASSOC);

    if (!$target || $target['role'] === 'superadmin') {
        header("Location: index.php?section=admins&error=Cannot+modify+superadmin"); exit;
    }

    $conn->prepare("
        INSERT INTO admin_permissions (
            user_id, can_userdata, can_activity, can_settings,
            can_sales, can_sales_catered, can_sales_denied, can_sales_tickets, can_sales_orders
        )
        VALUES (:uid, :cu, :ca, :cs, :csl, :cslc, :csld, :cslt, :cslo)
        ON CONFLICT(user_id) DO UPDATE SET
            can_userdata      = :cu,
            can_activity      = :ca,
            can_settings      = :cs,
            can_sales         = :csl,
            can_sales_catered = :cslc,
            can_sales_denied  = :csld,
            can_sales_tickets = :cslt,
            can_sales_orders  = :cslo
    ")->execute([
        ':uid'  => $userId,
        ':cu'   => $canUserdata,
        ':ca'   => $canActivity,
        ':cs'   => $canSettings,
        ':csl'  => $canSales,
        ':cslc' => $canSalesCatered,
        ':csld' => $canSalesDenied,
        ':cslt' => $canSalesTickets,
        ':cslo' => $canSalesOrders,
    ]);

    log_activity($conn, $adminName, 'Updated admin permissions', $target['username'],
        "userdata=$canUserdata, activity=$canActivity, settings=$canSettings, " .
        "sales=$canSales [catered=$canSalesCatered, denied=$canSalesDenied, tickets=$canSalesTickets, orders=$canSalesOrders]");

    header("Location: index.php?section=admins&success=Permissions+updated"); exit;
}

header("Location: index.php?section=admins"); exit;
?>