<?php
namespace MhDb;

abstract class DatabaseUtils {
	private static $databaseConfigs=array();
	private static $defaultDatabaseConfig;
	
	public static function addDatabaseConfig($name,$database){
		self::$databaseConfigs[$name]=$database;
	}
	
	public static function setDefaultDatabaseConfig($name){
		if(key_exists($name,self::$databaseConfigs)){
		self::$defaultDatabaseConfig=$name;
		}else{
			throw new \Exception("DatabaseConfig with '$name' does not exist and therefore can not be used as default");
		}
	}
	
	public static function getDefaultDatabaseConfig(){
		if(key_exists(self::$defaultDatabaseConfig,self::$databaseConfigs)){
		return self::$databaseConfigs[self::$defaultDatabaseConfig];
		}
		throw new \Exception("Default DatabaseConfig is not set.");
	}
	
	public static function getDatabaseConfig($name){
		if(key_exists($name,self::$databaseConfigs)){
		return self::$databaseConfigs[$name];
		}
		throw new \Exception("DatabaseConfig with name '$name' does not exist.");
	}
	
	public static function startsWith($haystack, $needle)
	{
		 $length = strlen($needle);
		 return (substr($haystack, 0, $length) === $needle);
	}

	public static function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
		return (substr($haystack, -$length) === $needle);
	}
}

class DatabaseConfig {
	private $type="mysql";
	private $dbhost;
	private $dbname;
	private $dbuser;
	private $dbpass;
	private $pdo;
	
	function __construct($dbhost,$dbname,$dbuser,$dbpass){
		$this->dbhost=$dbhost;
		$this->dbname=$dbname;
		$this->dbuser=$dbuser;
		$this->dbpass=$dbpass;
	}
	
	function executeStatement(SqlStatement &$sqlStatement){
		$charset = 'utf8mb4';
		
		$dsn = "mysql:host=$this->dbhost;dbname=$this->dbname;charset=$charset";
		$options = [
			\PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
			\PDO::ATTR_EMULATE_PREPARES   => false,
		];		
		
		try {
			$pdo = new \PDO($dsn, $this->dbuser, $this->dbpass,$options);
			
			$result=null;
			
			
			if($sqlStatement->isPrepared()){
				$pdostmt=$pdo->prepare($sqlStatement->getStatement());
				
				
				$pdostmt->execute($sqlStatement->getPreparedData());
				
				if("SELECT"==$crudType=$sqlStatement->getCrudType()){
					
					if($sqlStatement->getFetchClass()!=null){
						$resultSet = $pdostmt->fetchAll(\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE, $sqlStatement->getFetchClass());
					}else{
						$resultSet = $pdostmt->fetchAll(\PDO::FETCH_ASSOC);
					}
					$out=array();
					foreach($resultSet as $row){
						$out[]=$row;
					}
					$pdo=null;
					$result= new SqlResultSet($out);
					return $result;
				}
			}else{
				if($sqlStatement->getFetchClass()!=null){
						$resultSet = $pdo->query($sqlStatement->getStatement(),\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE, $sqlStatement->getFetchClass());
					}else{
						$resultSet=$pdo->query($sqlStatement->getStatement(),\PDO::FETCH_ASSOC);
					}
				
				if("SELECT"==$crudType=$sqlStatement->getCrudType()){
					$out=array();
					foreach($resultSet as $row){
						$out[]=$row;
					}
					$pdo=null;
					$result= new SqlResultSet($out);
					unset($sqlStatement);
					return $result;
				}
			}		
		} catch (\PDOException $e) {
			 throw new \PDOException($e->getMessage(), (int)$e->getCode());
		}
	}
}

class SqlStatement {
	private $statementArgs=array();
	private $crudType="";
	private $lastExpression="";
	private $preparedData=array();
	private $enableConsumption=true;
	private $consumed=false;
	private $fetchClass=null;
	
	private $prepared=true;
	private $sanitizeSqlStatements=true;
	private $database=null;
	
	public function __construct($database=null){
		if($database!=null){
			$this->database=$database;
		}else{
			if (DatabaseUtils::getDefaultDatabaseConfig()!=null){
				$this->database=DatabaseUtils::getDefaultDatabaseConfig();
			}
		}
	}

	public function useDatabase($name){
		$this->database=DatabaseUtils::getDatabase($name);
	}
	
	public function isPrepared(){
		return $this->prepared;
	}
	
	public function getCrudType(){
		return $this->crudType;
	}
	
	public function enableConsumption($bool){
		$this->enableConsumption=$bool;
	}
	
	public function prepared($bool){
		$this->prepared=$bool;
		return $this;
	}
	
	public function select($columns){
		$this->crudType="SELECT";
		$select="";
		if(is_array($columns)){
			$columns=implode(",",$columns);
		}
		$select="SELECT ".$columns;
		
		$this->addExpression("SELECT",$select);
		
		return $this;
	}
	
	public function update($table){
		$this->crudType="UPDATE";
		$this->addExpression("UPDATE","UPDATE ".$table);
		return $this;
	}
	
	public function set(Array $update){
		$this->addExpression("SET","SET ".http_build_query($update,"",","));
		return $this;
	}
	
	public function delete(){
		$this->crudType="DELETE";
		$this->addExpression("DELETE","DELETE ");
		return $this;
	}
	
	public function deleteFrom ($tables){
		$this->crudType="DELETE";
		$deletefrom="";
		if(is_array($tables)){
			$tables=implode(",",$tables);
		}
		$deletefrom="DELETE FROM ".$tables;
		$this->addExpression("DELETE",$deletefrom);
		
		return $this;
	}
	
	public function insertInto($table,array $columns){
		$this->crudType="INSERT";
		$this->addExpression("INSERT","INSERT INTO ".$table. "(".implode(",",$columns).")");
		return $this;
	}
	public function values(array $valuesArgs){
		$values="";
		
		if("VALUES"!=$this->lastExpression){
			$values="VALUES ";
		}else{
			$index = count( $this->statementArgs ) - 1;
			$this->statementArgs[$index]=$this->statementArgs[$index].",";
		}
		
		if($this->prepared){
			$values.="(". implode(",",array_fill(0,count($valuesArgs),"?")).")";
			$this->addExpression("VALUES",$values);
			foreach($valuesArgs as $entry){
				$this->preparedData[]=$entry;
			}
		}else{
			$values.="(".implode(",",$valuesArgs).")";
			$this->addExpression("VALUES",$values);
		}

		return $this;
	}
	
	public function from ($tables){
		$from="";
		if(is_array($tables)){
			$tables=implode(",",$tables);
		}
		$from="FROM ".$tables;
		$this->addExpression("FROM",$from);
		
		return $this;
	}
	
	public function where($column,$operator,$value){
		if("AND"==$this->lastExpression||"OR"==$this->lastExpression){
			$where="";
		}else{
			$where="WHERE ";
		}
		if($this->prepared){
				
			$where.=$column." ".$operator." ?";
			$this->addExpression("WHERE",$where);
			$this->preparedData[]=$value;
		}else{
			$where.=$column." ".$operator." '".$value."'";
			$this->addExpression("WHERE",$where);
		}
		return $this;
	}
	
	public function and(){
		$this->addExpression("AND","AND");
		return $this;
	}
	
	public function or(){
		$this->addExpression("OR","OR");
		return $this;
	}
	
	public function leftJoin($table){
		$this->addExpression("LEFT JOIN","LEFT JOIN ".$table);
		return $this;
	}
	
	public function rightJoin($table){
		$this->addExpression("RIGHT JOIN","RIGHT JOIN ".$table);
		return $this;
	}
	
	public function innerJoin($table){
		$this->addExpression("INNER JOIN","INNER JOIN ".$table);
		return $this;
	}
	
	public function on($value1,$operator,$value2){
		$this->addExpression("ON","ON $value1 $operator $value2");
		return $this;
	}
	
	public function orderBy($columns){
		if(is_array($columns)){
			$columns=implode(",",$columns);
		}
		$this->addExpression("ORDER BY","ORDER BY ".$columns);
		return $this;
	}
	//alias for orderBy
	public function sortBy($columns){
		return $this->orderBy($columns);
	}
	
	public function groupBy($columns){
		if(is_array($columns)){
			$columns=implode(",",$columns);
		}
		$this->addExpression("GROUP BY","GROUP BY ".$columns);
		return $this;
	}
	
	public function getStatement(){
		return implode(PHP_EOL,$this->statementArgs).";";
	}
	
	public function getPreparedData(){
		return $this->preparedData;
	}
	
	public function dump(){
		echo $this->getStatement();
		return $this;
	}
	
	public function fetch(){
		if($this->database==null){
			throw new \Exception("Missing database in SqlStatement. Make sure to set a default database in DatabaseUtils or give Database Object to SqlStatement");
		}
		$result=$this->database->executeStatement($this);
		if($this->enableConsumption){
			$this->setConsumed();
		}
		return $result;
	}
	//alias for fetch
	public function query(){
		return $this->fetch();
	}
	
	public function fetchAs($class=null){
		if($class!=null){
			$this->fetchClass=$class;
		}
		return $this->fetch();
	}
	
	//alias for fetch
	public function queryAs($class){
		return $this->fetchAs($class);
	}

	public function getFetchClass(){
		return $this->fetchClass;
	}
	
	private function addExpression($sqlExpression,$expressionString){
		$this->checkIfConsumed();
		$this->statementArgs[]=$expressionString;
		$this->lastExpression=$sqlExpression;
	}
	
	private function checkIfConsumed(){
		if($this->consumed&&$this->enableConsumption){
			throw new \Exception("SqlStatement has been already consumed by a former database action and must not be reused");
		}
	}
	
	private function setConsumed(){
		$this->consumed=true;
	}
}


class SqlResultSet implements \Iterator{
	private $resultSet=array();
	
	function __construct($resultSet){
		$this->resultSet =$resultSet;
	}
	
	public function rewind() {
        reset($this->resultSet);
    }

    public function current() {
        $current = current($this->resultSet);
        return $current;
    }

    public function key() {
        $key = key($this->resultSet);
        return $key;
    }

    public function next() {
        $next = next($this->resultSet);
        return $next;
    }

    public function valid() {
        $valid = $this->current() !== false;
        return $valid;
    }
	
	public function length(){
		return count($this->resultSet);
	}
}