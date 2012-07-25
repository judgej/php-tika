<?php

/**
 * Still fumbling in the dark with this one.
 * The question on the output is, should the returned output be a filename/
 * file stream, or should it be an iterator? It makes sense being a file for
 * the command-line version, because the output always will be a file. But
 * if the output is not a file, but is a stream from a server process instead,
 * then does a file make less sense? But then, perhaps we want to always dump the
 * output into a temporary file first, and make that available to the caller to
 * read or itterate over as it likes. The only thing we can't rely on, is loading
 * the output into memory, as we have no idea how big it will be.
 * Any ideas?
 *
 * Edit: I think I get it, after a good night's sleep. So long as the output
 * class returned is inherited from the PHP iterator, then it can be used in
 * a consistent way by the calling routine. It may iterate over an output file
 * today, and may be a comletely different class that iterates over the output
 * from a remote network stread - but it is still a PHP iterator that can be
 * used in the same way.
 *
 * TODO: declare GPL licence details.
 */


// TODO: use an autoloader
include('Academe/Tika/TemporaryFile.php');
include('Academe/Tika/Tika.php');
use Academe\Tika;
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/> 
</head>
<body>

<form enctype="multipart/form-data" action="" method="POST">
    <input type="hidden" name="MAX_FILE_SIZE" value="100000" />
    Upload a document: 
    <input name="uploadedfile" type="file" />
    <br />

    Enter document URL: 
    <input name="url" type="text" />
    <br />

    Local file: 
    <label><input name="local" type="radio" value="" />None</label>
    <?php 
    $sample_docs_dir = __DIR__ . '/sample-docs';
    if (is_readable(__DIR__ . '/sample-docs')) {
        $local_files_fd = opendir(__DIR__ . '/sample-docs');
        while($name = readdir($local_files_fd)) {
            if ($name == '.' || $name == '..' || is_dir($sample_docs_dir .'/' . $name)) continue;
            echo '<label><input name="local" type="radio" value="';
            echo htmlspecialchars($name);
            echo '" />';
            echo htmlspecialchars($name);
            echo '</label>';
        }
        closedir($local_files_fd);
    }
    ?>
    <!-- <label><input name="local" type="radio" value="CV1.docx" />CV1.docx</label>
    <label><input name="local" type="radio" value="image1.jpeg" />image1.jpeg</label> -->
    <br />

    <input type="submit" value="Process Document" />
</form>

<hr />

<?php

/*
Notes
=====
Once a file has been uploaded, it should be possible to run several commands against
it, such as language detection, metadata, text, etc.

Rather then passing URLs into the command line, it should be downloaded and cached 
locally. That way we do not have to fetch it multiple times for running multiple
actions against it.

The -eUTF-8 works well. Other encodings should be supported, but this should be the
default, and not whatever the default is (appears to be ASCII).

Add option to include pretty-format newlines, to help reading line at a time.
*/

// The source supplied as a stream or stream name.
//$source = "CV1.docx";
//$source = "http://cumulus.acadweb.co.uk/tika/CV1.docx";
//$source = fopen($source, 'r');

$tika = new Tika\Tika();

$tika->javaPath = '/usr/local/jdk/bin/';

if (!empty($_FILES['uploadedfile']['name'])) {var_dump($_FILES);
    $move_to = $tika->temporaryFile();
    if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $move_to->fileName)) {
        echo "<p>Moved uploaded ".$_FILES['uploadedfile']['tmp_name']." to ".$move_to->fileName . "</p>";
    } else {
        echo "<p>Error in uploading file</p>";
    }

    $source = $move_to->fileName;
} elseif (!empty($_POST['local'])) {
    $source = '"' . $sample_docs_dir . '/' . basename($_POST['local']) . '"';
    echo "<p>Source is local file $source</p>";
} elseif (!empty($_POST['url'])) {
    $source = $_POST['url'];
    echo "<p>Source is URL $source</p>";
}

if (!empty($source)) {
    $in_filename = $tika->defineSource($source);

    //echo "<p>Language=".$tika->getLanguage()."</p>";

    // Call the main task:
    //$result = $tika->executeCommand('json');
    //$result = $tika->extractText('main');
    //$result = $tika->getMetadata('array');
    $result = $tika->extractText('text');

    echo "<p>result=" . print_r($result) , "</p>";
    echo "<p>Command = $tika->commandString</p>";

    // Coming out at this point we have the result code, an output file handle, 
    // and an error file handle. These two handles are temp files, and so will
    // be cleaned up when we exit.

    $out = array();
    while (($out[] = fgets($tika->outfile->fileStream, 4096)) !== FALSE);
    if (!feof($tika->outfile->fileStream)) {
        echo "Error: unexpected fgets() fail\n";
    }
    fclose($tika->outfile->fileStream);

    Tika\Tika::trimText($out);

    echo "<pre>";
    foreach($out as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    //var_dump($out);
    echo "</pre>";
}
?>
<hr />
</body>
</html>