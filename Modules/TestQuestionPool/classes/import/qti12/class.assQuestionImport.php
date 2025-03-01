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

use ILIAS\Data\DataSize;
use ILIAS\Filesystem\Filesystem;
use ILIAS\TA\Questions\assQuestionSuggestedSolution;
use ILIAS\TA\Questions\assQuestionSuggestedSolutionsDatabaseRepository;

/**
* Class for question imports
*
* assQuestionImport is a basis class question imports
*
* @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
* @version	$Id$
* @ingroup ModulesTestQuestionPool
*/
class assQuestionImport
{
    /**
    * @var assQuestion
    */
    public $object;

    private Filesystem $filesystem;

    /**
    * assQuestionImport constructor
    *
    * @param object $a_object The question object
    * @access public
    */
    public function __construct($a_object)
    {
        $this->object = $a_object;

        /** @var ILIAS\DI\Container $DIC */
        global $DIC;
        $this->filesystem = $DIC['filesystem']->web();
    }

    public function getQuestionId(): int
    {
        return (int) $this->object->getId();
    }

    public function getFeedbackGeneric($item): array
    {
        $feedbacksgeneric = [];
        foreach ($item->resprocessing as $resprocessing) {
            foreach ($resprocessing->respcondition as $respcondition) {
                foreach ($respcondition->displayfeedback as $feedbackpointer) {
                    if (strlen($feedbackpointer->getLinkrefid())) {
                        foreach ($item->itemfeedback as $ifb) {
                            if (strcmp($ifb->getIdent(), "response_allcorrect") == 0) {
                                // found a feedback for the identifier
                                if (count($ifb->material)) {
                                    foreach ($ifb->material as $material) {
                                        $feedbacksgeneric[1] = $material;
                                    }
                                }
                                if ((count($ifb->flow_mat) > 0)) {
                                    foreach ($ifb->flow_mat as $fmat) {
                                        if (count($fmat->material)) {
                                            foreach ($fmat->material as $material) {
                                                $feedbacksgeneric[1] = $material;
                                            }
                                        }
                                    }
                                }
                            } elseif (strcmp($ifb->getIdent(), "response_onenotcorrect") == 0) {
                                // found a feedback for the identifier
                                if (count($ifb->material)) {
                                    foreach ($ifb->material as $material) {
                                        $feedbacksgeneric[0] = $material;
                                    }
                                }
                                if ((count($ifb->flow_mat) > 0)) {
                                    foreach ($ifb->flow_mat as $fmat) {
                                        if (count($fmat->material)) {
                                            foreach ($fmat->material as $material) {
                                                $feedbacksgeneric[0] = $material;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // handle the import of media objects in XHTML code
        foreach ($feedbacksgeneric as $correctness => $material) {
            $m = $this->QTIMaterialToString($material);
            $feedbacksgeneric[$correctness] = $m;
        }
        return $feedbacksgeneric;
    }

    /**
     * @param $feedbackIdent
     * @param string $prefix
     * @return int
     */
    protected function fetchIndexFromFeedbackIdent($feedbackIdent, $prefix = 'response_'): int
    {
        return (int) str_replace($prefix, '', $feedbackIdent);
    }

    /**
     * @param ilQTIItem $item
     * @param string $prefix
     * @return array
     */
    protected function getFeedbackAnswerSpecific(ilQTIItem $item, $prefix = 'response_'): array
    {
        $feedbacks = [];

        foreach ($item->itemfeedback as $ifb) {
            if ($ifb->getIdent() == 'response_allcorrect' || $ifb->getIdent() == 'response_onenotcorrect') {
                continue;
            }

            if ($ifb->getIdent() == $prefix . 'allcorrect' || $ifb->getIdent() == $prefix . 'onenotcorrect') {
                continue;
            }

            if (substr($ifb->getIdent(), 0, strlen($prefix)) != $prefix) {
                continue;
            }

            $ident = $ifb->getIdent();

            // found a feedback for the identifier

            if (count($ifb->material)) {
                foreach ($ifb->material as $material) {
                    $feedbacks[$ident] = $material;
                }
            }

            if ((count($ifb->flow_mat) > 0)) {
                foreach ($ifb->flow_mat as $fmat) {
                    if (count($fmat->material)) {
                        foreach ($fmat->material as $material) {
                            $feedbacks[$ident] = $material;
                        }
                    }
                }
            }
        }

        foreach ($feedbacks as $ident => $material) {
            $m = $this->QTIMaterialToString($material);
            $feedbacks[$ident] = $m;
        }

        return $feedbacks;
    }

    /**
    * Creates a question from a QTI file
    *
    * Receives parameters from a QTI parser and creates a valid ILIAS question object
    *
    * @param ilQtiItem $item The QTI item object
    * @param integer $questionpool_id The id of the parent questionpool
    * @param integer $tst_id The id of the parent test if the question is part of a test
    * @param object $tst_object A reference to the parent test object
    * @param integer $question_counter A reference to a question counter to count the questions of an imported question pool
    * @param array $import_mapping An array containing references to included ILIAS objects
    * @access public
    */
    public function fromXML(&$item, $questionpool_id, &$tst_id, &$tst_object, &$question_counter, $import_mapping): array
    {
    }

    /**
     * @param ilQTIItem $item
     */
    protected function addGeneralMetadata(ilQTIItem $item): void
    {
        if ($item->getMetadataEntry('externalID')) {
            $this->object->setExternalId($item->getMetadataEntry('externalID'));
        } else {
            $this->object->setExternalId($item->getMetadataEntry('externalId'));
        }

        $this->object->setLifecycle($this->fetchLifecycle($item));
    }

    /**
     * @param ilQTIItem $item
     * @return ilAssQuestionLifecycle
     */
    protected function fetchLifecycle(ilQTIItem $item): ilAssQuestionLifecycle
    {
        try {
            $lifecycle = ilAssQuestionLifecycle::getInstance(
                $item->getMetadataEntry('ilias_lifecycle')
            );
        } catch (ilTestQuestionPoolInvalidArgumentException $e) {
            try {
                $lomLifecycle = new ilAssQuestionLomLifecycle(
                    $item->getMetadataEntry('lifecycle')
                );

                $lifecycle = ilAssQuestionLifecycle::getInstance(
                    $lomLifecycle->getMappedIliasLifecycleIdentifer()
                );
            } catch (ilTestQuestionPoolInvalidArgumentException $e) {
                $lifecycle = ilAssQuestionLifecycle::getDraftInstance();
            }
        }

        return $lifecycle;
    }

    /**
     * returns the full path to extracted qpl import archiv (qpl import dir + qpl archiv subdir)
     */
    protected function getQplImportArchivDirectory(): string
    {
        return ilObjQuestionPool::_getImportDirectory() . '/' . ilSession::get("qpl_import_subdir");
    }

    /**
     * returns the full path to extracted tst import archiv (tst import dir + tst archiv subdir)
     */
    protected function getTstImportArchivDirectory(): string
    {
        return ilObjTest::_getImportDirectory() . '/' . ilSession::get("tst_import_subdir");
    }

    protected function processNonAbstractedImageReferences($text, $sourceNic): string
    {
        $reg = '/<img.*src=".*\\/mm_(\\d+)\\/(.*?)".*>/m';
        $matches = null;

        if (preg_match_all($reg, $text, $matches)) {
            $mobs = [];
            for ($i = 0, $max = count($matches[1]); $i < $max; $i++) {
                $mobSrcId = $matches[1][$i];
                $mobSrcName = $matches[2][$i];
                $mobSrcLabel = 'il_' . $sourceNic . '_mob_' . $mobSrcId;

                //if (!is_array(ilSession::get("import_mob_xhtml"))) {
                //    ilSession::set("import_mob_xhtml", array());
                //}

                //$_SESSION["import_mob_xhtml"][] = array(
                $mobs[] = [
                    "mob" => $mobSrcLabel, "uri" => 'objects/' . $mobSrcLabel . '/' . $mobSrcName
                ];
            }
            ilSession::set('import_mob_xhtml', $mobs);
        }

        return ilRTE::_replaceMediaObjectImageSrc($text, 0, $sourceNic);
    }

    /**
     * fetches the "additional content editing mode" information from qti item
     * and falls back to ADDITIONAL_CONTENT_EDITING_MODE_RTE when no or invalid information is given
     *
     * @final
     * @access protected
     * @return string $additionalContentEditingMode
     */
    final protected function fetchAdditionalContentEditingModeInformation($qtiItem): string
    {
        $additionalContentEditingMode = $qtiItem->getMetadataEntry('additional_cont_edit_mode');

        if (!$this->object->isValidAdditionalContentEditingMode($additionalContentEditingMode ?? '')) {
            $additionalContentEditingMode = assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_RTE;
        }

        return $additionalContentEditingMode;
    }

    public function importSuggestedSolutions(
        int $question_id,
        array $solution_from_import
    ): void {
        if (!$solution_from_import === []
            || !isset($solution_from_import[0]['solution'])) {
            return;
        }

        $solution_item = $solution_from_import[0]['solution'];
        $content = $solution_item->getContent();
        if ($solution_item->getTexttype() === 'application/json') {
            $content = json_decode($content, true);
            if (!isset($content['type']) || !isset($content['value'])) {
                return;
            }
            $type = $content['type'];
        } else {
            $type = $this->findSolutionTypeByValue($solution_item->getContent());
            if ($type === null) {
                return;
            }
        }

        $repo = $this->getSuggestedSolutionsRepo();
        $solution_object = $repo->create($question_id, $type);

        if ($type !== assQuestionSuggestedSolution::TYPE_FILE) {
            $link = is_string($content) ? $content : $content['value'];
            $adapted_link = $this->object->resolveInternalLink($link);
            if ($adapted_link === '') {
                return;
            }
            $solution_object = $solution_object
                ->withInternalLink($adapted_link)
                ->withImportId($link);
        } else {
            if (!isset($content['title']) || !isset($content['filename'])) {
                return;
            }
            $path = str_replace(CLIENT_WEB_DIR . DIRECTORY_SEPARATOR, '', $this->object->getSuggestedSolutionPath() . $content['filename']);
            $this->filesystem->put($path, base64_decode($content['value']));
            $solution_object = $solution_object->withTitle($content['title'])
                ->withFilename($content['filename'])
                ->withSize((int) $this->filesystem->getSize($path, DataSize::Byte)->inBytes())
                ->withMime($this->filesystem->getMimeType($path));
        }
        $repo->update([$solution_object]);
    }

    protected function findSolutionTypeByValue(string $value): ?string
    {
        foreach (array_keys(assQuestionSuggestedSolution::TYPES) as $type) {
            $search_type = '_' . $type . '_';
            if (strpos($value, $search_type) !== false) {
                return $type;
            }
        }
        return null;
    }


    protected ?assQuestionSuggestedSolutionsDatabaseRepository $suggestedsolution_repo = null;
    protected function getSuggestedSolutionsRepo(): assQuestionSuggestedSolutionsDatabaseRepository
    {
        if (is_null($this->suggestedsolution_repo)) {
            $dic = ilQuestionPoolDIC::dic();
            $this->suggestedsolution_repo = $dic['question.repo.suggestedsolutions'];
        }
        return $this->suggestedsolution_repo;
    }

    /**
     * Reads an QTI material tag and creates a text or XHTML string
     * @return string text or xhtml string
     */
    public function QTIMaterialToString(ilQTIMaterial $a_material): string
    {
        $result = "";
        $mobs = ilSession::get('import_mob_xhtml') ?? [];
        for ($i = 0; $i < $a_material->getMaterialCount(); $i++) {
            $material = $a_material->getMaterial($i);
            if (strcmp($material["type"], "mattext") === 0) {
                $result .= $material["material"]->getContent();
            }
            if (strcmp($material["type"], "matimage") === 0) {
                $matimage = $material["material"];
                if (preg_match("/(il_([0-9]+)_mob_([0-9]+))/", $matimage->getLabel(), $matches)) {
                    // import an mediaobject which was inserted using tiny mce
                    //if (!is_array(ilSession::get("import_mob_xhtml"))) {
                    //    ilSession::set("import_mob_xhtml", array());
                    //}
                    $mobs[] = ["mob" => $matimage->getLabel(),
                                    "uri" => $matimage->getUri()
                    ];
                }
            }
        }
        ilSession::set('import_mob_xhtml', $mobs);
        return $result;
    }

    protected function deduceThumbSizeFromImportValue(?int $size): int
    {
        if ($size === null) {
            return $this->object->getThumbSize();
        }

        if ($size > $this->object->getMaximumThumbSize()) {
            return $this->object->getMaximumThumbSize();
        }

        return $size;
    }
}
