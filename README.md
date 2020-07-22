MhDb is a PHP library for creating and executing clean and secure MySQL-Statements width PDO.

# Requirements

## PHP Verison and extensions
MhDb is supported by PHP >7.0

PHP PDO has to be loaded.

## Quickstart

- require library file
`require('path/to/database.class.php');`

- create a Database Object
`$database= new MhDb\DatabaseConfig(dbhost,dbname,dbuser,dbpass);`

- create a SqlStatement Object
`$statement= new MhDb\SqlStatement($database);`

- add sql expression to your statement
`$statment=$statement->select('*')->from('tables')->where('column','value);`

- execute statement to receive associative array
`$result= $statement->fetch();`

- iterate over $result to work with entries
`foreach($result as $key=>$entry){ /*code here */ }`

By default a sqlStatement is executed as prepared statement to prevent SQL injections.
You can disable this before adding expressions by calling
`$statement->prepared(false);`
Warning: you actually should not do this.

By default a sqlStatement can only be used one time and is then marked as consumed. It can not be reused afterwards. You can disable this by calling `$statement->enableConsumption(false);`


## more extended usage
MhDB brings a set of tools to handle multiple databases at a time without creating and destroying PDOs manually.

### DatabaseConfig
`MhDb\DatabaseConfig` is a DatabaseConfig Object to store database credentials and handle the PDO-stuff. It expects `$dbhost,$dbname,$dbuser,$dbpass` on construction.

`MhDb\DatabaseConfig::executeStatement` should never be called by you. Use `SqlStatement::fetch()` instead

### DatabaseUtils
`MhDb\DatabaseUtils` is an abstract class to handle multiple database credentials at a time.

`MhDb\DatabaseUtils::addDatabaseConfig($name,$database)` expects a database alias and a DatabaseConfig-Object.

`MhDb\DatabaseUtils::setDefaultDatabaseConfig($name)` sets a existing DatabaseConfig as default to be used by each SqlStatement without a sepcified database.

`MhDb\DatabaseUtils::getDefaultDatabaseConfig()` returns the default DatabaseConfig object.

`MhDb\DatabaseUtils::getDatabaseConfig($name)` returns a DatabaseConfig by its alias

`MhDb\DatabaseUtils::startsWith($haystack,$needle)` is a helper to check the beginning of a string for a substring. Always usefull.

`MhDb\DatabaseUtils::endsWith($haystack,$needle` is a helper to check the ending of a string for a substring. Always usefull.

### SqlStatement
`MhDb\SqlStatement` is the Object to work with when creating SQl-Statements and executing them. Optionally you can pass a DatabaseConfig-Object to the constructor, else it will load the default DatabaseConfig from `MhDb\DatabaseUtils`

`MhDb\SqlStatement::useDatabase($name)` will load the DatabaseConfig with matching Alias from `MhDb\DatabaseUtils`

`MhDb\SqlStatement::isPrepared()` returns true, if Statement will be executed as prepared.

`MhDb\SqlStatement::getCrudType()` returns last used CRUD-Expression.

`MhDb\SqlStatement::enableConsumption($bool)` enables or disables consumption of Statement by true or false. 

`MhDb\SqlStatement::prepared($bool)` enables or disables prepared statement execution. Use with caution.

`MhDb\SqlStatement::[usualSqlExpression]` does, what it does in SQL...

`MhDb\SqlStatement::sortBy` is an alias for `MhDb\SqlStatement::orderBy`

`MhDb\SqlStatement::getStatement()` returns current SQL-Statement as String. You can not continue adding expressions.

`MhDb\SqlStatement::dump()` echoes current state of SQL-Statement-String. You can go on with adding expressions.

`MhDb\SqlStatement::fetch()` executes the sql statement and returns some good database content as associative array.

`MhDb\SqlStatement::query()` alias to `MhDb\SqlStatement::fetch()`





