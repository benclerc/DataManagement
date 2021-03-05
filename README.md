# Data Management

PHP Class providing simple and compact database requests. Useful for small projects without ORM. With this class you can :

* Select, insert, update, delete data
* Count rows
* Sum rows
* Use SQL transaction
* Execute custom SQL request

## Getting started

1. Get [Composer](http://getcomposer.org/)
2. Install the library using composer `composer require benclerc/datamanagement`.
3. Add the following to your application's main PHP file `require 'vendor/autoload.php';`.
4. Instanciate the class with the database's connection information `$db = new \DataManagement\DataManagement('pgsql', 'localhost', 5432, 'myDb', 'myUser', 'myUserPassword');`.
5. Start using the library `$books = $db->select('BOOKS')['fetchAll'];`.

## Documentation

You can find a full documentation [here](https://benclerc.github.io/DataManagement/).

### DataManagement class

#### select(string $table, array $order = NULL, array $join = NULL, array $filter = NULL, int $limit = NULL, int $offset = NULL, array $columns = ['\*']) : array

This method is used to retrieve data from the database. It can be a very simple request like getting a whole table or a more complex request with ordering, table joins, filters, limits and offsets.

Examples :

```php
// Get all books
$res = $db->select('BOOKS')['fetchAll'];
// Get one book, id = 42
$res = $db->select('BOOKS', NULL, NULL, ['BOOKS'=>[['books_id'=>42]]])['fetch']; // Note the NULL values because we do not want order or join. And also note the fetch instead of fetchAll because we know we have only one result.
// Get all books with their authors, results ordered on the book name from A to Z
$res = $db->select('BOOKS', ['books_name'=>'ASC'], ['AUTHORS'=>['INNER', 'books_refauthor', 'authors_id']])['fetchAll'];
// Get all books with the reference in the list + their authors, results ordered on the book name from A to Z and author name from Z to A, limit to 10 results with an offset of 10 (page 2)
$referenceList = [37483, 27949, 49303, 20438];
$res = $db->select('BOOKS', ['books_name'=>'ASC', 'authors_name'=>'DESC'], ['AUTHORS'=>['INNER', 'books_refauthor', 'authors_id']], ['BOOKS'=>[['books_reference'=>$referenceList]]], 10, 10)['fetchAll'];
// Get all books with their subcategories and categories
$res = $db->select('BOOKS', NULL, ['SUBCATEGORIES'=>['INNER', 'books_refsubcategory', 'subcategories_id'], 'CATEGORIES'=>['INNER', 'subcategories_refcategory', 'categories_id', 'SUBCATEGORIES']])['fetchAll']; // Note the fourth element in the element 'CATEGORIES' in the join array.
```


