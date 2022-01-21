<?php

require_once __DIR__ . '/../../composer/vendor/autoload.php';

include 'models/CMemberPointsModel.php';

// Template rendering.
$loader = new \Twig\Loader\FilesystemLoader('./templates');
$twig = new \Twig\Environment($loader, array(
    'cache' => './writable/cache/twig/',
    'debug' => TRUE
));

date_default_timezone_set('UTC');

// Get dates and members from database.
$aAll = (new CMemberPointsModel)->GetLatestDates('Québec Kingdóm');
$AllDiff = ComputeMembersProgression($aAll);

// Render HTML
echo $twig->render('index_member_days.html.twig', [
    'days' => $AllDiff
]);

/**
 * Return associative array with points diff against previous day. For example:
 * 
 *     [1639173778] => Array
 *         (
 *             [0] => Array
 *                 (
 *                     [name] => Name1
 *                     [pts] => 6368
 *                     [diff] => 27
 *                 )
 *             [1] => Array
 *                 (
 *                     [name] => Name2
 *                     [pts] => 6107
 *                     [diff] => 30
 *                 )
 *         )
 */
function ComputeMembersProgression($aTimeGroups)
{
    $aAllProgress = array();
    $aTimeGroupKeys = array_keys($aTimeGroups);
    for ($i = 0; $i < count($aTimeGroupKeys); $i++) {
        $aMemberPoints = $aTimeGroups[$aTimeGroupKeys[$i]];
        $aMemberPointsOld = array();

        // If still an older date to come
        if ($i < count($aTimeGroupKeys) - 1) {
            $aMemberPointsOld = $aTimeGroups[$aTimeGroupKeys[$i + 1]];
        }

        $aMemberProgress = array();
        foreach ($aMemberPoints as $szName => $uPoints) {
            $uDiff = null;
            if (array_key_exists($szName, $aMemberPointsOld)) {
                $uDiff = $uPoints - $aMemberPointsOld[$szName];
            }
            
            $aMemberProgress[] = array('name' => $szName, 'pts' => $uPoints, 'diff' => $uDiff);
        }
        $aAllProgress[$aTimeGroupKeys[$i]] = $aMemberProgress;
    }
    return $aAllProgress;
}
