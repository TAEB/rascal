<?php
include('vt102.php');

$str = file_get_contents ("C:/rascal/dumplog.txt");
$ouput = parseVT102( $str, $output, TRUE );//true specifies to make a debugging report *large filesize*

?>