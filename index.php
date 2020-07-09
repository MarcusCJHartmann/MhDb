<?php
include("database.class.php");

use \MhDb\DatabaseUtils;
use \MhDb\DatabaseConfig;
use \MhDb\SqlStatement;

$db= new DatabaseConfig("localhost","world","root","");
DatabaseUtils::addDatabaseConfig("main",$db);
DatabaseUtils::setDefaultDatabaseConfig("main");

$statement = new SqlStatement();

$statement->prepared(false);
$statement->select("*")->from("city")->where("CountryCode","=","DEU")->and()->where("District","!=","–")->and()->where("Population","<","100000")->orderBy("Population DESC");
print_r($statement->fetch());