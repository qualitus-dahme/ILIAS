<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package     Modules/Test
 */
class ilTestSkillLevelThresholdList
{
    /**
     * @var ilDBInterface
     */
    private $db;

    /**
     * @var integer
     */
    private $testId;

    /**
     * @var array
     */
    private $thresholds = array();

    public function __construct(ilDBInterface $db)
    {
        $this->db = $db;
    }

    /**
     * @param int $testId
     */
    public function setTestId($testId)
    {
        $this->testId = $testId;
    }

    /**
     * @return int
     */
    public function getTestId(): int
    {
        return $this->testId;
    }

    public function resetThresholds()
    {
        $this->thresholds = array();
    }

    public function loadFromDb()
    {
        $this->resetThresholds();

        $query = "
			SELECT test_fi, skill_base_fi, skill_tref_fi, skill_level_fi, threshold
			FROM tst_skl_thresholds
			WHERE test_fi = %s
		";

        $res = $this->db->queryF($query, array('integer'), array($this->getTestId()));

        while ($row = $this->db->fetchAssoc($res)) {
            $threshold = $this->buildSkillLevelThresholdByArray($row);
            $this->addThreshold($threshold);
        }
    }

    /**
     */
    public function saveToDb()
    {
        foreach ($this->thresholds as $skillKey => $skillLevels) {
            foreach ($skillLevels as $levelThreshold) {
                /* @var ilTestSkillLevelThreshold $levelThreshold */
                $levelThreshold->saveToDb();
            }
        }
    }

    /**
     * @param ilTestSkillLevelThreshold $threshold
     */
    public function addThreshold($threshold)
    {
        $skillKey = $threshold->getSkillBaseId() . ':' . $threshold->getSkillTrefId();
        $this->thresholds[$skillKey][$threshold->getSkillLevelId()] = $threshold;
    }

    /**
     * @param array $data
     * @return ilTestSkillLevelThreshold
     */
    private function buildSkillLevelThresholdByArray($data): ilTestSkillLevelThreshold
    {
        $threshold = new ilTestSkillLevelThreshold($this->db);

        $threshold->setTestId($data['test_fi']);
        $threshold->setSkillBaseId($data['skill_base_fi']);
        $threshold->setSkillTrefId($data['skill_tref_fi']);
        $threshold->setSkillLevelId($data['skill_level_fi']);
        $threshold->setThreshold($data['threshold']);

        return $threshold;
    }

    /**
     * @param $skillBaseId
     * @param $skillTrefId
     * @param $skillLevelId
     * @return ilTestSkillLevelThreshold
     */
    public function getThreshold($skillBaseId, $skillTrefId, $skillLevelId, $forceObject = false): ?ilTestSkillLevelThreshold
    {
        $skillKey = $skillBaseId . ':' . $skillTrefId;

        if (isset($this->thresholds[$skillKey]) && isset($this->thresholds[$skillKey][$skillLevelId])) {
            return $this->thresholds[$skillKey][$skillLevelId];
        }

        if ($forceObject) {
            $threshold = new ilTestSkillLevelThreshold($this->db);

            $threshold->setTestId($this->getTestId());
            $threshold->setSkillBaseId($skillBaseId);
            $threshold->setSkillTrefId($skillTrefId);
            $threshold->setSkillLevelId($skillLevelId);

            return $threshold;
        }

        return null;
    }

    public function cloneListForTest($testId)
    {
        foreach ($this->thresholds as $data) {
            foreach ($data as $threshold) {
                /* @var ilTestSkillLevelThreshold $threshold */

                $threshold->setTestId($testId);
                $threshold->saveToDb();

                $threshold->setTestId($this->getTestId());
            }
        }
    }
}
