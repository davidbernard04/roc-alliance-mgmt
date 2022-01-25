<?php

/**
 *  Interfaces the 'member_points' table.
 */
class CMemberPointsModel
{
    private $m_pDb = null;
    private $m_szPath = 'writable/database/riseofculture.db';
    
    public function __construct()
    {
        $this->m_pDb = new SQLite3($this->m_szPath, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
        chmod($this->m_szPath, 0664); // make sure we can write inside afterwards.

        $this->m_pDb->exec('CREATE TABLE IF NOT EXISTS member_points(
            id INTEGER PRIMARY KEY, alliance_id TEXT, name_id TEXT, fullname TEXT, mp_points INT, mp_real_datetime TEXT, mp_time_group INT,
            UNIQUE(name_id, mp_time_group) ON CONFLICT REPLACE)');
    }

    public function __destruct()
    {
        // Close the DB, even if done automatically when script ends.
        $this->m_pDb->close();
    }

    public function InsertMembers($szAllianceId, $aMembers)
    {
        // Prepare the query for faster batch insert.
        $sqlStatement = $this->m_pDb->prepare('INSERT INTO member_points (alliance_id, name_id, fullname, mp_points, mp_real_datetime, mp_time_group) 
                                                VALUES (:alliance_id, :name_id, :fullname, :mp_points, :mp_real_datetime, :mp_time_group)');

        $this->m_pDb->exec('BEGIN');
        foreach ($aMembers as $m)
        {
            $sqlStatement->bindValue(':alliance_id', $szAllianceId, SQLITE3_TEXT);
            $sqlStatement->bindValue(':name_id', $m['name_id'], SQLITE3_TEXT);
            $sqlStatement->bindValue(':fullname', $m['fullname'], SQLITE3_TEXT);
            $sqlStatement->bindValue(':mp_points', $m['pts'], SQLITE3_INTEGER);
            $sqlStatement->bindValue(':mp_real_datetime', $m['date'], SQLITE3_TEXT);
            $sqlStatement->bindValue(':mp_time_group', $m['time_group'], SQLITE3_INTEGER);
            $res = $sqlStatement->execute();
            $sqlStatement->reset();
        }
        $this->m_pDb->exec('COMMIT');
    }

    /**
     * Ordered by most recent first, and order by points. For example:
     * 
     *     [1639173778] => Array
     *         (
     *             [Name1] => 4829
     *             [Name2] => 4703
     *             [Name3] => 3910
     *         )
     */
    public function GetLatestDates($szAllianceId)
    {
        $aDates = array();
        $aFull = array();
        $u_NB_DATES_MAX = 5;

        // First get latest time groups.
        $sqlStatement = $this->m_pDb->prepare('SELECT mp_time_group FROM member_points WHERE alliance_id = :alliance_id' .
            " GROUP BY mp_time_group ORDER BY mp_time_group DESC LIMIT $u_NB_DATES_MAX");
        $sqlStatement->bindValue(':alliance_id', $szAllianceId, SQLITE3_TEXT);
        $res = $sqlStatement->execute();

        while ($row = $res->fetchArray(SQLITE3_NUM)) {
            array_push($aDates, $row[0]);
        }

        // Then members per time groups.
        $sqlStatement = $this->m_pDb->prepare('SELECT name_id, mp_points FROM member_points ' .
            ' WHERE alliance_id = :alliance_id AND mp_time_group = :mp_time_group ORDER BY mp_points DESC');

        for ($i = 0; $i < count($aDates); $i++) {
            $uDate = $aDates[$i];
            $sqlStatement->bindValue(':alliance_id', $szAllianceId, SQLITE3_TEXT);
            $sqlStatement->bindValue(':mp_time_group', $uDate, SQLITE3_INTEGER);
            $res = $sqlStatement->execute();
            $sqlStatement->reset();

            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $aFull[$uDate][$row['name_id']] = $row['mp_points'];
            }
        }

        return $aFull;
    }
}
