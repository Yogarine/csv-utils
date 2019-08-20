<?php

use PHPUnit\Framework\TestCase;
use Yogarine\CsvUtils\CsvFile;

class CsvFileTest extends TestCase
{
    /**
     * @var array
     */
    private $dataWithHeaders = array(
        array(
            'id'          => 0,
            'name'        => 'foo',
            'description' => 'This is a Foo',
        ),
        array(
            'id'          => 1,
            'name'        => 'bar',
            'description' => 'This is a Bar',
        ),
        array(
            'id'          => 2,
            'name'        => 'baz',
            'description' => 'This is a Baz',
        ),
        array(
            'id'          => 3,
            'name'        => 'qux',
            'description' => 'This is a Qux',
        ),
    );

    /**
     * @var array
     */
    private $dataWithoutHeaders = array(
        array(0, 'foo', 'This is a Foo'),
        array(1, 'bar', 'This is a Bar'),
        array(2, 'baz', 'This is a Baz'),
        array(3, 'qux', 'This is a Qux'),
    );

    /**
     * @throws \Exception
     */
    public function testIterateThroughCsv()
    {
        $csvFile = $this->getCsvFileWithHeaders();

        foreach ($csvFile as $key => $value) {
            $this->assertEquals($this->dataWithHeaders[$key], $value);
        }

        // Iterate again
        foreach ($csvFile as $key => $value) {
            $this->assertEquals($this->dataWithHeaders[$key], $value);
        }
    }


    /**
     * @throws \Exception
     */
    public function testIterateThroughCsvWithoutHeaders()
    {
        $csvFile = $this->getCsvFileWithoutHeaders();

        foreach ($csvFile as $key => $value) {
            $this->assertEquals($this->dataWithoutHeaders[$key], $value);
        }

        // Iterate again
        foreach ($csvFile as $key => $value) {
            $this->assertEquals($this->dataWithoutHeaders[$key], $value);
        }
    }

    /**
     * @throws \Exception
     */
    public function testCurrentNext()
    {
        $csvFile = $this->getCsvFileWithHeaders();

        // Test twice because caching.
        $this->assertEquals($this->dataWithHeaders[0], $csvFile->current());
        $this->assertEquals($this->dataWithHeaders[0], $csvFile->current());

        $csvFile->next();

        // Test twice because caching.
        $this->assertEquals($this->dataWithHeaders[1], $csvFile->current());
        $this->assertEquals($this->dataWithHeaders[1], $csvFile->current());
    }

    /**
     * @throws \Exception
     */
    public function testKey()
    {
        $csvFile = $this->getCsvFileWithHeaders();

        $this->assertEquals(0, $csvFile->key());

        $csvFile->next();

        $this->assertEquals(1, $csvFile->key());
    }

    /**
     * @throws \Exception
     */
    public function testValid()
    {
        $csvFile = $this->getCsvFileWithHeaders();

        $this->assertTrue($csvFile->valid());
        $csvFile->seek(3);
        $this->assertTrue($csvFile->valid());
        $csvFile->next();
        $this->assertFalse($csvFile->valid());
    }

    /**
     * @throws \Exception
     */
    public function testRewind()
    {
        $csvFile = $this->getCsvFileWithHeaders();

        $csvFile->seek(2);
        $this->assertEquals($this->dataWithHeaders[2], $csvFile->current());

        $csvFile->rewind();
        $this->assertEquals($this->dataWithHeaders[0], $csvFile->current());
    }

    /**
     * @throws \Exception
     */
    public function testCount()
    {
        $csvFile = $this->getCsvFileWithHeaders();
        $this->assertEquals(4, $csvFile->count());

        $csvFile = $this->getCsvFileWithoutHeaders();
        $this->assertEquals(4, $csvFile->count());
    }

    /**
     * @throws \Exception
     */
    public function testSeek()
    {
        $csvFile = $this->getCsvFileWithHeaders();

        $csvFile->seek(3);
        $this->assertEquals($this->dataWithHeaders[3], $csvFile->current());

        $csvFile->seek(1);
        $this->assertEquals($this->dataWithHeaders[1], $csvFile->current());

        /** @noinspection PhpParamsInspection */
        $this->expectException('OutOfBoundsException');
        $csvFile->seek(4);
    }

    /**
     * @return \Yogarine\CsvUtils\CsvFile
     * @throws \Exception
     */
    private function getCsvFileWithHeaders()
    {
        return new CsvFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'csv_with_header.csv'
        );
    }

    /**
     * @return \Yogarine\CsvUtils\CsvFile
     * @throws \Exception
     */
    private function getCsvFileWithoutHeaders()
    {
        return new CsvFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'csv_without_header.csv',
            CsvFile::HEADER_ROW_NONE
        );
    }
}
