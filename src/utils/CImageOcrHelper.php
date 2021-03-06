<?php

require_once __DIR__ . '/../../composer/vendor/autoload.php';

include_once __DIR__ . '/../utils/CPhoneSizeHelper.php';

use thiagoalessio\TesseractOCR\TesseractOCR;
use PNGMetadata\PNGMetadata;

date_default_timezone_set('UTC');

class CImageOcrHelper
{
    public function __construct()
    {
    }

    /**
     * Crop screenshot because OCR works a lot better.
     * 
     * Note: Using UZN files didn't worked as good (even with exact same coordinates).
     */
    public function CropImage($szFilename)
    {
        $type = exif_imagetype($szFilename);
        if ($type == IMAGETYPE_PNG) {
            $im = imagecreatefrompng($szFilename);
        } elseif ($type == IMAGETYPE_JPEG) {
            $im = imagecreatefromjpeg($szFilename);
        } else {
            echo "Unsupported format $type";
            return -1;
        }

        $sizeHelper = new CPhoneSizeHelper(imagesx($im), imagesy($im));

        // // Screen name
        // $im2 = imagecrop($im, ['x' => 14, 'y' => 27, 'width' => 89, 'height' => 19]);
        // // Convert to monochrome image.
        // imagefilter($im2, IMG_FILTER_GRAYSCALE);
        // imagefilter($im2, IMG_FILTER_BRIGHTNESS, 10);
        // imagefilter($im2, IMG_FILTER_CONTRAST, -255); 
        // if ($im2 !== FALSE) {
        //     if (file_exists("$szFilename-screen.png")) {
        //         unlink("$szFilename-screen.png");
        //     }
        //     imagepng($im2, "$szFilename-screen.png");
        //     imagedestroy($im2);
        // }

        // Better rendering if we increase contrast, which increase text and reduce graphics.
        imagefilter($im, IMG_FILTER_GRAYSCALE);
        imagefilter($im, IMG_FILTER_BRIGHTNESS, 20);
        imagefilter($im, IMG_FILTER_CONTRAST, -150);

        if (file_exists("$szFilename-filtered.png")) {
            unlink("$szFilename-filtered.png");
        }
        imagepng($im, "$szFilename-filtered.png");

        // Names
        $im2 = imagecrop($im, $sizeHelper->GetNamesCoordinates());
        if ($im2 !== FALSE) {
            if (file_exists("$szFilename-names.png")) {
                unlink("$szFilename-names.png");
            }
            imagepng($im2, "$szFilename-names.png");
            imagedestroy($im2);
        }
        
        // Points
        $im2 = imagecrop($im, $sizeHelper->GetPointsCoordinates());
        if ($im2 !== FALSE) {
            if (file_exists("$szFilename-pts.png")) {
                unlink("$szFilename-pts.png");
            }
            imagepng($im2, "$szFilename-pts.png");
            imagedestroy($im2);
        }
        
        imagedestroy($im);
    }

    public function ExtractFromImage($szFilename, $bDebugKeepWorkImages = false)
    {
        // Extract date from PNG file exif.
        $szCreatedDate = $this->GetImageCreationDate($szFilename);
        $datetimeCreated = new DateTime($szCreatedDate); // or today if no exif date

        $szScreenType = false;

        // // First look if it's a supported screenshot type.
        // $aScreenName = $this->OCR("$szFilename-screen.png", 6, false);
        // if (array_search("ALLIANCE", $aScreenName) !== false) {
        //     $szScreenType = "Alliance Ranking";
        // } elseif (
        //     array_search("HUNT", $aScreenName) !== false ||
        //     array_search("CLASSEMENT", $aScreenName) !== false
        // ) {
        //     $szScreenType = "Treasure Hunt Ranking";
        // } else {
        //     echo "-> Error, cannot find Alliance or Treasure Hunt ranking.\n<br>";
        //     echo "--> RAW output is " . print_r($aScreenName, true) . "\n<br>";
        //     return -1;
        // }

        // //TODO: Support TH.
        // if ($szScreenType == "Treasure Hunt Ranking") {
        //     echo "-> Treasure Hunt ranking are not yet supported. Skipping.\n<br>";
        //     return -1;
        // }

        $aNamesDirty = $this->OCR("$szFilename-names.png", 4, false);
        $aPoints = $this->OCR("$szFilename-pts.png", 4, true);

        if (!$bDebugKeepWorkImages) {
            unlink($szFilename);
            unlink("$szFilename-names.png");
            unlink("$szFilename-pts.png");
            unlink("$szFilename-filtered.png");
        }

        // Remove junks from names array.
        foreach ($aNamesDirty as $szName) {
            // Names require at least 4 characters.
            if (strlen($szName) >= 4) {
                $aNames[] = $szName;
            }
        }

        // Sanity checkup.
        if (count($aPoints) != count($aNames) || count($aNames) > 6) {
            echo "Error, names vs points mismatch for " . $szFilename . "\n<br>";
            echo "-> Names RAW data: " . print_r($aNames, true) . "<br>\n";
            echo "-> Points RAW data: " . print_r($aPoints, true) . "<br>\n";
            return -1;
        }

        // Build array indexed by position.
        $aComplete = array();
        for ($i = 0; $i < count($aPoints); $i++) {
            $szNameId = preg_replace('/[^A-Za-z0-9#]/', '', $aNames[$i]); // keep only alpha-numeric chars, except the #.
            $aComplete[] = array(
                'fullname' => $aNames[$i],
                'name_id' => $szNameId,
                'pts' => $aPoints[$i],
                'date' => $szCreatedDate,
                'timestamp' => $datetimeCreated->format('U'),
                'time_group' => $this->GetReferenceDate($datetimeCreated)
            );
        }
        return $aComplete;
    }

    private function OCR($szFilename, $uPsm, $bNumbersOnly)
    {
        // $aAcceptedChars = array_merge(range('A','Z'), range('a','z'), range(0,9));
        // array_push($aAcceptedChars, '#', '*', '\'', ' ', '_', '-');
        $aNumbersOnly = array();
        if ($bNumbersOnly) {
            $aNumbersOnly = range(0, 9);
        }

        $blob = (new TesseractOCR($szFilename))
            ->psm($uPsm)
            ->threadLimit(1) // Multi-threading is broken in Tesseract 4.x
            ->allowlist($aNumbersOnly)
            ->configFile('tesseract.ini')
            ->run();

        // $blob = (new TesseractOCR($szFilename))
        //     ->psm($uPsm)
        //     ->threadLimit(1)
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
    private function GetImageCreationDate($szFilename)
    {
        $dateTimeOriginal = '';

        if (PNGMetadata::isPNG($szFilename)) {
            $png_metadata = new PNGMetadata($szFilename); // return a 'ArrayObject' or 'Exception'
            $dateTimeOriginal = $png_metadata->get('exif:DateTimeOriginal');
        } else {
            $exif_metadata = exif_read_data($szFilename);
            if (isset($exif_metadata['DateTimeOriginal'])) {
                $dateTimeOriginal = $exif_metadata['DateTimeOriginal'];
            }
        }

        // Debug
        if ($dateTimeOriginal == "") {
            echo "Notice: Image does not contain any date/time metadata.<br>\n";
        }

        return $dateTimeOriginal;
    }

    /**
     * Return only the date (no time) in unix timestamp.
     */
    private function GetReferenceDate($dateTime)
    {
        $dateOnly = new DateTime($dateTime->format('Y-m-d'));
        return $dateOnly->format('U');
    }
}
