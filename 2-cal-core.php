<?php
class Calendar {
  // (A) CONSTRUCTOR - CONNECT TO DATABASE
  private $pdo = null;
  private $stmt = null;
  public $error = "";
  function __construct () {
    try {
      $this->pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASSWORD, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
    } catch (Exception $ex) { exit($ex->getMessage()); }
  }

  // (B) DESTRUCTOR - CLOSE DATABASE CONNECTION
  function __destruct () {
    if ($this->stmt!==null) { $this->stmt = null; }
    if ($this->pdo!==null) { $this->pdo = null; }
  }

  // (C) HELPER - EXECUTE SQL QUERY
  function exec ($sql, $data=null) {
    try {
      $this->stmt = $this->pdo->prepare($sql);
      $this->stmt->execute($data);
      return true;
    } catch (Exception $ex) {
      $this->error = $ex->getMessage();
      return false;
    }
  }

  // (D) SAVE EVENT
  function save ($start, $end, $txt, $color, $id=null) {
    // (D1) START & END DATE QUICK CHECK
    $uStart = strtotime($start);
    $uEnd = strtotime($end);
    if ($uEnd < $uStart) {
      $this->error = "End date cannot be earlier than start date";
      return false;
    }

    // (D2) SQL - INSERT OR UPDATE
    if ($id==null) {
      $sql = "INSERT INTO `rec` (`date`, `date`, `title`, `color`) VALUES (?,?,?,?)";
      $data = [$start, $end, $txt, $color];
    } else {
      $sql = "UPDATE `rec` SET `date`=?, `date`=?, `title`=?, `color`=? WHERE `id`=?";
      $data = [$start, $end, $txt, $color, $id];
    }

    // (D3) EXECUTE
    return $this->exec($sql, $data);
  }

  // (E) DELETE EVENT
  function del ($id) {
    return $this->exec("DELETE FROM `rec` WHERE `id  `=?", [$id]);
  }

  // (F) GET rec FOR SELECTED MONTH
  function get ($month, $year) {
    // (F1) FIST & LAST DAY OF MONTH
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $dayFirst = "{$year}-{$month}-01 00:00:00";
    $dayLast = "{$year}-{$month}-{$daysInMonth} 23:59:59";

    // (F2) GET rec
    if (!$this->exec(
      "SELECT * FROM `rec` WHERE (
        (`date` BETWEEN ? AND ?)
        OR (`date` BETWEEN ? AND ?)
        OR (`date` <= ? AND `date` >= ?)
      )", [$dayFirst, $dayLast, $dayFirst, $dayLast, $dayFirst, $dayLast]
    )) { return false; }

    // $rec = [
    //  "e" => [ EVENT ID => [DATA], EVENT ID => [DATA], ... ],
    //  "d" => [ DAY => [EVENT IDS], DAY => [EVENT IDS], ... ]
    // ]
    $rec = ["e" => [], "d" => []];
    while ($row = $this->stmt->fetch()) {
      $eStartMonth = substr($row["date"], 5, 2);
      $eEndMonth = substr($row["date"], 5, 2);
      $eStartDay = $eStartMonth==$month
                 ? (int)substr($row["date"], 8, 2) : 1 ;
      $eEndDay = $eEndMonth==$month
               ? (int)substr($row["date"], 8, 2) : $daysInMonth ;
      for ($d=$eStartDay; $d<=$eEndDay; $d++) {
        if (!isset($rec["d"][$d])) { $rec["d"][$d] = []; }
        $rec["d"][$d][] = $row["id"];
      }
      $rec["e"][$row["id"]] = $row;
      $rec["e"][$row["id"]]["first"] = $eStartDay;
    }
    return $rec;
  }
}

// (G) DATABASE SETTINGS - CHANGE TO YOUR OWN!
define("DB_HOST", "localhost");
define("DB_NAME", "event");
define("DB_CHARSET", "utf8");
define("DB_USER", "root");
define("DB_PASSWORD", "");

// (H) NEW CALENDAR OBJECT
$_CAL = new Calendar();
