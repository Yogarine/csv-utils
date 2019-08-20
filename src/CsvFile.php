<?php

namespace Yogarine\CsvUtils;

use Countable;
use Exception;
use Iterator;
use OutOfBoundsException;
use SeekableIterator;

/**
 * Wrapper class for CSV files. Uses fgetcsv().
 *
 * Primarily designed to efficiently handle large CSV files. Can handle CSV
 * files as big as you can make them. Implements Iterator so you can use
 * foreach() to iterate through the entries in a CSV file.
 *
 * @author Alwin Garside <alwin@garsi.de>
 */
class CsvFile implements Iterator, Countable, SeekableIterator
{
    const HEADER_ROW_NONE = -1;

    /**
     * Headers of the CSV file.
     *
     * @var string[]
     */
    public $headers = array();

    /**
     * CSV filename.
     *
     * @var string
     */
    public $filename;

    /**
     * Current position.
     *
     * @var int
     */
    protected $position = 0;

    /**
     * Number of rows.
     *
     * @var int
     */
    protected $count = 0;

    /**
     * The row at which the header is located.
     *
     * -1 means there is no header.
     *
     * @var int
     */
    protected $headerRow = -1;

    /**
     * The field delimiter (one character only).
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * The field enclosure character (one character only).
     *
     * @var string
     */
    protected $enclosure = '"';

    /**
     * The escape character (one character only).
     *
     * @var string
     */
    protected $escape = '\\';

    /**
     * @var int
     */
    protected $contentOffset = 0;

    /**
     * Must be greater than the longest line (in characters) to be found in
     * the CSV file (allowing for trailing line-end characters).
     *
     * @var int
     */
    protected $maxLineLength = 0;

    /**
     * File pointer for the CSV file.
     */
    protected $handle;

    /**
     * Position that fgetcsv is currently at.
     *
     * Unfortunately, fgetcsv can't seek,
     * so we need to carefully keep track of this.
     *
     * @var int
     */
    protected $fgetcsvPosition = 0;

    /**
     * @var
     */
    protected $current = null;

    /**
     * Creates a new CsvFile instance.
     *
     * Accepts the filename and optionally the row at which the headers are
     * located and parameters used by fgetcsv().
     *
     * @param  string  $filename   Filename of the csv file.
     * @param  int     $headerRow  OPTIONAL 0-based row number at which the
     *                             headers can be found. -1 means there are no
     *                             headers. Defaults to 0 (first row). Any rows
     *                             above the headerRow are ignored.
     * @param  string  $delimiter  OPTIONAL Set the field delimiter (one
     *                             character only). Defaults as a comma.
     * @param  string  $enclosure  OPTIONAL Set the field enclosure character
     *                             (one character only). Defaults as a double
     *                             quotation mark.
     * @param  string  $escape     Set the escape character (one character
     *                             only). Defaults as a backslash (\)
     * @throws \Exception
     */
    public function __construct(
        $filename,
        $headerRow = 0,
        $delimiter = ',',
        $enclosure = '"',
        $escape = '\\'
    ) {
        $this->filename  = $filename;
        $this->headerRow = $headerRow;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape    = $escape;

        $this->handle = fopen($this->filename, 'r');
        if (! $this->handle) {
            throw new Exception("Unable to open file '{$this->filename}'");
        }

        // Skip the header rows.
        for ($row = 0; $row <= $this->headerRow; $row++) {
            if ($row == $this->headerRow) {
                $this->headers = fgetcsv(
                    $this->handle,
                    0,
                    $this->delimiter,
                    $this->enclosure,
                    $this->escape
                );
            } else {
                fgets($this->handle);
            }
        }

        $this->contentOffset = ftell($this->handle);

        // Resolve all duplicates in headers.
        if ($this->headers) {
            $headerKeys = array();
            foreach ($this->headers as $key => $header) {
                if (isset($headerKeys[$header])) {
                    $headerKeys[$header][] = $key;
                } else {
                    $headerKeys[$header] = array($key);
                }
            }

            foreach ($headerKeys as $header => $keys) {
                if (count($keys) > 1) {
                    $i = 0;
                    foreach ($keys as $key) {
                        $this->headers[$key] = rtrim($this->headers[$key]) . $i++;
                    }
                }
            }
        }

        // Get the max line length for better performance when iterating.
        while (false !== ($rowData = fgets($this->handle))) {
            $this->count++;
            $lineLength = strlen($rowData);
            if ($lineLength > $this->maxLineLength) {
                $this->maxLineLength = $lineLength;
            }
        }

        // Add margin for trailing line-end characters.
        $this->maxLineLength += 2;

        fseek($this->handle, $this->contentOffset);
    }

    /**
     * Called when this class is destroyed.
     *
     * Makes sure the file handle is closed.
     */
    public function __destruct()
    {
        fclose($this->handle);
    }

    /**
     * Return the current row as an array.
     *
     * @link https://php.net/manual/en/iterator.current.php
     *
     * @return array|bool|null An associative array containing the fields read.
     *                         A blank line in a CSV file will be returned as an
     *                         empty array, and will not be treated as an error.
     *                         Returns NULL if an invalid handle is supplied or
     *                         FALSE on other errors, including end of file.
     */
    public function current()
    {
        if ($this->fgetcsvPosition != $this->position + 1) {
            $this->current = $this->getRow($this->position);
        }

        return $this->current;
    }

    /**
     * Move forward to next element.
     *
     * @link https://php.net/manual/en/iterator.next.php
     *
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * Return the key of the current element.
     *
     * @link https://php.net/manual/en/iterator.key.php
     *
     * @return int|string|null Scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Checks if current position is valid.
     *
     * @link https://php.net/manual/en/iterator.valid.php
     *
     * @return bool Returns true on success or false on failure.
     */
    public function valid()
    {
        if ($this->fgetcsvPosition != $this->position + 1) {
            $this->current = $this->getRow($this->position);
        }

        return ! (false === $this->current || null === $this->current);
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @link https://php.net/manual/en/iterator.rewind.php
     *
     * @return void
     */
    public function rewind()
    {
        fseek($this->handle, $this->contentOffset);
        $this->fgetcsvPosition = 0;
        $this->position        = 0;
    }

    /**
     * Count the number of CSV rows.
     *
     * i.e. the number of rows in the file minus the header row and any
     * preceding rows.
     *
     * @link https://php.net/manual/en/countable.count.php
     *
     * @return int The row count as an integer.
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * Seeks to a position.
     *
     * @link https://php.net/manual/en/seekableiterator.seek.php
     *
     * @param  int  $position  The position to seek to.
     * @return void
     */
    public function seek($position)
    {
        if (false === ($row = $this->getRow($position))) {
            throw new OutOfBoundsException(
                "Invalid seek position ($position)"
            );
        }

        $this->current  = $row;
        $this->position = $position;
    }

    /**
     * @param  int  $position
     * @return array|false|null
     */
    protected function getRow($position)
    {
        // If we are passed our position, rewind.
        if ($this->fgetcsvPosition > $position) {
            fseek($this->handle, $this->contentOffset);
            $this->fgetcsvPosition = 0;
        }

        // Fast-forward our CSV file handler to the proper offset.
        while ($this->fgetcsvPosition < $position) {
            fgets($this->handle);
            $this->fgetcsvPosition++;
        }

        $rowData = fgetcsv(
            $this->handle,
            $this->maxLineLength,
            $this->delimiter,
            $this->enclosure,
            $this->escape
        );
        $this->fgetcsvPosition++;

        if (false === $rowData || null === $rowData) {
            return $rowData;
        }

        $row = array();
        foreach ($rowData as $key => $value) {
            if (isset($this->headers[$key])) {
                $key = $this->headers[$key];
            }
            $row[$key] = $value;
        }

        // In case the count increased, update it.
        if ($position > $this->count - 1) {
            $this->count = $position + 1;
        }

        return $row;
    }
}
