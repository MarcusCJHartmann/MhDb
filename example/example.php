<?php
include("../database.class.php");
include ("book.class.php");

use \MhDb\DatabaseUtils;
use \MhDb\DatabaseConfig;
use \MhDb\SqlStatement;

$db= new DatabaseConfig("localhost","buchladen","root","");
DatabaseUtils::addDatabaseConfig("main",$db);
DatabaseUtils::setDefaultDatabaseConfig("main");

$statement = new SqlStatement();

$statement->prepared(true);
$statement->select("*")->from("books")->where("title","=","test")->and()->where("id","!=",1);
$result=$statement->fetchAs("book");
foreach($result as $key=>$entry){
	print_r($entry);
}