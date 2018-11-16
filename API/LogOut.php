<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 10/30/2018
 * Time: 8:03 PM
 */
require_once 'inner/Response.php';
if (!isset($_SESSION)) {
    session_start();
}
session_unset();
session_destroy();
Respond(true);
