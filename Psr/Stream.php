<?php declare(strict_types=1);

/**
 * This file is part of the Vection-Framework project.
 * Visit project at https://github.com/Vection-Framework/Vection
 *
 * (c) David M. Lung <vection@davidlung.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vection\Component\Http\Psr;

use Psr\Http\Message\StreamInterface;

/**
 * Class Stream
 *
 * @package Vection\Component\Http\Psr
 */
class Stream implements StreamInterface
{
    /** @var resource */
    protected $stream;

    /** @var  */
    protected $uri;

    /** @var int */
    protected $size;

    /** @var bool */
    protected $readable;

    /** @var bool */
    protected $writable;

    /** @var bool */
    protected $seekable;

    /** @var array */
    protected const RESOURCE_MODES = [
        'read' => [
            'r' => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
            'rb' => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
            'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a+' => true,
        ],
        'write' => [
            'w' => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
            'c+' => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
            'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true,
        ],
    ];

    /**
     * Stream constructor.
     *
     * @param string|resource $content
     */
    public function __construct($content = '')
    {
        if( is_string($content) && strpos($content, 'php://') === 0 ){

            $this->stream = fopen($content, $content === 'php://input' ? 'r' : 'rw+');

        }elseif( is_resource($content) ){
            $this->stream = $content;
        }else{

            $this->stream = fopen('php://temp', 'rw+');

            if( $content ){
                fwrite($this->stream, $content);
                fseek($this->stream, 0);
            }
        }

        $meta = $this->getMetadata();

        $this->seekable = $meta['seekable'];
        $this->readable = isset(self::RESOURCE_MODES['read'][$meta['mode']]);
        $this->writable = isset(self::RESOURCE_MODES['write'][$meta['mode']]);
        $this->uri = $meta['uri'];
    }

    /**
     * Closes the stream when the destructed.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close()
    {
        if( $this->stream ){
            if( is_resource($this->stream) ){
                fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        if( $this->stream ){
            $stream = $this->stream;
            $this->stream = null;
            $this->size = null;
            $this->uri = null;
            $this->readable = false;
            $this->writable = false;
            $this->seekable = false;

            return $stream;
        }

        return null;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize()
    {
        if( ! $this->stream ){
            return null;
        }

        if( $this->size !== null ){
            return $this->size;
        }

        if( $this->uri ){
            clearstatcache(true, $this->uri);
        }

        return $this->size = fstat($this->stream)['size'] ?? null;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell()
    {
        $position = ftell($this->stream);

        if( $position === false ){
            throw new \RuntimeException('Unable to determine the pointer to references steam.');
        }

        return $position;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof()
    {
        return ! $this->stream || feof($this->stream);
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable()
    {
        return $this->seekable;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *                    based on the seek offset. Valid values are identical to the built-in
     *                    PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *                    offset bytes SEEK_CUR: Set position to current location plus offset
     *                    SEEK_END: Set position to end-of-stream plus offset.
     *
     * @throws \RuntimeException on failure.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if( ! $this->seekable ){
            throw new \RuntimeException("The current stream is not seekable.");
        }

        $seek = fseek($this->stream, $offset, $whence);

        if( $seek === -1 ){
            throw new \RuntimeException(sprintf(
                'Failure while seeking stream to position "%s" with whence value "%s"',
                $offset, $whence
            ));
        }
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @throws \RuntimeException on failure.
     * @link http://www.php.net/manual/en/function.fseek.php
     * @see  seek()
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     *
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string)
    {
        if( ! $this->writable ){
            throw new \RuntimeException("Unable to write: The current stream is not writeable.");
        }

        $result = fwrite($this->stream, $string);

        if( $result === false ){
            throw new \RuntimeException("Error while writing to stream.");
        }

        $this->size = null;

        return $result;
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *                    them. Fewer than $length bytes may be returned if underlying stream
     *                    call returns fewer bytes.
     *
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length)
    {
        if( ! $this->readable ){
            throw new \RuntimeException("Unable to read: The current stream is not readable.");
        }

        return fread($this->stream, $length);
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents()
    {
        if( ! $this->stream ){
            throw new \RuntimeException("Unable to read stream. The current stream does not exists or is invalid.");
        }

        $content = stream_get_contents($this->stream);

        if( $content === false ){
            throw new \RuntimeException("Error while reading from stream.");
        }

        return $content;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     *
     * @param string $key Specific metadata to retrieve.
     *
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        if( ! $this->stream ){
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->stream);

        return $key === null ? $meta : ($meta[$key] ?? null);
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        try{
            $this->seekable && $this->seek(0);
            return $this->getContents();
        }catch(\RuntimeException $e){
            return '';
        }
    }
}