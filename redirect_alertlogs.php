<?php
$input = file_get_contents("php://input");
file_put_contents("alertlog_okx.txt", $input . PHP_EOL, FILE_APPEND);

//$input='XRPUSDT;BUY;0.03;0.01';
header("Location: insertalertlog.php?input=".$input);
exit;
?>