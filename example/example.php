<?php
include("../database.class.php");
include ("book.class.php");

use \MhDb\DatabaseUtils;
use \MhDb\DatabaseConfig;
use \MhDb\SqlStatement;
use MhDb\SqlResultSet;

$db= new DatabaseConfig("localhost","buchladen","root","");
DatabaseUtils::addDatabaseConfig("main",$db);
DatabaseUtils::setDefaultDatabaseConfig("main");

$statement = new SqlStatement();

$statement->setPrepared(true);
$statement->select("*")->from("books")->where("title","=","test")->and()->where("id","!=",1);


$result=new SqlResultSet(array(1,2,3,4,5));
for($i=0;$i<$result->length();$i++){
    print_r($result->get($i));    
}