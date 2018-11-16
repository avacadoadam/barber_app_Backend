<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 10/23/2018
 * Time: 9:37 PM
 */
require_once 'inner/Response.php';
if (!isset($_SESSION)) {
    session_start();
}
if (isset($_SESSION['id']) && isset($_SESSION['type']) && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case "GetMyAppointment";
            GetMyAppointments();
            break;
        case "BarberFreeTime";
            BarberFreeTime();
            break;
        case "ListBarbers";
            ListBarbers();
            break;
        case "ListBarberShops";
            ListBarbershops();
            break;
        case "CancelAppointment";
            CancelAppointment();
            break;
        Default:
            Respond(false, "Incorrect Action");
    }

} else {
//    header("Location: http://example.com/myOtherPage.php");
    Respond(false, "Must log in");
}

function CancelAppointment()
{
    if (isset($_POST['AppointmentID'])) {
        $o = new GetAppointments();
        $o->CancelAppointment($_POST['AppointmentID']);
    } else {
        Respond(false, "Must have a AppointmentID");
    }
}

function ListBarbershops()
{
    $o = new GetAppointments();
    $o->ListBarbershops();
}

function ListBarbers()
{
    $o = new GetAppointments();
    $o->ListBarbers();
}


//todo logging
function BarberFreeTime()
{
    $o = new GetAppointments();
    if (isset($_POST['BarberID']) && isset($_POST['Date'])) {

        if (!preg_match("/\d{4}-\d{2}-\d{2}$/", $_POST['Date'])) Respond(false, "Date is not formated");
        $appointment_date = new DateTime($_POST['Date']);
        $today_date = new DateTime();
        if ($appointment_date > $today_date) {
            $o->GetBarberFreeTime($_POST['BarberID'], $_POST['Date']);
        } else {
            Respond(false, "Date must be in future");
        }
        return false;
    } else {
        Respond(false, "Date or BarberID is not set");
    }
}

function GetMyAppointments()
{
    $o = new GetAppointments();
    if ($_SESSION['type'] === 'Customer' || $_SESSION['type'] === 'customer') {
        $o->GetAppointmentsCustomer($_SESSION['id']);
    } elseif ($_SESSION['type'] === 'Barber' || $_SESSION['type'] === 'barber') {
        $o->GetAppointmentsBarber($_SESSION['id']);
    }
}


class GetAppointments   //////////Class Start
{
    private $DB;

    /**
     * GetAppointments constructor.
     * Will call query function based on type
     */
    function __construct()
    {
        require_once 'inner/Database.php';
        $this->DB = DatabaseOperations::Instance();


    }

    /**
     * @param $barberID
     * Will get all appointments for the barber indicated by his/her ID
     */
    function GetAppointmentsBarber($barberID)
    {
        $query = 'SELECT
                  Appointments.id as appointmentID,
                  concat(Barber.fname, Barber.lname) AS BarbersName,
                  concat(customer.fname, customer.lname) AS CustomerName,
                  barbershop.name                    AS barbershopname,
                  Appointments.date,
                  Appointments.time
                  FROM Appointments
                  CROSS JOIN Barber ON Barber.id = Appointments.barberID
                  CROSS JOIN Barbershop ON Appointments.barbershopID = Barbershop.id
                  CROSS JOIN customer ON Appointments.customerID = customer.id
                  WHERE Appointments.barberID = ?
                  AND  Appointments.date > CURRENT_DATE()
                   LIMIT 10';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $barberID);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->free_result();
            $stmt->close();
            if ($result->num_rows > 0) {
                $this->MyAppointmentRespondSuccess($result);
            } else {
                Respond(false, "No appointments set");
            }
        } else {
            Respond(false, "Couldn't Connect");
        }
    }

    /**
     * @param $CustomerID
     * Will get all appointments for the customer indicated by his/her ID
     */
    function GetAppointmentsCustomer($CustomerID)
    {
        $query = 'SELECT
                  Appointments.id as appointmentID,
                  concat(Barber.fname, Barber.lname) AS BarbersName,
                  concat(customer.fname, customer.lname) AS CustomerName,
                  barbershop.name                    AS barbershopname,
                  Appointments.date,
                  Appointments.time
                  FROM Appointments
                  CROSS JOIN Barber ON Barber.id = Appointments.barberID
                  CROSS JOIN Barbershop ON Appointments.barbershopID = Barbershop.id
                  CROSS JOIN customer ON Appointments.customerID = customer.id
                  WHERE Appointments.customerID = ?
                  AND  Appointments.date > CURRENT_DATE()
                  LIMIT 10';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $CustomerID);
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->free_result();
            $stmt->close();
            if ($result->num_rows > 0) {
                $this->MyAppointmentRespondSuccess($result);
            } else {
                Respond(false, "No appointments set");
            }
        } else {
            Respond(false, "Couldn't Connect");
        }
    }

    /**
     * Will Check BarberID is valid then
     * Will echo out a json response containing the time slots a barber is free
     * Will need a valid barberID
     */
    function GetBarberFreeTime($BarberID, $date)
    {
        mysqli_real_escape_string($this->DB->getConn(), $date);
        mysqli_real_escape_string($this->DB->getConn(), $BarberID);
        $query = 'SELECT * FROM barber  WHERE ID = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $BarberID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $query = 'SELECT time as start,ADDTIME(appointment_length,time) as finish FROM `appointments` WHERE `barberID` = ? AND `date` = ? LIMIT 10';
                $stmt->prepare($query);
                $stmt->bind_param("is", $BarberID, $date);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->free_result();
                $stmt->close();
                $this->BarberFreeTimeResponse($result);
            } else {
                $stmt->free_result();
                $stmt->close();
                Respond(false, "Barber dosn't exist");
            }
        } else {
            Respond(false, "Couldn't Connect");
        }
    }

    function ListBarbershops()
    {
        $query = 'SELECT * FROM Barbershop';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->free_result();
            $stmt->close();
            $this->ReturnBarbershop($result);
        } else {
            Respond(false, "Couldn't Connect");
        }
    }

    /**
     * Lists barbers that are apporved by admin and also based on rating
     */
    function ListBarbers()
    {
        $query = 'SELECT barber.approved,barber.id,CONCAT(barber.fname,barber.lname) AS BarbersName,barber.phone_number,barber.BarbershopID,barber_rating.rating as rating
          FROM barber CROSS JOIN barber_rating ON barber.id = barber_rating.barberID WHERE barber.approved = 1 GROUP BY barber_rating.rating LIMIT 10';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->execute();
            $result = $stmt->get_result();
            $stmt->free_result();
            $stmt->close();
            $this->ReturnListOfBarbers($result);
        } else {
            Respond(false, "Couldn't Connect");
        }

    }

    public function CancelAppointment($AppointmentID)
    {
        mysqli_real_escape_string($this->DB->getConn(), $AppointmentID);
        $query = 'DELETE FROM appointments WHERE appointments.id = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $AppointmentID);
            $succes = $stmt->execute();
            if ($succes) {
                if ($_SESSION['type'] === 'Customer') $this->LowerRatingCustomer();
                if ($_SESSION['type'] === 'Barber') $this->LowerRatingBarberRating();
            } else {
                Respond(false, "Couldn't Cancel Appointment");
            }
            $stmt->free_result();
            $stmt->close();
            Respond(true);
        } else {
            Respond(false, "Couldn't Connect");
        }
    }

    private function LowerRatingCustomer()
    {
        $query = 'UPDATE customer_rating SET rating = rating - 1 WHERE customerID = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $_SESSION['id']);
            $stmt->execute();
            $stmt->free_result();
            $stmt->close();
        }
    }

    private function LowerRatingBarberRating()
    {
        $query = 'UPDATE barber_rating SET rating = rating - 1 WHERE barberID = ?';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("i", $_SESSION['id']);
            $stmt->execute();
            $stmt->free_result();
            $stmt->close();
        }
    }



    //-------------------------------------------- Results --------------------------------------------

    /**
     * @param $result
     * Parses and return result from listBarbershops then exits
     */
    private function ReturnBarbershop($result)
    {
        $rows = array();
        $rows[] = array('success' => true);
        while ($row = $result->fetch_assoc()) {
            $rows[] = array(
                "BarbershopID" => $row['id'],
                "BarberShopName" => $row['name'],
                "address" => $row['address']);
        }
        echo json_encode($rows, JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * @param $result
     * To be used with barberfreeTime func
     * Takes in a msqli result and parse it into json to be echo and exited
     */
    private function BarberFreeTimeResponse($result)
    {
        $rows = array();
        $rows[] = array('success' => true);
        while ($row = $result->fetch_assoc()) {
            $rows[] = array("StartTime" => $row['start'],
                "Finish" => $row['finish']);
        }
        echo json_encode($rows, JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * @param $result
     * To be used with Myappointment barber or customer func
     * Takes in a msqli result and parse it into json to be echo and exited
     */
    private function MyAppointmentRespondSuccess($result)
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


    /**
     * @param $result
     * Parses ReturnListOfBarbers into json format and echos back then exits
     */
    private function ReturnListOfBarbers($result)
    {
        $rows = array();
        $rows[] = array('success' => true);
        while ($row = $result->fetch_assoc()) {
            $rows[] = array(
                "BarberName" => $row['BarbersName'],
                "BarberID" => $row['id'],
                "PhoneNumber" => $row['phone_number'],
                "rating" => $row['rating']);
        }
        echo json_encode($rows, JSON_PRETTY_PRINT);
        exit();
    }


}