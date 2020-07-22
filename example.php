<?php
include("database.class.php");

use \MhDb\DatabaseUtils;
use \MhDb\DatabaseConfig;
use \MhDb\SqlStatement;

$db= new DatabaseConfig("localhost","buchladen","root","");
DatabaseUtils::addDatabaseConfig("main",$db);
DatabaseUtils::setDefaultDatabaseConfig("main");

$statement = new SqlStatement();

$statement->prepared(false);
$statement->select("*")->from("buecher")->where("titel","=","test")->and()->where("id","!=",1);
$result=$statement->fetch();
foreach($result as $key=>$entry){
	print_r($entry);
}