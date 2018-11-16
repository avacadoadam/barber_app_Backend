<?php
/**
 * Created by PhpStorm.
 * User: avaca
 * Date: 10/10/2018
 * Time: 6:54 PM
 */
class DatabaseOperations
{
    private $ERROR_MESSAGE;
    private $servername = "localhost";
    private $username = "root";
    private $password = "";
    private $DatabaseName = "";
    private $conn;
    private $CONNECTED = false;


    /**
     * DatabaseOperations constructor.
     * @param $servername
     * @param $username
     * @param $password
     * @param $DatabaseName
     * Will Die() if Cannot connect
     * however set CONNECTED if connection succeds
     */
    private function __construct($servername, $username, $password, $DatabaseName)
    {
        //Can set mysql login details
        if (isset($servername)) $this->servername = $servername;
        if (isset($username)) $this->username = $username;
        if (isset($password)) $this->password = $password;
        if (isset($DatabaseName)) $this->DatabaseName = $DatabaseName;

        // Create connection
        $this->conn = new mysqli($this->servername, $this->username, $this->password, $this->DatabaseName);
        // Check connection
        if ($this->conn->connect_error) {
            $ERROR_MESSAGE = $this->conn->connect_error;
            die("Connection failed:  " . " <br><h1>Database Faiiled</h1><br> " . $this->conn->connect_error);
        }
        else $this->CONNECTED = TRUE;
    }

    /**
     * @param $query Query String to be excuted
     * @return bool|mysqli_result
     * http://php.net/manual/en/class.mysqli-result.php
     * $query  String to be excuted
     * Can be used for DML and DDC
     * return --
     * bool:
     * will return True if success
     * or false for failure
     * NOTE SECURITY WARNING
     * ONLY TO BE USED WHEN NO INPUT OR VARIABLES ARE USED IN QUERY TO PREVENT SQL INJECTION
     */
    public function Query($query)
    {

        $result = $this->conn->query($query);
        return $result;
    }


    /**
     * Close Connection
     */
    public function __destruct()
    {
        $this->conn->close();
    }

    /**
     * @return String
     * Ensure String before return as may be malicious
     */
    public function GetError()
    {
        if (is_string($this->ERROR_MESSAGE)) {
            return $this->ERROR_MESSAGE;
        } else {
            return "Error was not in string format";
        }
    }

    /**
     * @return bool
     */
    public function isCONNECTED()
    {
        return $this->CONNECTED;
    }


      public function getConn()
      {
         return $this->conn;
      }


    public static function Instance()
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new DatabaseOperations("localhost", "root", "", "barbers");
        }
        return $inst;
    }


}