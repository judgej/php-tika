<?php

namespace Academe\Tika;

// TODO: Split Tika into two classes: "Tika" to handle the driving functionality
// and global settings,
// and "TikaFile" to handle the properties of a file. That way multiple files
// can be handled at once, and data can be cleaned up by destroying the TikeFile
// objects when finished with.

class Tika
{
    // Temporary file defaults.
    public static $tempDir = '';
    public static $tempPrefix = '';

    // Source document details, after caching if necessary.
    public $sourceTemp;
    //public $sourceStream;
    public $sourceFilename;

    // The output files from the command line: stadout and stserr.
    // Holds temporaryFile objects.
    public $outfile;
    public $errfile;

    // Where to find the java executable.
    public $javaPath = '';
    public $javaCommand = 'java';

    public $tikaJar = 'tika-app-1.2.jar';

    // The charset the command will be asked to return.
    public $outputCharset = 'UTF-8';

    // The last command string executed.
    public $commandString;

    // Options
    public $prettyPrint = true;
    public $password = '';
    public $debug = false;

    // Output data for some of the smaller commands.
    public $output;

    public $language;
    public $type;
    public $version;

    // Return a temporary file object.
    public static function temporaryFile()
    {
        return new temporaryFile(self::$tempDir, static::$tempPrefix);
    }

    // Define the source document.
    // This can be a filename, a stream name, or an open stream.
    // If not local, then the resource or stream will be copied to a
    // local temporary file; we are likely to be processing it multiple
    // times to extract the information we need from it.
    public function defineSource($source)
    {
        if (is_string($source)) {
            // Source is a string.
            // Check whether it is a local file or remote resource.
            // TODO: detect if the file has been uploaded, and so needs moving
            // before it can be read.
            if (!stream_is_local($source)) {
                // Not local - it should be cached locally before we first use it.
                // Let's do that now.
                // TODO: is there somewhere static we can store the temporary file object?
                // We want to keep it in scope until the script exits, so the temporary
                // file is kept around until we have finished with it.
                $this->srcTemp = $this->temporaryFile();

                $src_fd = fopen($source, 'r');
                stream_copy_to_stream($src_fd, $this->srcTemp->fileStream);
                fclose($src_fd);

                //$this->sourceStream = $this->srcTemp->fileStream;
                $this->sourceFilename = $this->srcTemp->fileName;
            } else {
                // Is local - just open it.
                $this->sourceFilename = $source;
                //$this->sourceStream = fopen($source, 'r');
            }
        } else {
            // Assume for now it is an open stream. FIXME: not a good assumption.
            // Check if it is local.
            $src_meta = stream_get_meta_data($source);
            if ($src_meta['wrapper_type'] == 'plainfile') {
                // Local.
                //$this->sourceStream = $source;
                $meta = stream_get_meta_data($source);
                $this->sourceFilename = $meta['uri'];
            } else {
                // Not local - copy it to a local temporary file.
                // Do not close the source stream - leave that to its owner.
                $this->srcTemp = $this->temporaryFile();
                stream_copy_to_stream($source, $this->srcTemp->fileStream);

                //$this->sourceStream = $this->srcTemp->fileStream;
                $this->sourceFilename = $this->srcTemp->fileName;
            }
        }

        // Regardless of what was passed in, we return with a local filename
        // that can be passed into the executed command-line.
        return $this->sourceFilename;
    }

    // Run the command.
    // We will create new output files is necessary, or reuse existing.
    // We need to check everything is in place before the command can be executed.
    // That includes the desired operation, characterset etc.
    // This is a unix command line at this stage.
    // TODO: must use escapeshellarg() or escapeshellcmd() on argumements.
    public function executeCommand($task = 'text')
    {
        // True to send stdout to a file.
        $stdoutToFile = true;

        // True if a task that supports "pretty-print".
        $ppSupport = false;

        $command = array();
        $command[] = $this->javaPath . $this->javaCommand;
        $command[] = '-jar ' . $this->tikaJar;

        if (!empty($this->outputCharset)) $command[] = '-e' . $this->outputCharset;

        // TODO: extract of attachments
        // TODO: some of these commands return just one line of text, so support
        // getting that more easily through the exec() output parameter, so we
        // can leave the files for big output streams.
        // It also means a temporary outfile only needs creating for certain tasks.
        switch ($task) {
            case 'text':
                $command[] = '--text'; break;
            case 'main':
                $command[] = '--text-main'; break;
            case 'xml':
                $ppSupport = true;
                $command[] = '--xml'; break;
            case 'html':
                $ppSupport = true;
                $command[] = '--html'; break;
            case 'metadata':
                $stdoutToFile = false;
                $command[] = '--metadata'; break;
            case 'json':
                $stdoutToFile = false;
                $command[] = '--json'; break;
            case 'xmp':
                $stdoutToFile = false;
                $command[] = '--xmp'; break;
            case 'language':
                $stdoutToFile = false;
                $command[] = '--language'; break;
            case 'parsers':
                $command[] = '--list-parsers'; break;
            case 'parser-details':
                $command[] = '--list-parser-details'; break;
            case 'models':
                $command[] = '--list-met-models'; break;
            case 'type':
                $stdoutToFile = false;
                $command[] = '--detect'; break;
            case 'types':
                $command[] = '--list-supported-types'; break;
            case 'version':
                $stdoutToFile = false;
                $command[] = '--version'; break;
            default:
                $command[] = $task; break;
            case 'help':
                $command[] = '--help'; break;
        }

        if ($this->prettyPrint && $ppSupport) $command[] = '--pretty-print';

        if (!empty($this->password)) $command[] = '--password=' . $this->password;

        if (!empty($this->debug)) $command[] = '--verbose';

        // TODO: if the files are there, then clean them out or reset the file handle
        // back to the start.
        if (empty($noFile) &&empty($this->outfile)) $this->outfile = $this->temporaryFile();
        if (empty($this->errfile)) $this->errfile = $this->temporaryFile();

        $command[] = $this->sourceFilename;

        // Send stdout to a file if necessary.
        if ($stdoutToFile) $command[] =  ' >' . $this->outfile->fileName;

        // Always send stderr to a file.
        $command[] =  ' 2>' . $this->errfile->fileName;

        $this->commandString = implode(' ', $command);
        $result = exec($this->commandString, $output);

        // If the result goes into a a file, then return the command result, else
        // return the output.
        // TODO: be consistent with the return, and put the result into a property instead.
        if (!$stdoutToFile) {
            $this->output = $output;
        }

        return $result;
    }

    public function getLanguage()
    {
        $result = $this->executeCommand('language');
        $this->language = reset($this->output);
        return $this->language;
    }

    public function extractText($format = 'text')
    {
        if (!preg_match('/^(text|main|xml|html)$/', $format)) $format = 'text';

        $result = $this->executeCommand($format);
        return $result;
    }

    public function getMetadata($format = 'json')
    {
        switch ($format) {
            case ('text'):
                $task = 'metadata';
                break;
            case ('json'):
                $task = 'json';
                break;
            case ('xmp'):
                $task = 'xmp';
                break;
            case ('array'):
            case ('flatarray'):
                $task = 'metadata';
                break;
            case ('json'):
            default:
                $task = 'json';
                break;
        }

        $result = $this->executeCommand($task);

        if ($format == 'array' || $format == 'flatarray') {
            $outArray = array();
            foreach($this->output as $element) {
                // Skip if not in the correct format.
                if (strpos($element, ': ') === FALSE) continue;

                list($key, $value) = explode(': ', $element, 2);

                if ($format == 'flatarray') {
                    $outArray[$key] = $value;
                } else {
                    // Convert the foo:bar keys into nested arrays.
                    // This technique courtesy: 
                    // http://stackoverflow.com/questions/9628176/using-a-string-path-to-set-nested-array-data
                    $temp = &$outArray;
                    foreach(explode(':', $key) as $key2) {
                        $temp = &$temp[$key2];
                    }
                    $temp = $value;
                    unset($temp);
                }
            }
            return $outArray;
        }

        return $this->output;
    }

    // Helper function TODOs:
    // - Fetch metadata into a structured array

    // Trim lines, collapse multiple spaces and remove blank lines from an array.
    // TODO: support line-terminated text too.
    // This method handles the input by reference, to cut down on memory usage.
    // However, the array still needs to start off in memory.
    // Maybe this can be implemented as a stream filter, to be applied to the
    // output stream, so it can be handled line-by-line as the output file is read
    // and not having to post-process the data.
    public static function trimText(&$Text)
    {
        array_walk($Text,
            function(&$Value, $Key) use(&$Text) {
                // Trim spaces.
                $Value = preg_replace('/\s+/', ' ', trim($Value));

                // Remove blank elements.
                if ($Value == '') unset($Text[$Key]);
            }
        );
    }
}
