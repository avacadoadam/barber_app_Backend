<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 11/1/2018
 * Time: 7:19 PM
 */


/**
 * @param $successful_login
 * @param null $error
 * A template for formatting errors and success using json that will be used across API
 */
function Respond($successful_login, $error = NULL)
    {
        if ($successful_login) {
            echo json_encode(array('success' => true),JSON_PRETTY_PRINT);
        } else {
            if (is_null($error)) {
                echo json_encode(array('success' => false) ,JSON_PRETTY_PRINT);
            } else {
                echo json_encode(array('success' => false, 'error' => $error),JSON_PRETTY_PRINT);
            }
        }
        exit();
    }