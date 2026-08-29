<?php
require_once __DIR__ . '/config.php';
$conn = db();
$conn->query("UPDATE radacct SET acctstoptime=NOW(), acctterminatecause='Stale-Session' WHERE acctstoptime IS NULL AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(acctupdatetime) > 120 AND UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(acctstarttime) > 300");
