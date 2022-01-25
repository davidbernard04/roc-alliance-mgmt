<?php

//TODO better mobile HTML view

$uReportLevel = E_ALL ^ E_STRICT;
error_reporting(($uReportLevel > E_ALL ? E_ALL : $uReportLevel));
ini_set('display_errors', '1');

require_once __DIR__ . '/../../composer/vendor/autoload.php';

include 'models/CMemberPointsModel.php';
include 'utils/CImageOcrHelper.php';

// Comment out to cleanup intermediate images.
$g_UPLOAD_DIR = 'writable/uploads';

$g_sqlModel = new CMemberPointsModel;

if (count($_FILES) > 0)
{
    HandlePost();
}

echo "<br><a href=\"/\">Go Back</a>";

function HandlePost()
{
    global $g_UPLOAD_DIR;
    global $g_sqlModel;

    $ocrHelper = new CImageOcrHelper();

    if (isset($g_UPLOAD_DIR)) {
        $aFiles = GetUploadedFiles();
    } else {
        // array_filter is used to remove empty entries, just in case.
        $aFiles = array_filter($_FILES['upload']['tmp_name']);
    }

    if (count($aFiles) > 0) {
        $aMembers = array();
        $bufferToForceBrowserToDisplay = str_repeat(" ", 4096); // some browser wait to display despite having receiving data.

        foreach ($aFiles as $szFilename) {
            echo "Processing $szFilename...\n<br>".$bufferToForceBrowserToDisplay;
            flush();

            $res = $ocrHelper->CropImage($szFilename);

            if ($res !== -1) {
                $aMembers = $ocrHelper->ExtractFromImage($szFilename, isset($g_UPLOAD_DIR));

                if ($aMembers !== -1) {
                    $g_sqlModel->InsertMembers('Québec Kingdóm', $aMembers);
                }
            }
        }

        echo "<br>\nDone!";
    }
}

/**
 * Copy the uploaded files, and return the filenames. Mostly useful for debugging.
 */
function GetUploadedFiles()
{
    global $g_UPLOAD_DIR;

    // print_r($_FILES);
    $uNbFiles = count($_FILES['upload']['name']);

    $aFilenames = array();
    // Loop through each file
    for ($i = 0; $i < $uNbFiles; $i++) {
        // Get the temp file path
        $tmpFilePath = $_FILES['upload']['tmp_name'][$i];

        // Make sure we have a file path
        if ($tmpFilePath != "") {
            // Setup our new file path
            $newFilePath = $g_UPLOAD_DIR . '/' . $_FILES['upload']['name'][$i];

            // Remove old file, else moving file may fail.
            if (file_exists($newFilePath)) {
                unlink($newFilePath);
            }

            // Move the uploaded file from tmpdir to the new destination.
            if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                echo "Moved image to $newFilePath\n<br>";
                array_push($aFilenames, $newFilePath);
            }
        }
    }
    return $aFilenames;
}
