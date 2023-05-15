<?php
/**
 * Exception class thrown when a filesystem access failure happens
 */
class IOException extends Exception
{
    private $path;

    /**
     * Construct a new IOException
     *
     * @param string $path    path to the resource that cannot be accessed
     * @param string $message Custom exception message.
     */
    public function __construct($path, $message = '')
    {
        $this->path = $path;
        $this->message = empty($message) ? 'Error accessing' : $message;
        $this->message .= PHP_EOL . $this->path;
    }
}
