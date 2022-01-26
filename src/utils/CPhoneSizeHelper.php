<?php

/**
 * Compute the pixel coordinates from variable screenshots size.
 */
class CPhoneSizeHelper
{
    private $m_uImageWidth = null;
    private $m_uImageHeight = null;

    private $m_uMenuArrowWidth = null;
    private $m_uRectangleWidth = null;

    private $m_uLeftMargin = null;

    // TODO: Document constants and ideally derive from a reference image and coordinates.

    public function __construct($uImageWidth, $uImageHeight)
    {
        $this->m_uImageHeight = $uImageHeight;
        $this->m_uImageWidth = $uImageWidth;

        // DESIGN_NOTES: Assume image-height/rectangle-width ratio is constant among phones.
        //      The width is not constant and margins are added besides the Big Rectangle.
        //      The margin is somewhat the same on left and right size, but the left side as fixed-width button.
        $menuArrowWidth = $this->m_uImageHeight / 4.625;
        $this->m_uMenuArrowWidth = $menuArrowWidth + ($menuArrowWidth * 0.058536); // menu buttons + 5.8% padding

        // The big rectangle around member names and points.
        $this->m_uRectangleWidth = $this->m_uImageHeight / 0.7165; 

        // Compute the left margin (variable width on the left side of the big rectangle).
        $totalMargin = $this->m_uImageWidth - $this->m_uRectangleWidth - $this->m_uMenuArrowWidth;
        $this->m_uLeftMargin = ($totalMargin + ($this->m_uMenuArrowWidth*2))/2;
    }

    public function GetNamesCoordinates()
    {
        $y = $this->m_uImageHeight * 0.279;
        $height = $this->m_uImageHeight - $y;

        $width = $this->m_uRectangleWidth / 5.234;
        $x = $this->m_uRectangleWidth * 0.198 + $this->m_uLeftMargin ;

        return ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
    }

    public function GetPointsCoordinates()
    {
        $y = $this->m_uImageHeight * 0.279;
        $height = $this->m_uImageHeight - $y;

        $width = $this->m_uRectangleWidth / 11.63;
        $x = $this->m_uRectangleWidth * 0.61 + $this->m_uLeftMargin - $width;

        return ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
    }
}
