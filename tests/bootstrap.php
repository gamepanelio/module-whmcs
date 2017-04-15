<?php

require_once __DIR__ . "/../vendor/autoload.php";

define('WHMCS', true);
define('WHMCS_TESTGATE', true);

$mockApiClient = null;

function full_query($query)
{
    return null;
}

function select_query($table, $select, $where)
{
    return null;
}

function update_query($table, $update, $where)
{
    return null;
}

function logModuleCall($a, $b, $c, $d, $e = null, $f = null)
{
    throw new \Exception(json_encode(func_get_args()));
}
