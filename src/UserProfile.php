<?php
namespace SOS;
use \TymFrontiers\InstanceError,
    \TymFrontiers\Validator,
    \TymFrontiers\Generic,
    \TymFrontiers\MultiForm,
    \TymFrontiers\File,
    \TymFrontiers\Data;

trait UserProfile {
  public $id;
  public $name;
  public $surname;
  public $sex;
  public $dob;
  public $avatar;
  public $country_code;
  public $state_code;
  public $city_code;
  public $zip_code;
  public $address;

  protected $_updated;


  public static function find($user, string $by="id"){
    static::_checkEnv();
    global $database;
    $by = \in_array( \strtolower($by),["email","id","phone"]) ? \strtolower($by) : "id";
    $in = [];
    if( \is_array($user) ){
      foreach($user as $usr){
        $in[] = $database->escapeValue($usr);
      }
    }else{ $in[] = $database->escapeValue($user); }
    $in = "'" . \implode("','",$in) . "'";
    $whost = WHOST;
    $data_db = MYSQL_DATA_DB;
    $file_db = MYSQL_FILE_DB;
    $file_tbl = MYSQL_FILE_TBL;
    $prefix = self::PREFIX;
    $sql = "SELECT usr._id AS id, usr.status, usr.email, usr.phone, usr._created,
                   usrp.name, usrp.surname,
                   usrp.sex, usrp.dob, usrp._updated,
                   usrp.address, usrp.zip_code,
                   c.code AS country_code, c.name AS country,
                   s.name AS state, s.code AS state_code,
                   ci.name AS city,ci.code AS city_code,
                   (
                     SELECT CONCAT('{$whost}','/file/',f._name)
                   ) AS avatar

            FROM :db:.:tbl: AS usr
            LEFT JOIN :db:.user_profile AS usrp ON usrp.user = usr._id
            LEFT JOIN {$data_db}.country AS c ON c.code = usrp.country_code
            LEFT JOIN {$data_db}.state AS s ON s.code = usrp.state_code
            LEFT JOIN {$data_db}.city AS ci ON ci.code = usrp.city_code
            LEFT JOIN :db:.setting AS stt ON stt.user=usr._id AND stt.skey='USER.AVATAR'
            LEFT JOIN `{$file_db}`.`{$file_tbl}` AS f ON f.id = stt.sval ";
    if( $by == 'id' ){
      $sql .= " WHERE usr._id IN({$in}) ";
    }elseif( $by == 'email' ){
      $sql .= " WHERE usr.email IN({$in}) ";
    }else{
      $sql .= " WHERE usr.phone IN({$in}) ";
    }
    $found =  self::findBySql($sql);
    if ($found) {
      foreach ($found as $i=>$obj) {
        $found[$i]->avatar = (!empty($obj->avatar) && Generic::urlExist($obj->avatar))
          ? $obj->avatar
          : $whost . \strtolower("/assets/img/{$obj->sex}-avatar.png");
      }
    }
    return $found;
  }
  private function _createProfile(string $user, array $prop, \TymFrontiers\MySQLDatabase $conn) {
    $profile = new MultiForm(MYSQL_BASE_DB, 'user_profile', 'user');
    $profile->user = $user;
    foreach ($prop as $prop=>$value) {
      if (\property_exists($profile,$prop) && !empty($value)) {
        $profile->$prop = $value;
      }
    }
    return $profile->create($conn);
  }
}
