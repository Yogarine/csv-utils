<?php

namespace Yogarine\CsvUtils;

use Countable;
use Exception;
use Iterator;

/**
 * Wrapper class for CSV files. Uses fgetcsv().
 *
 * Primarily designed to efficiently handle large CSV files. Can handle CSV
 * files as big as you can make them. Implements Iterator so you can use
 * foreach() to iterate through the entries in a CSV file.
 *
 * @todo   Implement SeekableIterator correctly
 * @author Alwin Garside <alwin@garsi.de>
 */
class CsvFile implements Iterator, Countable
{
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
    protected $_position = 0;

    /**
     * Number of rows.
     *
     * @var int
     */
    protected $_count = 0;

    /**
     * The row at which the header is located.
     *
     * -1 means there is no header.
     *
     * @var int
     */
    protected $_headerRow = -1;

    /**
     * The field delimiter (one character only).
     *
     * @var string
     */
    protected $_delimiter = ',';

    /**
     * The field enclosure character (one character only).
     *
     * @var string
     */
    protected $_enclosure = '"';

    /**
     * The escape character (one character only).
     *
     * @var string
     */
    protected $_escape = '\\';

    /**
     * Must be greater than the longest line (in characters) to be found in
     * the CSV file (allowing for trailing line-end characters).
     *
     * @var int[]
     */
    protected $_maxLineLength;

    /**
     * File pointer for the CSV file.
     */
    protected $_handle;

    /**
     * Position that fgetcsv is currently at.
     *
     * Unfortunately, fgetcsv can't seek,
     * so we need to carefully keep track of this.
     *
     * @var int
     */
    protected $_fgetcsvPosition = 0;

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
        $this->filename   = $filename;
        $this->_headerRow = $headerRow;
        $this->_delimiter = $delimiter;
        $this->_enclosure = $enclosure;
        $this->_escape    = $escape;

        $this->_handle = fopen($this->filename, 'r');
        if (! $this->_handle) {
            throw new Exception("Unable to open file '{$this->filename}'");
        }

        fseek($this->_handle, 0);

        // Get the max line length for better performance when iterating.
        $this->_maxLineLength = 0;
        $row                  = 0;
        while (false !== ($rowData = fgets($this->_handle))) {
            if ($row > $this->_headerRow) {
                $this->_count++;
                $lineLength = strlen($rowData);
                if ($lineLength > $this->_maxLineLength) {
                    $this->_maxLineLength = $lineLength;
                }
            }
            $row++;
        }

        $this->rewind();
    }

    /**
     * Makes sure there are no duplicate headers.
     */
    protected function _checkHeaders()
    {
        $headerKeys = array();
        foreach ($this->headers as $key => $header) {
            if (! isset($headerKeys[$header])) {
                $headerKeys[$header] = array();
            }
            $headerKeys[$header][] = $key;
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

    /**
     * Called when this class is destroyed.
     *
     * Makes sure the file handle is closed.
     */
    public function __destruct()
    {
        fclose($this->_handle);
    }

    /**
     * Rewinds this Iterator to the first row after the headers.
     */
    public function rewind()
    {
        fseek($this->_handle, 0);
        for ($row = 0; $row <= $this->_headerRow; $row++) {
            $this->headers = fgetcsv(
                $this->_handle,
                0,
                $this->_delimiter,
                $this->_enclosure,
                $this->_escape
            );
            if (count($this->headers) > 0) {
                $this->_checkHeaders();
            }
        }

        $this->_position        = 0;
        $this->_fgetcsvPosition = 0;
    }

    /**
     * Returns the current row as an array.
     *
     * @return array An associative array containing the fields read. A blank
     *               line in a CSV file will be returned as an empty array, and
     *               will not be treated as an error.
     */
    public function current()
    {
        $rowData = fgetcsv(
            $this->_handle,
            $this->_maxLineLength,
            $this->_delimiter,
            $this->_enclosure,
            $this->_escape
        );
        $this->_fgetcsvPosition++;

        $data = array();
        foreach ($rowData as $key => $value) {
            if (array_key_exists($key, $this->headers)) {
                $key = $this->headers[$key];
            }
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Return the current position.
     *
     * i.e. the current current row minus the header row and preceding rows.
     *
     * @return int
     */
    public function key()
    {
        return $this->_position;
    }

    /**
     * Increment the current position.
     */
    public function next()
    {
        // fgetcsv() automatically goes to the next row, so we just try to
        // handle things as nicely as possible.
        while ($this->_fgetcsvPosition <= $this->_position) {
            fgetcsv(
                $this->_handle,
                $this->_maxLineLength,
                $this->_delimiter,
                $this->_enclosure,
                $this->_escape
            );
            $this->_fgetcsvPosition++;
        }

        $this->_position = $this->_fgetcsvPosition;
    }

    /**
     * Check whether the current position is valid.
     *
     * @return boolean True iff the current position is a valid row.
     */
    public function valid()
    {
        if ($this->_position > $this->_count - 1) {
            return false;
        }

        return true;
    }

    /**
     * Returns the number of CSV entries.
     *
     * i.e. the number of rows in the file minus the header row and any
     * preceding rows.
     *
     * @return int Number of CSV entries.
     */
    public function count()
    {
        return $this->_count;
    }
}
