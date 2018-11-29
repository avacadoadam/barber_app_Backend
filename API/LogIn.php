<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 10/20/2018
 * Time: 5:10 PM
 */
require_once 'inner/Response.php';
/**
 * Check if session is set if so Return Json response and exit
 */
if (!isset($_SESSION)) {
    session_start();
}
if (isset($_SESSION['id'])) {
    Respond(false, "Already Logged in");
}

/**
 * Ensure all values are set
 */
if (isset($_POST['passwd']) && isset($_POST['type']) && isset($_POST['email'])) {
    if ($_POST['type'] == "Customer" || $_POST['type'] == "Barber" || $_POST['type'] == "Admin") {
        new Log_In($_POST['email'], $_POST['passwd'], $_POST['type']);
    } else {
        Respond(false, "Type dosn't exist");
    }
} else {
    Respond(false, "One or more fields are not set");
}

class Log_In
{

    private $DB;

    /**
     * Log_In constructor.
     * @param $email
     * @param $passwd
     * @param $type Type of account i.e Barber,Customer,Admin
     * If database fails respond fail
     * if connects hash password and check details are correct and return result
     */
    function __construct($email, $passwd, $type)
    {
        $this->ValidateUserInput($email, $passwd, $type);
        require_once 'Inner/Database.php';
        $this->DB = DatabaseOperations::Instance();
        if ($type == "Admin") {
            $this->AdminLogin($email, $passwd, $type);
        } else {
            $this->User_Info_Correct($email, $passwd, $type);
        }
    }

    /**
     * @param $email
     * @param $passwd
     * @param $type
     * Hash Password then
     * Checks if the user info is same as in database
     * Then the result of data is sent to SessionControl and put into session
     */
    private function User_Info_Correct($email, $passwd, $type)
    {
        mysqli_real_escape_string($this->DB->getConn(), $email);
        mysqli_real_escape_string($this->DB->getConn(), $passwd);
        $hash = hash('sha256', $passwd);
        $query = 'SELECT * FROM ' . $type . ',' . $type . '_rating WHERE passwd = ? AND email = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("ss", $hash, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            if ($result->num_rows > 0) {
                $this->Set_session($result->fetch_assoc(), $type);
            } else {
                Respond(false, "Incorrect Email or Password");
            }
        } else {
            Respond(false, "Couldn't connect to Database");
        }
    }

    private function AdminLogin($email, $passwd, $type)
    {
        mysqli_real_escape_string($this->DB->getConn(), $email);
        mysqli_real_escape_string($this->DB->getConn(), $passwd);
        $hash = hash('sha256', $passwd);
        $query = 'SELECT * FROM ' . $type . ' WHERE passwd = ? AND email = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("ss", $hash, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->close();
            if ($result->num_rows > 0) {
                $this->Set_session($result->fetch_assoc(), $type);
            } else {
                Respond(false, "Incorrect Email or Password");
            }
        } else {
            Respond(false, "Couldn't connect to Database");
        }
    }


    /**
     * @param $type
     *  Checks that email,passwd and type follow schema before calling database functions.
     *  Which is passwd between 8 and 12 chars has one Num, One Cap, One Special Char
     *  Email is valid
     *  And type is a table of the database
     */
    private function ValidateUserInput($email, $passwd, $type)
    {
        if (!(strlen($passwd) < 12
            && strlen($passwd) > 8
            && 1 === (preg_match("/\d/", $passwd))
            && 1 === (preg_match("/[A-Z]/", $passwd))
            && 1 === (preg_match("/[@#$%^&*()_!]/", $passwd)))) {
            Respond(false, "Invalid password or email");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Respond(false, "Invalid password or email");
        }
        if (!($type = "Barber" || $type == "Customer" || $type == "Admins")) {
            Respond(false, "Invalid type");
        }
    }


    /**
     * @param $result
     * @param $type type of User
     * $result should be array an associative array
     * sets Session variables
     * fname,lname,email,id,type
     */
    private function Set_session($result, $type)
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['id'] = $result['id'];
        $_SESSION['fname'] = $result['fname'];
        $_SESSION['lname'] = $result['lname'];
        $_SESSION['email'] = $result['email'];
        $_SESSION['type'] = $type;
        if ($type == "Barber")
            $_SESSION['approved'] = $result['approved'];

        $success = array('success' => true, 'id' => $result['id'],
            'fname' => $result['fname'],
            'lname' => $result['lname'],
            'email' => $result['email'],
            'type' => $type,
        );
        if ($type != "Admin") $success =+ array('rating' => $result['rating']);

        echo json_encode($success, JSON_PRETTY_PRINT);
        exit();
    }


}
