<?php
$pdo = new PDO('mysql:host=localhost;dbname=radius', 'radius', 'R@d1us2026');
$pdo->exec("UPDATE radacct SET acctstoptime=NOW(), acctterminatecause='Stale-Session' WHERE acctstoptime IS NULL AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(acctupdatetime) > 120");
