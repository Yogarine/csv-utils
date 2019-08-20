# CSV Utils

This library currently contains the CsvFile class, which makes it easy to
iterate through CSV files of any size and makeup.


## Installation

```bash
composer require "yogarine/csv-utils:^1.0"
```


## CsvFile

The CsvFile class makes it easy to iterate through CSV files of any size. It
implements `Iterator` and `SeekableIterator` so you can easily loop through
the CSV using `foreach`:


### Usage

Let's say you have a file called `foo.csv` with the following content:

```
id,name,description
0,foo,"This is a Foo"
1,bar,"This is a Bar"
2,baz,"This is a Baz"
3,qux,"This is a Qux"
```

You can iterate through it like this:

```php
$csvFile = new CsvFile('foo.csv');

foreach ($csvFile as $row) {
	$id          = $row['id'];
	$name        = $row['name'];
	$description = $row['description'];
}
```


#### No header row

If your CSV doesn't have a header row:

```
0,foo,"This is a Foo"
1,bar,"This is a Bar"
2,baz,"This is a Baz"
3,qux,"This is a Qux"
```

Use the `$headerRow` argument to omit it:

```php
$csvFile = new CsvFile('foo.csv', CsvFile::HEADER_ROW_NONE);

foreach ($csvFile as $row) {
	list($id, $name, $description) = $row;
}
```


#### Other formats

Other formats, like PSV or TSV are also supported:

```
id|name|description
0|foo|"This is a Foo"
1|bar|"This is a Bar"
2|baz|"This is a Baz"
3|qux|"This is a Qux"
```

```php
$csvFile = new CsvFile('foo.csv', 0, '|');
```