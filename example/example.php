<?php
include("../database.class.php");
include ("book.class.php");

use \MhDb\DatabaseUtils;
use \MhDb\DatabaseConfig;
use \MhDb\SqlStatement;
use MhDb\SqlResultSet;
use MhDb\SqlColumn;

$db= new DatabaseConfig("localhost","buchladen","root","");
DatabaseUtils::addDatabaseConfig("main",$db);
DatabaseUtils::setDefaultDatabaseConfig("main");

$statement = new SqlStatement();

$statement->setPrepared(false);
$value=new \MhDb\SqlColumn("test");
$statement->select("*")->from("books")->where("title","=",$value)->and()->where("id","!=",1)->dump();

$result=new SqlResultSet(array(1,2,3,4,5));
for($i=0;$i<$result->length();$i++){
    print_r($result->get($i));    
}