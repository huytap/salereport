<?php
$list = "CO1
C02
C03
C04
OS
T1
T2
special";
$temp_arr = explode("\n", $list);
$return_arr = array();
foreach ($temp_arr as $temp_val) {
	$temp_val = trim($temp_val, " \r\n");
	$return_arr[$temp_val]  = $temp_val;
}
return $return_arr;
?>