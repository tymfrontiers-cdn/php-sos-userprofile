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
  public $alias = NULL;
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

  public static function profile(string $user_id, string $id_type="id") {
    if ($profile = self::find($user_id, $id_type)) {
      return $profile;
    }
    return null;
  }
  public static function find(string $uid){
    static::_checkEnv();
    global $database;
    $uid = $database->escapeValue($uid);
    $valid = new Validator;
    $whost = WHOST;
    $data_db = MYSQL_DATA_DB;
    $file_db = MYSQL_FILE_DB;
    $file_tbl = MYSQL_FILE_TBL;
    $prefix = self::PREFIX;
    $sql = "SELECT usr._id AS id, usr.status, usr.email, usr.phone, usr._created,
                   usrp.name, usrp.surname,
                   usrp.sex, usrp.dob, usrp._updated,
                   usrp.address, usrp.zip_code,
                   usra.alias,
                   c.code AS country_code, c.name AS country,
                   s.name AS state, s.code AS state_code,
                   ci.name AS city,ci.code AS city_code,
                   (
                     SELECT CONCAT('{$whost}','/app/file/',f._name)
                   ) AS avatar

            FROM :db:.:tbl: AS usr
            LEFT JOIN :db:.user_profile AS usrp ON usrp.user = usr._id
            LEFT JOIN :db:.user_alias AS usra ON usra.user = usr._id
            LEFT JOIN {$data_db}.country AS c ON c.code = usrp.country_code
            LEFT JOIN {$data_db}.state AS s ON s.code = usrp.state_code
            LEFT JOIN {$data_db}.city AS ci ON ci.code = usrp.city_code
            LEFT JOIN `{$file_db}`.`file_default` AS fd ON fd.`user` = usr._id AND fd.set_key = 'USER.AVATAR'
            LEFT JOIN `{$file_db}`.`{$file_tbl}` AS f ON f.id = fd.file_id
            WHERE 1=1 ";
    if ( $valid->username($uid,["uid", "username", 3, 12, [], "mixed", [".","-","_"]]) ) {
      $sql .= " AND usr._id = '{$uid}'
        OR usr._id = (
          SELECT `user`
          FROM :db:.`user_alias`
          WHERE `alias` = '{$uid}'
          LIMIT 1
        )";
    } else if ( $valid->email($uid,["email","email"]) ){
      $sql .= " AND usr.email = '{$uid}' ";
    } else if ($valid->tel($uid, ["uid", "tel"])) {
      $sql .= " AND usr.phone = '{$uid}' ";
    } else {
      return NULL;
    }
    $found =  self::findBySql($sql);
    if ($found) {
      $found = $found[0];
      $found->avatar = (!empty($found->avatar) && Generic::urlExist($found->avatar))
        ? $found->avatar
        : $whost . "/app/user/img/default-avatar.png";
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
  public static function getId(string $alias) {
    global $database;
    if ($usr = (new MultiForm(MYSQL_BASE_DB, "user_alias", "user"))->findBySql("SELECT `user` FROM :db:.:tbl: WHERE `alias` = '{$database->escapeValue($alias)}' LIMIT 1")) {
      return $usr[0]->user;
    }
    return NULL;
  }
}
