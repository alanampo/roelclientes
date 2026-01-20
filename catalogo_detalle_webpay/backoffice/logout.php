<?php
// /catalogo_detalle/backoffice/logout.php
declare(strict_types=1);
require __DIR__ . '/_boot.php';

bo_audit('logout');
unset($_SESSION['bo_admin']);
header('Location: login.php');
exit;
