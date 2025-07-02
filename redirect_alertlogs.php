<?php
$input = file_get_contents("php://input");
$input='WIF;BUY;07022025';
file_put_contents('alertlog_okx.txt', $input . PHP_EOL, FILE_APPEND);
exit;

?>