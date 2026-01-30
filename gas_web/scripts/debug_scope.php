<?php
include __DIR__ . '/../flutter_api/connection.php';
var_dump('global_connect', isset($connect), gettype($connect));
function test_in_func() {
    include __DIR__ . '/../flutter_api/connection.php';
    var_dump('inside_func_connect', isset($connect), is_object($connect));
}
test_in_func();
?>