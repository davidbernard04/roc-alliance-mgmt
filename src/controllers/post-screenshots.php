<?php

//TODO better mobile HTML view

$uReportLevel = E_ALL ^ E_STRICT;
error_reporting(($uReportLevel > E_ALL ? E_ALL : $uReportLevel));
ini_set('display_errors', '1');

require_once __DIR__ . '/../../composer/vendor/autoload.php';

include 'models/CMemberPointsModel.php';

use thiagoalessio\TesseractOCR\TesseractOCR;
use PNGMetadata\PNGMetadata;

// Config
$g_UPLOAD_DIR = 'writable/uploads';
$g_CLEANUP_UPLOADS = false;
date_default_timezone_set('UTC');

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

    if (isset($g_UPLOAD_DIR)) {
        $aFiles = GetUploadedFiles();
    } else {
        // array_filter is used to remove empty entries, just in case.
        $aFiles = array_filter($_FILES['upload']['tmp_name']);
    }

    if (count($aFiles) > 0) {
        $aAll = array();

        foreach ($aFiles as $szFilename) {
            CropImage($szFilename);

            $aPart = ExtractFromImage($szFilename);

            if ($aPart !== -1) {
                // Append and magically ignore duplicates
                // TODO: This does not support uploading screenshots from differente date (since array indexed by position). Need an index by date.
                $aAll += $aPart;
            }
        }

        // Sort by array key (i.e. position)
        ksort(/*INOUT*/$aAll, SORT_NUMERIC);

        // Insert in database;
        $g_sqlModel->InsertMembers('Québec Kingdóm', $aAll);

        echo "<br><pre>" . serialize($aAll) . "</pre>";
        echo "<br>\nDone!";
    }
}

/**
 * Crop screenshot because OCR works a lot better.
 * 
 * Note: Using UZN files didn't worked as good (even with exact same coordinates).
 */
function CropImage($szFilename)
{
    $type = exif_imagetype($szFilename);
    if ($type == IMAGETYPE_PNG) {
        $im = imagecreatefrompng($szFilename);
    } elseif ($type == IMAGETYPE_JPEG) {
        $im = imagecreatefromjpeg($szFilename);
    } else {
        echo "Unsupported format $type";
        exit;
    }

    // Screen name
    $im2 = imagecrop($im, ['x' => 14, 'y' => 27, 'width' => 89, 'height' => 19]);
    // Convert to monochrome image.
    imagefilter($im2, IMG_FILTER_GRAYSCALE);
    imagefilter($im2, IMG_FILTER_BRIGHTNESS, 10);
    imagefilter($im2, IMG_FILTER_CONTRAST, -255); 
    if ($im2 !== FALSE) {
        if (file_exists("$szFilename-screen.png")) {
            unlink("$szFilename-screen.png");
        }
        imagepng($im2, "$szFilename-screen.png");
        imagedestroy($im2);
    }

    // Better rendering if we increase contrast, which increase text and reduce graphics.
    imagefilter($im, IMG_FILTER_GRAYSCALE);
    imagefilter($im, IMG_FILTER_BRIGHTNESS, 20);
    imagefilter($im, IMG_FILTER_CONTRAST, -150);

    if (file_exists("$szFilename-filtered.png")) {
        unlink("$szFilename-filtered.png");
    }
    imagepng($im, "$szFilename-filtered.png");

    // TODO: Coordinates are hardcoded for an iPhone 7

    // Positions
    $im2 = imagecrop($im, ['x' => 294, 'y' => 214, 'width' => 60, 'height' => 520]);
    if ($im2 !== FALSE) {
        if (file_exists("$szFilename-pos.png")) {
            unlink("$szFilename-pos.png");
        }
        imagepng($im2, "$szFilename-pos.png");
        imagedestroy($im2);
    }
    
    // Names
    $im2 = imagecrop($im, ['x' => 442, 'y' => 214, 'width' => 200, 'height' => 520]);
    if ($im2 !== FALSE) {
        if (file_exists("$szFilename-names.png")) {
            unlink("$szFilename-names.png");
        }
        imagepng($im2, "$szFilename-names.png");
        imagedestroy($im2);
    }
    
    // Points
    $im2 = imagecrop($im, ['x' => 778, 'y' => 214, 'width' => 80, 'height' => 520]);
    if ($im2 !== FALSE) {
        if (file_exists("$szFilename-pts.png")) {
            unlink("$szFilename-pts.png");
        }
        imagepng($im2, "$szFilename-pts.png");
        imagedestroy($im2);
    }
    
    imagedestroy($im);
}

function ExtractFromImage($szFilename)
{
    $bufferToForceBrowserToDisplay = str_repeat(" ", 4096); // some browser wait to display despite having receiving it.

    echo "Processing $szFilename...\n<br>".$bufferToForceBrowserToDisplay;
    flush();

    // Extract date from PNG file exif.
    $szCreatedDate = GetImageCreationDate($szFilename);
    $createdDateGroup = GetReferenceDate($szCreatedDate);
    $szScreenType = false;

    // First look if it's a supported screenshot type.
    $aScreenName = OCR("$szFilename-screen.png", 6, false);
    if (array_search("ALLIANCE", $aScreenName) !== false) {
        $szScreenType = "Alliance Ranking";
    } elseif (
        array_search("HUNT", $aScreenName) !== false ||
        array_search("CLASSEMENT", $aScreenName) !== false
    ) {
        $szScreenType = "Treasure Hunt Ranking";
    } else {
        echo "-> Error, cannot find Alliance or Treasure Hunt ranking.\n<br>";
        echo "--> RAW output is " . print_r($aScreenName, true) . "\n<br>";
        return -1;
    }

    //TODO: Support TH.
    if ($szScreenType == "Treasure Hunt Ranking") {
        echo "-> Treasure Hunt ranking are not yet supported. Skipping.\n<br>";
        return -1;
    }

    $aPositions = OCR("$szFilename-pos.png", 6, true); // positions work a bit better with psm(6)
    $aNames = OCR("$szFilename-names.png", 4, false);
    $aPoints = OCR("$szFilename-pts.png", 4, true);

    // echo "-> NAME RAW data: " . print_r($aNames, true) . "<br>\n";
    //TODO: Hardcoded for iPhone7 screenshots with 6 players visible.
    if (count($aPoints) != 6 || count($aPositions) != 6 || count($aNames) < 11) {
        echo "Error, did not found 6 players for " . $szFilename . "\n<br>";
        echo "-> RAW data: " . print_r($aNames, true) . "<br>\n";
        return -1;
    }

    global $g_CLEANUP_UPLOADS;
    if ($g_CLEANUP_UPLOADS) {
        unlink($szFilename);
        unlink("$szFilename-pos.png");
        unlink("$szFilename-names.png");
        unlink("$szFilename-pts.png");
        unlink("$szFilename-filtered.png");
    }

    // Build array indexed by position.
    $aComplete = array();
    $uStartingIndexForNames = (count($aNames) == 12 ? 1 : 0);
    for ($i = 0; $i < count($aPositions); $i++) {
        $szName = $aNames[$i + (1 * $i) + $uStartingIndexForNames]; // complex algo because names have junks in-between.
        $aComplete[$aPositions[$i]] = array(
            'fullname' => $szName,
            'name_id' => preg_replace('/[^A-Za-z0-9#]/', '', $szName), // keep only alpha-numeric chars, except the #.
            'pts' => $aPoints[$i],
            'date' => $szCreatedDate,
            'time_group' => $createdDateGroup
        );
    }
    return $aComplete;
}

function OCR($szFilename, $uPsm, $bNumbersOnly)
{
    // $aAcceptedChars = array_merge(range('A','Z'), range('a','z'), range(0,9));
    // array_push($aAcceptedChars, '#', '*', '\'', ' ', '_', '-');
    $aNumbersOnly = array();
    if ($bNumbersOnly) {
        $aNumbersOnly = range(0, 9);
    }

    $blob = (new TesseractOCR($szFilename))
        ->psm($uPsm)
        ->allowlist($aNumbersOnly)
        ->configFile('tesseract.ini')
        ->run();

    // $blob = (new TesseractOCR($szFilename))
    //     ->psm($uPsm)
    //     ->tessdataDir('./tessdata/')
    //     ->lang('fra')
    //     ->allowlist($aNumbersOnly)
    //     ->userWords('words.txt')
    //     ->configFile('config2.ini')
    //     ->run();

    $aLines = array_values(array_filter(explode("\n", $blob)));
    // print_r($aLines);
    return $aLines;
}

/**
 * Return in the format: 
 *      2021:12:02 13:55:01
 */
function GetImageCreationDate($szFilename)
{
    $dateTimeOriginal = '';

    if (PNGMetadata::isPNG($szFilename)) {
        $png_metadata = new PNGMetadata($szFilename); // return a 'ArrayObject' or 'Exception'
        $dateTimeOriginal = $png_metadata->get('exif:DateTimeOriginal');
    } else {
        $exif_metadata = exif_read_data($szFilename);
        $dateTimeOriginal = $exif_metadata['DateTimeOriginal'];
    } 

    return $dateTimeOriginal;
}

/**
 * Return only the date (no time) in unix timestamp.
 */
function GetReferenceDate($szDateTime)
{
    $dateTime = new DateTime($szDateTime);
    $dateOnly = new DateTime($dateTime->format('Y-m-d'));
    return $dateOnly->format('U');
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
