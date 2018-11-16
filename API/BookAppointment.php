<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 10/28/2018
 * Time: 8:55 PM
 */
require_once 'Inner/Response.php';
/**
 * Check if session is set if so Return Json response and exit
 */
if (!isset($_SESSION)) {
    session_start();
}
//Checks if user already set one appointment during this session
if(isset($_SESSION['SetAppointment'])){
    Respond(false,"Already set one appointment");
}

if (!(isset($_POST['barbershop']) && isset($_POST['barber']) && isset($_POST['date']) && isset($_POST['time']))) {
    Respond(false, "One or more fields are unset");
} else {
    if ($_SESSION['type'] === "Customer"){
        new BookAppointment($_POST['barbershop'], $_POST['barber'], $_POST['date'], $_POST['time'], $_SESSION['id']);
    }else{
           Respond(false, "Only customers can create appointments");
    }
}


class BookAppointment
{

    private $barberShop;
    private $barber;
    private $date;
    private $time;
    private $customerID;
    private $DB;

    /**
     * BookAppointment constructor.
     * @param $barberShop
     * @param $barber
     * @param $date
     * @param $time
     * @param $customer
     */
    public function __construct($barberShop, $barber, $date, $time, $customer)
    {

        if (!preg_match("/^\d{2}:\d{2}:\d{2}$/", $time)) Respond(false, "Time not formated");
        if (!preg_match("/\d{4}-\d{2}-\d{2}$/", $date)) Respond(false, "date not formated");
        if ($this->CheckInPast($date)) Respond(false, "Can't book in past");
        if ($this->CheckTime($time)) Respond(false, "Not in between 6pm and 7am");
        $this->barberShop = $barberShop;
        $this->barber = $barber;
        $this->date = $date;
        $this->time = $time;
        $this->customerID = $customer;
        require_once 'Inner/Database.php';
        require_once 'Inner/Response.php';
        $this->DB = DatabaseOperations::Instance();
        $this->Book();
    }


    private function Book()
    {
        mysqli_real_escape_string($this->DB->getConn(), $this->barberShop);
        mysqli_real_escape_string($this->DB->getConn(), $this->barber);
        mysqli_real_escape_string($this->DB->getConn(), $this->customerID);
        mysqli_real_escape_string($this->DB->getConn(), $this->time);
        mysqli_real_escape_string($this->DB->getConn(), $this->date);
        $query = 'INSERT INTO `appointments` (`barberID`, `customerID`, `barbershopID`, `date`, `time`, `appointment_length` ) VALUES (?,?,?,?,?,"00:30:00.000000")';
        if ($stmt = mysqli_prepare($this->DB->getConn(), $query)) {
            $stmt->bind_param("iiiss", $this->barber, $this->customerID, $this->barberShop, $this->date, $this->time);
            $success = $stmt->execute();
            $stmt->free_result();
            $stmt->close();
            if ($success) {
                $_SESSION['SetAppointment'] = true;
                Respond(true);
            } else {
                Respond(false, "Failed to Create appointment ");
            }
        } else {
            Respond(false, $this->DB->getConn()->error);
            Respond(false, "Failed to connect to database");
        }
    }

    /**
     * @param $time
     * Returns true if time fits format and if the hour is between 6pm and 7am
     * will exit otherwise after sending reponse
     * @return bool
     */
    private function CheckTime($time)
    {
        $h = (int)$time[0]+$time[1];
        if ($h < 18 && $h > 07) {
            return true;
        }
        return false;
    }

    /**
     * @param $date
     * @return bool
     * Checks if Date is in past
     */
    static function CheckInPast($date)
    {

        $appointment_date = new DateTime($date);
        $today_date = new DateTime();

        if ($appointment_date < $today_date) {
            return true;
        }
        return false;
    }


}