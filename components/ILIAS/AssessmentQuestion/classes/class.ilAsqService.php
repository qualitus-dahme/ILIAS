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

/**
 * Class ilAsqService
 *
 * @author    Björn Heyser <info@bjoernheyser.de>
 * @version    $Id$
 *
 * @package components/ILIAS/AssessmentQuestion
 */
class ilAsqService
{
    /**
     * @param ilCtrl $ctrl
     * @return string
     */
    public function fetchNextAuthoringCommandClass($nextClass): string
    {
        global $DIC; /* @var ILIAS\DI\Container $DIC */

        $row = $DIC->database()->fetchAssoc($DIC->database()->queryF(
            "SELECT COUNT(question_type_id) cnt FROM qpl_qst_type WHERE ctrl_class = %s",
            array('text'),
            array($nextClass)
        ));

        if ($row['cnt']) {
            // current next class is indeed an authoring ctrl class,
            // return it to have the switch(nextclass) case matching
            return $nextClass;
        }

        // the interface that NOT represents a valid ctrl class,
        // this will lead to a non matching switch(nextclass) case
        return 'ilasqquestionauthoring';
    }

    /**
     * @param ilQTIItem $qtiItem
     * @return string
     */
    public function determineQuestionTypeByQtiItem(ilQTIItem $qtiItem): string
    {
        // the qti service parses ILIAS question types, so use it
        // although this may get changed in the future
        return $qtiItem->getQuestiontype();
    }

    /**
     * @param integer $parentObjectId
     * @param string $questionTitle
     * @return bool
     */
    public function questionTitleExists($parentObjectId, $questionTitle): bool
    {
        global $DIC; /* @var ILIAS\DI\Container $DIC */

        $row = $DIC->database()->fetchAssoc($DIC->database()->queryF(
            "SELECT COUNT(question_id) cnt FROM qpl_questions WHERE obj_fi = %s AND title = %s",
            array('integer', 'text'),
            array($parentObjectId, $questionTitle)
        ));

        return $row['cnt'] > 0;
    }
}
