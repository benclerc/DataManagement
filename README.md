# Data Management

PHP Class providing simple and compact database requests. Useful for small projects without ORM. With this class you can :

* Select, insert, update, delete data
* Count rows
* Sum rows
* Use SQL transaction
* Execute custom SQL request

This class was not tested on PHP version < 7.3, thus it is not recommended to use this class on < 7.3 PHP projects.

## Table of contents

<!--ts-->
   * [Getting started](#getting-started)
   * [Documentation](#documentation)
      * [connector()](#connector)
      * [beginTransaction()](#begintransaction)
      * [commit()](#commit)
      * [rollback()](#rollback)
      * [debug()](#debug)
      * [select()](#select)
      * [customSelect()](#customselect)
      * [insert()](#insert)
      * [customInsert()](#custominsert)
      * [update()](#update)
      * [delete()](#delete)
      * [count()](#count)
      * [sum()](#sum)
      * [customSQL()](#customsql)
<!--te-->

## Getting started

1. Get [Composer](http://getcomposer.org/)
2. Install the library using composer `composer require benclerc/datamanagement`.
3. Add the following to your application's main PHP file `require 'vendor/autoload.php';`.
4. Instanciate the class with the database's connection information `$db = \DataManagement\DataManagement('pgsql', 'localhost', 5432, 'myDb', 'myUser', 'myUserPassword');`.
5. Start using the library `$books = $db->select('BOOKS')['fetchAll'];`.

## Documentation

You can find a full documentation [here](https://benclerc.github.io/DataManagement/).

### connector()

This method returns the PDO object connected to the database.

### beginTransaction()

This method starts a SQL transaction. Every call to other methods following this one will be in the transaction until you end it with `commit()` or `rollback()`.

### commit()

This method ends a SQL transaction by applying the changes. Be careful, this method won't return the state of the commit ; even if it returns `TRUE` it does not mean the commit was successful, it means the commit was successfully sent to the database. You **must** check the state of every request you do during the transaction if you want to know if your transaction was successful.

### rollback()

This method ends a SQL transaction by rolling back the changes. Nothing done during the transaction will be applied.

### debug()

This method is used to enable debug mode for the next request (only works on methods forging SQL request like `select()`, `insert()`, `update()`, `delete()`, `count()`, `sum()`). instead of executing the forged request, it will be returned as a string.

Parameters :

* $state bool : Set the value for debug state, default is TRUE.

Return value : itself.

Examples :

```php
// "SELECT * FROM BOOKS;"
$db->debug()->select('BOOKS');
// "SELECT * FROM BOOKS WHERE BOOKS.books_id = :whereBOOKSbooks_id;"
$db->debug()->select('BOOKS', NULL, NULL, ['BOOKS'=>['books_id'=>42]]);
// "SELECT * FROM BOOKS INNER JOIN AUTHORS ON BOOKS.books_refauthor = AUTHORS.authors_id ORDER BY books_name ASC;"
$db->debug()->select('BOOKS', ['books_name'], ['AUTHORS'=>['INNER', 'books_refauthor', 'authors_id']]);
// "INSERT INTO BOOKS (books_name, books_refauthor) VALUES (:books_name, :books_refauthor);"
$db->debug()->insert('BOOKS', ['books_name'=>htmlentities('Super book'), 'books_refauthor'=>42]);
// "UPDATE BOOKS SET books_name=:books_name WHERE BOOKS.books_id = :whereBOOKSbooks_id;"
$db->debug()->update('BOOKS', ['books_name'=>htmlentities('Super book 2')], ['books_id'=>42]);
// "DELETE FROM BOOKS WHERE BOOKS.books_id = :whereBOOKSbooks_id;"
$db->debug()->delete('BOOKS', ['books_id'=>42]);
// "SELECT COUNT(books_id) FROM BOOKS WHERE BOOKS.books_isavailable IS NULL;"
$db->debug()->count('BOOKS', 'books_id', ['BOOKS'=>['books_isavailable'=>TRUE]]);
// "SELECT SUM(books_pages) FROM BOOKS WHERE BOOKS.books_isavailable IS NULL;"
$db->debug()->sum('BOOKS', 'books_pages', ['BOOKS'=>['books_isavailable'=>TRUE]]);
```

### select()

This method is used to retrieve data from the database. It can be a very simple request like getting a whole table or a more complex request with ordering, table joins, filters, limits and offsets.

Parameters :

* $table string : Table name.
* $order array (optional) : Array of column name and wanted order e.g. ['column' => 'ASC/DESC']. If no value is passed then default value is used : 'ASC'.
* $join array (optional) : Array with wanted join table name as key and array of needed values as values e.g. `['table' => [type(inner, left, right ...), 'foreignkey', 'primarykey', /*from table*\]]`. From table argument is optionnal, if not set $table will be used instead.
* $where array (optional) : Array with table name as key and array as value with column name and filter value e.g. `['table'=>['columnname'=>'data']]`. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'. 'data' can also be an array of values.
* $limit int (optional) : Number of max rows e.g. 50.
* $offset int (optional) : Offset for returned rows e.g. 100.
* $columns array (optional) : Array of column name.

Return value : an array of 3 PHP arrays : 'fetch' => first result (in an array), 'fetchAll' => array of all the results, 'rowCount' => number of results.

Examples :

```php
// Get all books
$res = $db->select('BOOKS')['fetchAll'];
// Get one book, id = 42
$res = $db->select('BOOKS', NULL, NULL, ['BOOKS'=>['books_id'=>42]])['fetch']; // Note the NULL values because we do not want order or join. And also note the fetch instead of fetchAll because we know we have only one result.
// Get all books with their authors, results ordered on the book name from A to Z
$res = $db->select('BOOKS', ['books_name'], ['AUTHORS'=>['INNER', 'books_refauthor', 'authors_id']])['fetchAll'];
// Get all books with the reference in the list + their authors, results ordered on the book name from A to Z and author name from Z to A, limit to 10 results with an offset of 10 (page 2)
$referenceList = [37483, 27949, 49303, 20438];
$res = $db->select('BOOKS', ['books_name', 'authors_name'=>'DESC'], ['AUTHORS'=>['INNER', 'books_refauthor', 'authors_id']], ['BOOKS'=>['books_reference'=>$referenceList]], 10, 10)['fetchAll'];
// Get all books with their subcategories and categories
$res = $db->select('BOOKS', NULL, ['SUBCATEGORIES'=>['INNER', 'books_refsubcategory', 'subcategories_id'], 'CATEGORIES'=>['INNER', 'subcategories_refcategory', 'categories_id', 'SUBCATEGORIES']])['fetchAll']; // Note the fourth element in the element 'CATEGORIES' in the join array.
```

### customSelect()

This method is used to retrieve data from the database using a custom SQL request.

Parameters :

* $sql string : SQL request.
* $data array (optional) : Array of data e.g. `['columnname'=>'data']` or if you use `?` in the request : `['data1', 'data2']`.

Return value : an array of 3 PHP arrays : 'fetch' => first result (in an array), 'fetchAll' => array of all the results, 'rowCount' => number of results.

Examples :

```php
// For request with subqueries for example 
$res = $db->customSelect('SELECT * FROM BOOKS WHERE books_id IN (SELECT orders_refbook FROM ORDERS WHERE orders_refclient = :id);', ['id'=>42])['fetchAll'];
// Or to filter using an other operator than =
$res = $db->customSelect('SELECT * FROM BOOKS WHERE books_release > 2000-01-01')['fetchAll'];
```

### insert()

This method is used to insert data in the database. It is highly recommended to use transaction when inserting data.

Parameters :

* $table string : Table name.
* $data array : Array of data e.g. `['columnname'=>'data']`.

Return value : an array with 2 rows : 'raw' => the database's raw response, 'lastInsertId' => the last insert id.

Examples :

```php
// Simple insert, without transaction
if ($db->insert('BOOKS', ['books_name'=>htmlentities('Super book'), 'books_refauthor'=>42])['raw']) {
	echo('Success');
} else {
	echo('Error');
}

// Simple insert, with transaction
$db->beginTransaction();
$resAuthor = $db->insert('AUTHORS', ['authors_name'=>htmlentities('Super Author')]);
if ($resAuthor['raw']) {
	if ($db->insert('BOOKS', ['books_name'=>htmlentities('Super book'), 'books_refauthor'=>$resAuthor['lastInsertId']])['raw']) {
		$db->commit();
		echo('Success');
	} else {
		$db->rollback();
		echo('Error when inserting book.');
	}
} else {
	$db->rollback();
	echo('Error when inserting author.');
}
```

### customInsert()

This method is used to insert data in the database using a custom SQL request.

Parameters :

* $sql string : SQL request.
* $data array (optional) : Array of data e.g. `['columnname'=>'data']` or if you use `?` in the request : `['data1', 'data2']`.

Return value : an array with 2 rows : 'raw' => the database's raw response, 'lastInsertId' => the last insert id.

Example :

```php
// For insert using SQL functions for example
$res = $db->customInsert('INSERT INTO BOOKS (books_name, books_release) VALUES (?, NOW());', [htmlentities('Super book')]);
```

### update()

This method is used to update data in the database. It is highly recommended to use transaction when updating data.

Parameters :

* $table string : Table name.
* $data array : Array of data e.g. `['columnname'=>'data']`.
* $where array : Array of data pointing the row to update e.g. `['columnname'=>'data']`. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'. 'data' can also be an array of values.

Return value : boolean representing the request's status.

Example :

```php
// Simple update, without transaction
if ($db->update('BOOKS', ['books_name'=>htmlentities('Super book 2')], ['books_id'=>42])) {
	echo('Success');
} else {
	echo('Error');
}

// Simple update, with transaction
$db->beginTransaction();
$resBook1 = $db->update('BOOKS', ['books_name'=>htmlentities('Super book 1')], ['books_id'=>41]);
if ($resBook1) {
	if ($db->update('BOOKS', ['books_name'=>htmlentities('Super book 2')], ['books_id'=>42])) {
		$db->commit();
		echo('Success');
	} else {
		$db->rollback();
		echo('Error when updating book 2.');
	}
} else {
	$db->rollback();
	echo('Error when updating book 1.');
}
```

### delete()

This method is used to delete data from the database. It is highly recommended to use transaction when deleting data.

Parameters :

* $table string : Table name.
* $where array : Array of data pointing the row to update e.g. `['columnname'=>'data']`. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'. 'data' can also be an array of values.

Return value : boolean representing the request's status.

Example :

```php
// Simple delete, without transaction
if ($db->delete('BOOKS', ['books_id'=>42])) {
	echo('Success');
} else {
	echo('Error');
}

// Simple delete, with transaction
$db->beginTransaction();
$resBook1 = $db->delete('BOOKS', ['books_id'=>41]);
if ($resBook1) {
	if ($db->delete('BOOKS', ['books_id'=>42])) {
		$db->commit();
		echo('Success');
	} else {
		$db->rollback();
		echo('Error when deleting book 2.');
	}
} else {
	$db->rollback();
	echo('Error when deleting book 1.');
}
```

### count()

This method is used to count how many rows match the criterias.

Parameters :

* $table string : Table name.
* $column string : Column name.
* $where array : Array with table name as key and array as value with column name and filter value e.g. `['table'=>['columnname'=>'data']]`. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'. 'data' can also be an array of values.
* $join array (optional) : = Array with wanted join table name as key and array of needed values as values e.g. `['table' => [type(inner, left, right ...), 'foreignkey', 'primarykey', /*from table*\]]`.

Return value : request's status on fail or int on success.

Example :

```php
// Simple count
$res = $db->count('BOOKS', 'books_id', ['books_isavailable'=>TRUE]);
// Count with join
$res = $db->count('BOOKS', 'authors_id', ['books_isavailable'=>TRUE], ['AUTHORS'=>['INNER', 'books_refauthor', 'authors_id']]); // Return the number of authors who have at least one book available.
```

### sum()

This method is used to get the sum of several rows matching criterias.

Parameters :

* $table string : Table name.
* $column string : Column name.
* $where array : Array with table name as key and array as value with column name and filter value e.g. `['table'=>['columnname'=>'data']]`. 'data' has reserved values for nulls and booleans : 'NULL', '!NULL' 'TRUE', 'FALSE'. 'data' can also be an array of values.
* $join array (optional) : = Array with wanted join table name as key and array of needed values as values e.g. `['table' => [type(inner, left, right ...), 'foreignkey', 'primarykey', /*from table*\]]`.

Return value : request's status on fail or int on success.

Example :

```php
// Simple sum
$res = $db->sum('BOOKS', 'books_pages', ['books_isavailable'=>TRUE]); // Return the total number of pages of available books.
```

### customSQL()

This method is used to execute a custom SQL request.

Parameters :

* $sql string : SQL request.
* $data array (optional) : Array of data e.g. `['columnname'=>'data']` or if you use `?` in the request : `['data1', 'data2']`.

Return value : the array of the raw response.

Example :

```php
// For uncommon SQL queries
$res = $db->customSQL('UPDATE BOOKS SET books_isold IS TRUE WHERE books_release < 2000-01-01');
```
