<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 10/20/2018
 * Time: 7:36 PM
 */
require_once 'Inner/Response.php';
/**
 * Check if session is set if so Return Json response and exit
 */
if (!isset($_SESSION)) {
    session_start();
}
if (isset($_SESSION['ID'])) {
    Respond(false, "Already Logged in");
    exit();
}
/**
 * Ensure all values are set
 * Theses attributes are used for both barber and customer
 */
if (isset($_POST['passwd']) && isset($_POST['type']) && isset($_POST['email'])
    && isset($_POST['fname']) && isset($_POST['lname'])) {
    new Create_Account();
} else {
    Respond(false, "One or more fields are not set");
}


class Create_Account
{
    private $email, $passwd, $fname, $lname, $type;
    private $NAME_LENGTH_MAX = 30, $NAME_LENGTH_MIN = 2; // if updated update response
    private $PASSWORD_MAX_LENGTH = 15, $PASSWORD_MIN_LENGTH = 8;
    private $DB;

    /**
     * If any of user account info is not valid a response will be issued and will exit.
     *
     */
    function __construct()
    {
        $this->type = $_POST['type'];
        $this->passwd = $_POST['passwd'];
        $this->email = $_POST['email'];
        $this->fname = $_POST['fname'];
        $this->lname = $_POST['lname'];
        if (!$this->ValidateName($this->fname, $this->lname)) Respond(false, "Name Not between 2 and 30 letters");
        if (!$this->ValidateEmail($this->email)) Respond(false, "Email is not valid");
        if (!$this->ValidateType($this->type)) Respond(false, "type is not valid");
        if (!$this->ValidatePassword($this->passwd)) Respond(false, "password does not fit restrictions");

        require_once 'Inner/Database.php';
        $this->DB = DatabaseOperations::Instance();
        if (!$this->DB->isCONNECTED()) Respond(false, "Couldn't connect to Database");

        if ($this->type == "Customer" || $this->type == "customer") {
            $this->Create_Customer();
        } elseif ($this->type == "Barber" || $this->type == "barber") {
            $this->Create_Barber();
        }

    }

    //three separte functions for barbers and admin and customert
    private function Create_Barber()
    {
        mysqli_real_escape_string($this->DB->getConn(), $this->email);
        mysqli_real_escape_string($this->DB->getConn(), $this->passwd);
        mysqli_real_escape_string($this->DB->getConn(), $this->fname);
        mysqli_real_escape_string($this->DB->getConn(), $this->lname);
        $hash = hash('sha256', $this->passwd);
        $query = 'INSERT INTO barber VALUES(NULL,NULL,?,?,?,?,NULL,NULL)';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("ssss", $this->fname, $this->lname, $this->email, $hash);
            $success = $stmt->execute();
            $stmt->free_result();
            $stmt->close();
            if ($success) {
                $this->CreateBarberRating($this->email);
                Respond(true);
            } else {
                Respond(false, "Email already used");
            }
        } else {
            Respond(false, "Failed to connect to database");
        }
    }

       /**
     * @param $email Barbers email
     * Will create a Barber Rating table
     */
    private function CreateBarberRating($email)
    {
        $result = $this->DB->Query('SELECT id FROM `barber` WHERE email = "'.$email.'"');
        $id = $result->fetch_assoc()['id'];
        $result = $this->DB->Query('INSERT into barber_rating VALUES(NULL,' . $id . ',5)');
    }

    private function Create_Customer()
    {
        mysqli_real_escape_string($this->DB->getConn(), $this->email);
        mysqli_real_escape_string($this->DB->getConn(), $this->passwd);
        mysqli_real_escape_string($this->DB->getConn(), $this->fname);
        mysqli_real_escape_string($this->DB->getConn(), $this->lname);
        $hash = hash('sha256', $this->passwd);
        $query = 'INSERT INTO Customer VALUES(NULL,?,?,?,?,NULL)';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("ssss", $this->fname, $this->lname, $hash, $this->email);
            $success = $stmt->execute();
            $stmt->free_result();
            $stmt->close();
            if ($success) {
                $this->CreateCustomerRating($this->email);
                Respond(true);
            } else {
                Respond(false, "Email already used");
            }
        } else {
            Respond(false, "Failed to connect to database");
        }
    }

    /**
     * @param $email Customer Email
     * Will create a Customer Rating table
     */
    private function CreateCustomerRating($email)
    {
        $result = $this->DB->Query('SELECT id FROM `Customer` WHERE email = "'.$email.'"');
        $id = $result->fetch_assoc()['id'];
        $result = $this->DB->Query('INSERT into customer_rating VALUES(NULL,' . $id . ',5)');
    }


    /**
     * @param $passwd
     * @return bool
     * Ensure passwd is within schema
     * between 8 and 12
     * one special char
     * one cap
     * one num
     */
    private function ValidatePassword($passwd)
    {
        if (strlen($passwd) < $this->PASSWORD_MAX_LENGTH
            && strlen($passwd) > $this->PASSWORD_MIN_LENGTH
            && 1 === (preg_match("/\d/", $passwd))
            && 1 === (preg_match("/[A-Z]/", $passwd))
            && 1 === (preg_match("/[@#$%^&*()_!]/", $passwd))) {
            return true;
        }
        return false;
    }

    /**
     * @param $type
     * @return bool
     * Ensure Type is within schema
     */
    private function ValidateType($type)
    {
        if ($type == "barber" || $type == "customer" || $type == "Barber" || $type == "Customer") {
            return true;
        }
        return false;
    }

    /**
     * @param $email
     * @return bool
     * Uses built in regex from FILTER_VALIDATE_EMAIL to valid email
     */
    private function ValidateEmail($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        return false;

    }

    /**
     * @param $fname
     * @param $lname
     * @return bool
     * Fname and lname must be between NAME_LENGTH_MIN and NAME_LENGTH_MAX characters
     */
    private function ValidateName($fname, $lname)
    {
        if (strlen($fname) < $this->NAME_LENGTH_MAX && strlen($lname) < $this->NAME_LENGTH_MAX
            && strlen($fname) > $this->NAME_LENGTH_MIN && strlen($lname) > $this->NAME_LENGTH_MIN) {
            return true;
        }
        return false;

    }


}