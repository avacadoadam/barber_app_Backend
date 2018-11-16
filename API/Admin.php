<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 11/7/2018
 * Time: 3:43 AM
 */


require_once 'inner/Response.php';
if (!isset($_SESSION)) {
    session_start();
}


switch ($_POST['action']) {
    case "LogIn";
        LogIn();
        break;
    case "GetAppointments";
        GetAppointments();
        break;
    case "CancelAppointment":
        CancelAppointment();
        break;
    case "CreateAnotherAdmin":
        CreateAdmin();
        break;
    Default:
        Respond(false, "Incorrect Action");
}


function LogIn()
{
    if (!isset($_SESSION['id'])) {

        if (isset($_POST['email']) && isset($_POST['passwd'])) {
            $admin = new Admin();
            $admin->LogIn();
        } else {
            Respond(false, "Invalid Email or Password");
        }
    } else {
        Respond(false, "Already logged in");
    }
}

function GetAppointments()
{
    if (!isset($_SESSION['id']) && $_SESSION['type'] === "Admin") {
        Respond(false, "Must be logged in as Admin");
    } else {
        $admin = new Admin();
        if (isset($_POST['AmountOfAppointments'])) {
            $admin->GetAppointments($_POST['AmountOfAppointments']);
        } else {
            $admin->GetAppointments(0);
        }

    }
}


function CreateAdmin()
{
    if (!isset($_SESSION['id']) && $_SESSION['type'] === "Admin") {
        Respond(false, "Must be logged in as Admin");
    } else {
        if (isset($_POST['fname']) && isset($_POST['lname']) && isset($_POST['email']) && isset($_POST['passwd'])) {
            $admin = new Admin();
            $admin->CreateAccount($_POST['fname'], $_POST['lname'], $_POST['email'], $_POST['passwd']);
        } else {
            Respond(false, "Some Fiells are not set");
        }
    }
}

function CancelAppointment()
{
    if (!isset($_SESSION['id']) && $_SESSION['type'] === "Admin" && $_POST['AppointmentID']) {
        Respond(false, "Must be logged in as Admin");
    } else {
        $admin = new Admin();
        $admin->CancelAppointment($_POST['AppointmentID']);
    }
}


class Admin
{

    private $DB;

    function __construct()
    {
        require_once 'inner/Database.php';
        $this->DB = DatabaseOperations::Instance();
    }

    public function LogIn()
    {
        mysqli_escape_string($this->DB->getConn(), $_POST['email']);
        $hash = hash('sha256', $_POST['passwd']);
        $query = 'SELECT * FROM `admins` WHERE email=? & passwd = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("ss", $_POST['email'], $hash);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->free_result();
            $stmt->close();
            if ($result->num_rows > 0) {
                $this->SetSession($result->fetch_assoc());
            } else {
                Respond(false, "Email or Password is incorrect");
            }
        } else {
            Respond(false, "Couldn't connect to Database");
        }
    }


    public function CreateAccount($fname, $lname, $email, $passwd)
    {
        mysqli_escape_string($this->DB->getConn(), $fname);
        mysqli_escape_string($this->DB->getConn(), $lname);
        mysqli_escape_string($this->DB->getConn(), $email);
        mysqli_escape_string($this->DB->getConn(), $passwd);
        $hash = hash('sha256', $_POST['passwd']);
        $query = 'INSERT INTO `admins` (`id`, `fname`, `lname`, `passwd`, `email`) VALUES (NULL,?,?,?,?);';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("ssss", $fname, $lname, $email, $hash);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->free_result();
            $stmt->close();
            if ($result->num_rows > 0) {
                Respond(true);
            } else {
                Respond(false, "Failed to create Admin Account");
            }
        } else {
            Respond(false, "Couldn't connect to Database");
        }


    }

    public function GetAppointments($AmountOfAppointments)
    {
        if (is_null($AmountOfAppointments) || $AmountOfAppointments > 100 || $AmountOfAppointments < 0) {
            $AmountOfAppointments = 0;
        }

        mysqli_escape_string($this->DB->getConn(), $AmountOfAppointments);
        $query = "SELECT            Appointments.id as appointmentID,
                  concat(Barber.fname, Barber.lname) AS BarbersName,
                  concat(customer.fname, customer.lname) AS CustomerName,
                  barbershop.name                    AS barbershopname,
                  Appointments.date,
                  Appointments.time
                  FROM Appointments
                  CROSS JOIN Barber ON Barber.id = Appointments.barberID
                  CROSS JOIN Barbershop ON Appointments.barbershopID = Barbershop.id
                  CROSS JOIN customer ON Appointments.customerID = customer.id
                  AND  Appointments.date > CURRENT_DATE()
                  LIMIT ?,15";
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $AmountOfAppointments);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->free_result();
            $stmt->close();
            if ($result->num_rows > 0) {
                $this->ParseAppointments($result);
            } else {
                Respond(false, "No appointments");
            }


        } else {
            Respond(false, "Couldn't connect to Database");
        }

    }

    public function CancelAppointment($AppointmentID)
    {
        mysqli_escape_string($this->DB->getConn(), $AppointmentID);
        $query = 'DELETE FROM appointments WHERE appointments.id = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $AppointmentID);
            $succes = $stmt->execute();
            $stmt->free_result();
            $stmt->close();
            if ($succes) {
                Respond(true, "Deleted ");
            } else {
                Respond(false, "Couldn't Cancel Appointment");
            }
        } else {
            Respond(false, "Couldn't Connect");
        }
    }

    private function SetSession($result)
    {
        $_SESSION['id'] = $result['id'];
        $_SESSION['type'] = "Admin";
        $_SESSION['email'] = $result['email'];
        $_SESSION['fname'] = $result['fname'];
        $_SESSION['lname'] = $result['lname'];
        echo json_encode(array('success' => true, 'id' => $result['id'],
            'fname' => $result['fname'],
            'lname' => $result['lname'],
            'email' => $result['email'],
            'type' => "Admin"), JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * @param $result
     * To be used with Myappointment barber or customer func
     * Takes in a msqli result and parse it into json to be echo and exited
     */
    private function ParseAppointments($result)
    {

        $rows = array();
        $rows[] = array('success' => true);
        while ($row = $result->fetch_assoc()) {
            $rows[] = array("id" => $row['appointmentID'],
                "BarberName" => $row['BarbersName'],
                "CustomerName" => $row['CustomerName'],
                "Barbershop" => $row['barbershopname'],
                "Date" => $row['date'],
                "Time" => $row['time']);
        }
        echo json_encode($rows, JSON_PRETTY_PRINT);
        exit();
    }

}




