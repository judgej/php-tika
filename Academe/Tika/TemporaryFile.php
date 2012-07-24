<?php

namespace Academe\Tika;

// A temporary file has a name and a stream.
// The file will [usually] be deleted on exit.

class TemporaryFile
{
    // The pathname of the temporary file.
    public $fileName;

    // The w+ file handle for the temporary file.
    public $fileStream;

    // Leave blank to use system temporary directory and naming.
    // Otherwise set a path for the required temporary file storage.
    public $tempDir = '';

    // Temporary file prefix when using own temporary directory.
    public $prefix = '';

    //
    public function __construct($path = '', $prefix = '')
    {
        if (!empty($path)) $this->tempDir = $path;
        if (!empty($prefix)) $this->prefix = $path;

        if (empty($this->tempDir)) {
            // Use system temporary file generation.
            // This will be automatically cleaned up on exit and so is preferred.
            $this->fileStream = tmpfile();
            $meta = stream_get_meta_data($this->fileStream);
            $this->fileName = $meta['uri'];
        } else {
            $this->fileName = tempnam($this->tempDir, $this->prefix);
            $this->fileStream = fopen($this->fileName, 'w+');
        }

        //parent::__construct();
    }

    public function __destruct()
    {
        // Clean up temporary files if we were using our own directory.
        if (!empty($this->tempDir)) {
            fclose($this->fileStream);
            if (file_exists($this->fileName)) unlink($this->fileName);
        }
    }
}
