<?php

namespace MhDb;

/**
 *
 * @author MarcusCJHartmann
 *         Helper to store multiple database credentials
 */
abstract class DatabaseUtils {
    private static $databaseConfigs = array ();
    private static $defaultDatabaseConfig;
    
    /**
     *
     * @param string $name
     * @param DatabaseConfig $database
     */
    public static function addDatabaseConfig($name, DatabaseConfig $database) {
        self::$databaseConfigs [$name] = $database;
    }
    
    /**
     *
     * @param string $name
     * @throws \Exception
     */
    public static function setDefaultDatabaseConfig(string $name) {
        if (key_exists ( $name, self::$databaseConfigs )) {
            self::$defaultDatabaseConfig = $name;
        } else {
            throw new \Exception ( "DatabaseConfig with '$name' does not exist and therefore can not be used as default" );
        }
    }
    
    /**
     *
     * @throws \Exception
     * @return mixed
     */
    public static function getDefaultDatabaseConfig() {
        if (key_exists ( self::$defaultDatabaseConfig, self::$databaseConfigs )) {
            return self::$databaseConfigs [self::$defaultDatabaseConfig];
        }
        throw new \Exception ( "Default DatabaseConfig is not set." );
    }
    
    /**
     *
     * @param string $name
     * @throws \Exception
     * @return mixed
     */
    public static function getDatabaseConfig($name) {
        if (key_exists ( $name, self::$databaseConfigs )) {
            return self::$databaseConfigs [$name];
        }
        throw new \Exception ( "DatabaseConfig with name '$name' does not exist." );
    }
    
    /**
     *
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    public static function startsWith($haystack, $needle) {
        $length = strlen ( $needle );
        return (substr ( $haystack, 0, $length ) === $needle);
    }
    
    /**
     *
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    public static function endsWith($haystack, $needle) {
        $length = strlen ( $needle );
        if ($length == 0) {
            return true;
        }
        return (substr ( $haystack, - $length ) === $needle);
    }
}
abstract class SqlSanitizer {
    public static function sanitize($input) {
        if (is_array ( $input )) {
            foreach ( $input as $key => $value ) {
                $input [$key] = SqlSanitizer::sanitize ( $value );
            }
            return $input;
        } elseif (is_string ( $input )) {
            $input = htmlspecialchars ( $input, ENT_QUOTES );
            $input = str_replace ( "\n", "", $input );
            return $input;
        } else {
            return $input;
        }
    }
}

/**
 *
 * @author MarcusCJHartmann
 *         Class to manage the login credentials of a single database.
 *         Handles the PDO execution.
 *         Should not be touched by the developer directly.
 */
class DatabaseConfig {
    private $type = "mysql";
    private $dbhost;
    private $dbname;
    private $dbuser;
    private $dbpass;
    private $pdo;
    function __construct($dbhost, $dbname, $dbuser, $dbpass) {
        $this->dbhost = $dbhost;
        $this->dbname = $dbname;
        $this->dbuser = $dbuser;
        $this->dbpass = $dbpass;
    }
    
    /**
     * Do not use this Method directly to execute your SqlStatement.
     * Use <code>SqlStatement::fetch()</code> or <code>SqlStatement::fetchAs($class)</code> instead.
     *
     * @param SqlStatement $sqlStatement
     *        	to be executed
     * @throws \PDOException thrown on pdo errors
     * @return NULL|\MhDb\SqlResultSet ResultObject that contains database results. Can be iterated with foreach
     */
    function executeStatement(SqlStatement &$sqlStatement) {
        $charset = 'utf8mb4';
        
        $dsn = "mysql:host=$this->dbhost;dbname=$this->dbname;charset=$charset";
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        try {
            $pdo = new \PDO ( $dsn, $this->dbuser, $this->dbpass, $options );
            
            $result = null;
            
            if ($sqlStatement->isPrepared ()) {
                
                $pdostmt = $pdo->prepare ($sqlStatement->getStatement ());
                
                $pdostmt->execute ( $sqlStatement->getPreparedData () );
                
                if ("SELECT" == $sqlStatement->getCrudType ()) {
                    if ($sqlStatement->getFetchClass () != null) {
                        $resultSet = $pdostmt->fetchAll ( \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $sqlStatement->getFetchClass () );
                    } else {
                        $resultSet = $pdostmt->fetchAll ( \PDO::FETCH_ASSOC );
                    }
                    
                    $out = array ();
                    foreach ( $resultSet as $row ) {
                        $out [] = $row;
                    }
                    $pdo = null;
                    $result = new SqlResultSet ( $out );
                    return $result;
                }
            } else {
                
                
                if ($sqlStatement->getFetchClass () != null) {
                    
                    $resultSet = $pdo->query ( $sqlStatement->getStatement (), \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $sqlStatement->getFetchClass () );
                } else {
                    $resultSet = $pdo->query ( $sqlStatement->getStatement (), \PDO::FETCH_ASSOC );
                }
                if ("SELECT" == $sqlStatement->getCrudType ()) {
                    $out = array ();
                    foreach ( $resultSet as $row ) {
                        $out [] = $row;
                    }
                    $pdo = null;
                    $result = new SqlResultSet ( $out );
                    unset ( $sqlStatement );
                    return $result;
                }
            }
        } catch ( \PDOException $e ) {
            throw new \PDOException ( $e->getMessage (), ( int ) $e->getCode () );
        }
    }
}

/**
 *
 * @author Marcus
 *
 *
 */
class SqlColumn {
    private $column;
    function __construct($column) {
        $this->column = $column;
    }
    public function get() {
        return $this->column;
    }
    public function __toString() {
        return $this->column;
    }
}

/**
 *
 * @author MarcusCJHartmann
 *         Class to create a SQL-Statement, execute it and get a result set from the database. Use this to talk to your database!
 */
class SqlStatement {
    private $statementArgs = array ();
    private $crudType = "";
    private $lastExpression = "";
    private $preparedData = array ();
    private $enableConsumption = true;
    private $consumed = false;
    private $fetchClass = null;
    private $prepared = true;
    private $sanitizeSqlStatements = true;
    private $database = null;
    
    /**
     *
     * @param DatabaseConfig $database
     */
    public function __construct(DatabaseConfig $database = null) {
        if ($database != null) {
            $this->database = $database;
        } else {
            if (DatabaseUtils::getDefaultDatabaseConfig () != null) {
                $this->database = DatabaseUtils::getDefaultDatabaseConfig ();
            }
        }
    }
    
    /**
     * load a database by alias from DatabaseUtils
     *
     * @param string $name
     */
    public function useDatabase($name) {
        $this->database = DatabaseUtils::getDatabase ( $name );
    }
    
    /**
     * returns true if SqlStatement will be executed as prepared
     *
     * @return boolean
     */
    public function isPrepared() {
        return $this->prepared;
    }
    
    /**
     *
     * @return string
     */
    public function getCrudType() {
        return $this->crudType;
    }
    
    /**
     *
     * @param boolean $bool
     */
    public function enableConsumption($bool) {
        $this->enableConsumption = $bool;
    }
    
    /**
     *
     * @param boolean $bool
     * @throws \Exception
     * @return \MhDb\SqlStatement
     */
    public function setPrepared($bool) {
        if (count ( $this->statementArgs ) != 0) {
            throw new \Exception ( "Prepared state of a SqlStatement can not be changed after adding expressions" );
        }
        $this->prepared = $bool;
        return $this;
    }
    
    /**
     *
     * @param string|array $columns
     *        	the columns to be selected, can be passed as String or Array
     * @return \MhDb\SqlStatement
     */
    public function select($columns) {
        $this->crudType = "SELECT";
        $select = "";
        if (is_array ( $columns )) {
            $columns = implode ( ",", $columns );
        }
        $select = "SELECT " . $columns;
        $this->addExpression ( "SELECT", $select );
        
        return $this;
    }
    
    /**
     *
     * @param string $table
     *        	the name of the table to be updated
     * @return \MhDb\SqlStatement
     */
    public function update($table) {
        $this->crudType = "UPDATE";
        $this->addExpression ( "UPDATE", "UPDATE " . $table );
        return $this;
    }
    
    /**
     *
     * @param array $update
     *        	data to be set in update expression
     * @return \MhDb\SqlStatement
     */
    public function set(Array $update) {
        if ($this->sanitizeSqlStatements) {
            $update = SqlSanitizer::sanitize ( $update );
        }
        $this->addExpression ( "SET", "SET " . http_build_query ( $update, "", "," ) );
        return $this;
    }
    
    /**
     *
     * @return \MhDb\SqlStatement
     */
    public function delete() {
        $this->crudType = "DELETE";
        $this->addExpression ( "DELETE", "DELETE " );
        return $this;
    }
    
    /**
     *
     * @param string|array $tables
     *        	tables to delete from
     * @return \MhDb\SqlStatement
     */
    public function deleteFrom($tables) {
        $this->crudType = "DELETE";
        $deletefrom = "";
        if (is_array ( $tables )) {
            $tables = implode ( ",", $tables );
        }
        $deletefrom = "DELETE FROM " . $tables;
        $this->addExpression ( "DELETE", $deletefrom );
        
        return $this;
    }
    
    /**
     *
     * @param string $table
     *        	table name to insert into
     * @param array $columns
     *        	array of columns to be inserted
     * @return \MhDb\SqlStatement
     */
    public function insertInto($table, array $columns) {
        $this->crudType = "INSERT";
        $this->addExpression ( "INSERT", "INSERT INTO " . $table . "(" . implode ( ",", $columns ) . ")" );
        return $this;
    }
    
    /**
     *
     * @param array $valuesArgs
     *        	array of values to be inserted into table
     * @return \MhDb\SqlStatement
     */
    public function values(array $valuesArgs) {
        if ($this->sanitizeSqlStatements) {
            $valuesArgs = SqlSanitizer::sanitize ( $valuesArgs );
        }
        $values = "";
        
        if ("VALUES" != $this->lastExpression) {
            $values = "VALUES ";
        } else {
            $index = count ( $this->statementArgs ) - 1;
            $this->statementArgs [$index] = $this->statementArgs [$index] . ",";
        }
        
        if ($this->prepared) {
            $values .= "(" . implode ( ",", array_fill ( 0, count ( $valuesArgs ), "?" ) ) . ")";
            $this->addExpression ( "VALUES", $values );
            foreach ( $valuesArgs as $entry ) {
                $this->preparedData [] = $entry;
            }
        } else {
            array_walk($valuesArgs, function(&$value, $key) { $value = '"'.$value.'"'; });
            $values .= "(" . implode ( ",", $valuesArgs ) . ")";
            $this->addExpression ( "VALUES", $values );
        }
        
        return $this;
    }
    
    /**
     *
     * @param string|array $tables
     * @return \MhDb\SqlStatement
     */
    public function from($tables) {
        $from = "";
        if (is_array ( $tables )) {
            $tables = implode ( ",", $tables );
        }
        $from = "FROM " . $tables;
        $this->addExpression ( "FROM", $from );
        
        return $this;
    }
    
    /**
     *
     * @param string $column
     * @param string $operator
     *        	typical operators like =, =<, >=, !=
     * @param string|SqlColumn $value
     * @return \MhDb\SqlStatement
     */
    public function where($column, $operator, $value) {
        if ($this->sanitizeSqlStatements) {
            $value = SqlSanitizer::sanitize ( $value );
        }
        if ("AND" == $this->lastExpression || "OR" == $this->lastExpression) {
            $where = "";
        } else {
            $where = "WHERE ";
        }
        if ($this->prepared) {
            
            $where .= $column . " " . $operator . " ?";
            $this->addExpression ( "WHERE", $where );
            $this->preparedData [] = $value;
        } else {
            if (! ($value instanceof SqlColumn)) {
                $value = "'" . $value . "'";
            }
            $where .= $column . " " . $operator . " " . $value;
            $this->addExpression ( "WHERE", $where );
        }
        return $this;
    }
    
    /**
     *
     * @return \MhDb\SqlStatement
     */
    public function and() {
        $this->addExpression ( "AND", "AND" );
        return $this;
    }
    
    /**
     *
     * @return \MhDb\SqlStatement
     */
    public function or() {
        $this->addExpression ( "OR", "OR" );
        return $this;
    }
    
    /**
     *
     * @param string $table
     * @throws \Exception
     */
    public function leftJoin($table) {
        $this->addExpression ( "LEFT JOIN", "LEFT JOIN " . $table );
        return $this;
    }
    
    /**
     *
     * @param string $table
     * @return \MhDb\SqlStatement
     */
    public function rightJoin($table) {
        $this->addExpression ( "RIGHT JOIN", "RIGHT JOIN " . $table );
        return $this;
    }
    
    /**
     *
     * @param string $table
     * @return \MhDb\SqlStatement
     */
    public function innerJoin($table) {
        $this->addExpression ( "INNER JOIN", "INNER JOIN " . $table );
        return $this;
    }
    
    /**
     *
     * @param string|SqlColumn $value1
     * @param string $operator
     *        	typical operators like =, =<, >=, !=
     * @param string|SqlColumn $value2
     * @return \MhDb\SqlStatement
     */
    public function on($value1, $operator, $value2) {
        $this->addExpression ( "ON", "ON $value1 $operator $value2" );
        return $this;
    }
    
    /**
     *
     * @param string|array $columns
     * @return \MhDb\SqlStatement
     */
    public function orderBy($columns) {
        if (is_array ( $columns )) {
            $columns = implode ( ",", $columns );
        }
        $this->addExpression ( "ORDER BY", "ORDER BY " . $columns );
        return $this;
    }
    
    /**
     * alias to <code>SqlStatement::orderBy()</code>
     *
     * @param string|array $columns
     * @return \MhDb\SqlStatement
     */
    public function sortBy($columns) {
        return $this->orderBy ( $columns );
    }
    
    /**
     *
     * @param string|array $columns
     * @return \MhDb\SqlStatement
     */
    public function groupBy($columns) {
        if (is_array ( $columns )) {
            $columns = implode ( ",", $columns );
        }
        $this->addExpression ( "GROUP BY", "GROUP BY " . $columns );
        return $this;
    }
    
    public function limit($limit,$offset=null){
        $exp=$limit;
        if($offset!=null){
            $exp=$limit.", ".$offset;
        }
        $this->addExpression("LIMIT","LIMIT ". $exp);
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getStatement() {
        $statement=implode ( PHP_EOL, $this->statementArgs ).";";
        if($this->crudType=="INSERT"){
            //$statement.="SELECT LAST_INSERT_ID();";
        }
        return $statement;
    }
    
    /**
     *
     * @return array
     */
    public function getPreparedData() {
        return $this->preparedData;
    }
    
    /**
     *
     * @return \MhDb\SqlStatement
     */
    public function dump() {
        echo $this->getStatement ();
        return $this;
    }
    
    /**
     *
     * @throws \Exception
     */
    public function fetch() {
        if ($this->database == null) {
            throw new \Exception ( "Missing database in SqlStatement. Make sure to set a default database in DatabaseUtils or give Database Object to SqlStatement" );
        }
        $result = $this->database->executeStatement ( $this );
        if ($this->enableConsumption) {
            $this->setConsumed ();
        }
        return $result;
    }
    
    /**
     * Alias of <code>SqlStatement::fetch()</code>
     *
     * @return NULL|\MhDb\SqlResultSet
     */
    public function query() {
        return $this->fetch ();
    }
    
    /**
     *
     * @param string $class
     *        	class name to map the result with
     * @return NULL|\MhDb\SqlResultSet
     */
    public function fetchAs($class = null) {
        if ($class != null) {
            $this->fetchClass = $class;
        }
        return $this->fetch ();
    }
    
    /**
     * Alias of <code>SqlStatement::fetchAs()</code>
     *
     * @param string $class
     * @return NULL|\MhDb\SqlResultSet
     */
    public function queryAs($class) {
        return $this->fetchAs ( $class );
    }
    
    /**
     *
     * @return string the class name to match the database results to a class
     */
    public function getFetchClass() {
        return $this->fetchClass;
    }
    
    /**
     *
     * @param string $sqlExpression
     * @param string $expressionString
     */
    private function addExpression($sqlExpression, $expressionString) {
        $this->checkIfConsumed ();
        $this->statementArgs [] = $expressionString;
        $this->lastExpression = $sqlExpression;
    }
    
    /**
     *
     * @throws \Exception
     */
    private function checkIfConsumed() {
        if ($this->consumed && $this->enableConsumption) {
            throw new \Exception ( "SqlStatement has been already consumed by a former database action and must not be reused" );
        }
    }
    
    /**
     */
    private function setConsumed() {
        $this->consumed = true;
    }
}

/**
 * A container class that is returned by <code>SqlStatement::fetch</code> or <code>SqlStatement::fetchAs</code>.
 * Can be iterated by foreach loop
 *
 * @author MarcusCJHartmann
 */
class SqlResultSet implements \Iterator {
    private $resultSet = array ();
    function __construct($resultSet) {
        $this->resultSet = $resultSet;
    }
    public function rewind() {
        reset ( $this->resultSet );
    }
    public function current() {
        $current = current ( $this->resultSet );
        return $current;
    }
    public function key() {
        $key = key ( $this->resultSet );
        return $key;
    }
    public function next() {
        $next = next ( $this->resultSet );
        return $next;
    }
    public function valid() {
        $valid = $this->current () !== false;
        return $valid;
    }
    public function length() {
        return count ( $this->resultSet );
    }
    public function get($index) {
        return $this->resultSet [$index];
    }
}
