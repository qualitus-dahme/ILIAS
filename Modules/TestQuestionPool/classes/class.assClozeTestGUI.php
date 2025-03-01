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

use ILIAS\Refinery\Random\Group as RandomGroup;
use ILIAS\Refinery\Random\Seed;
use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\Refinery\Factory;
use ILIAS\Refinery\KindlyTo\Transformation\StringTransformation;

/**
 * Cloze test question GUI representation
 *
 * The assClozeTestGUI class encapsulates the GUI representation
 * for cloze test questions.
 *
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 *
 * @version		$Id$
 *
 * @ingroup 	ModulesTestQuestionPool
 * @ilCtrl_Calls assClozeTestGUI: ilFormPropertyDispatchGUI
 */
class assClozeTestGUI extends assQuestionGUI implements ilGuiQuestionScoringAdjustable, ilGuiAnswerScoringAdjustable
{
    public const OLD_CLOZE_TEST_UI = false;

    public const JS_INSERT_GAP_CODE_AT_CARET = <<<JS
    jQuery.fn.extend({
        insertGapCodeAtCaret: function() {
            return this.each(function(i) {
                var code_start = "[gap]"
                var code_end = "[/gap]"
                if (typeof tinyMCE != "undefined" && typeof tinyMCE.get('cloze_text') != "undefined") {
                    var ed =  tinyMCE.get('cloze_text');
                    il.ClozeHelper.internetExplorerTinyMCECursorFix(ed);
                    ed.selection.setContent(code_start + ed.selection.getContent() + code_end);
                    ed.focus();
                    return;
                }
                if (document.selection) {
                    //For browsers like Internet Explorer
                    this.focus();
                    sel = document.selection.createRange();
                    sel.text = code_start + sel.text + code_end;
                    this.focus();
                } else if (this.selectionStart || this.selectionStart == '0') {
                    //For browsers like Firefox and Webkit based
                    var startPos = this.selectionStart;
                    var endPos = this.selectionEnd;
                    var scrollTop = this.scrollTop;
                    this.value = this.value.substring(0, startPos)
                            + code_start
                            + this.value.substring(startPos, endPos)
                            + code_end
                            + this.value.substring(endPos, this.value.length);
                    this.focus();
                    this.scrollTop = scrollTop;
                } else {
                    this.value += code_start + code_end;
                    this.focus();
                }
            });
        }
    });
JS;

    /**
    * A temporary variable to store gap indexes of ilCtrl commands in the getCommand method
    */
    private $gapIndex;

    private RandomGroup $randomGroup;
    private Container $dic;
    private Factory $refinery;
    private ArrayBasedRequestWrapper $post;
    private \ILIAS\UI\Factory $ui_factory;
    private \ILIAS\UI\Renderer $ui_renderer;

    /**
    * assClozeTestGUI constructor
    *
    * @param integer $id The database id of a image map question object
    */
    public function __construct(int $id = -1)
    {
        parent::__construct();
        global $DIC;
        $this->dic = $DIC;
        $this->ui_factory = $DIC->ui()->factory();
        $this->ui_renderer = $DIC->ui()->renderer();
        $this->refinery = $this->dic->refinery();
        $this->post = $this->dic->http()->wrapper()->post();

        $this->object = new assClozeTest();
        if ($id >= 0) {
            $this->object->loadFromDb($id);
        }

        $this->randomGroup = $this->refinery->random();
    }

    public function getCommand($cmd)
    {
        if (preg_match("/^(removegap|addgap)_(\d+)$/", $cmd, $matches)) {
            $cmd = $matches[1];
            $this->gapIndex = $matches[2];
        }
        return $cmd;
    }

    /**
     * {@inheritdoc}
     */
    protected function writePostData(bool $always = false): int
    {
        $hasErrors = (!$always) ? $this->editQuestion(true) : false;
        if ($hasErrors) {
            return 1;
        }

        $cloze_text = $this->removeIndizesFromGapText(
            $this->request->raw('cloze_text')
        );

        $this->object->setQuestion(
            $this->request->raw('question')
        );

        $this->writeQuestionGenericPostData();
        $this->object->setClozeText($cloze_text);
        $this->object->setTextgapRating($this->request->raw('textgap_rating'));
        $this->object->setIdenticalScoring((bool) ($this->request->raw('identical_scoring') ?? false));
        $this->object->setFixedTextLength(($this->request->int('fixedTextLength') ?? 0));
        $this->writeAnswerSpecificPostData(new ilPropertyFormGUI());
        $this->saveTaxonomyAssignments();
        return 0;
    }

    public function writeAnswerSpecificPostData(ilPropertyFormGUI $form): void
    {
        if (!$this->post->has('gap')) {
            return;
        }

        $gaps = $this->post->retrieve(
            "gap",
            $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->string())
        );

        if ($this->ctrl->getCmd() !== 'createGaps') {
            $this->object->clearGapAnswers();
        }

        foreach ($gaps as $idx => $hidden) {
            $clozetype = $this->post->retrieve(
                "clozetype_" . $idx,
                $this->refinery->kindlyTo()->string()
            );

            $this->object->setGapType($idx, $clozetype);

            switch ($clozetype) {
                case CLOZE_TEXT:

                    $this->object->setGapShuffle($idx, 0);

                    if ($this->ctrl->getCmd() != 'createGaps') {
                        if (is_array($_POST['gap_' . $idx]['answer'])) {
                            foreach ($_POST['gap_' . $idx]['answer'] as $order => $value) {
                                $this->object->addGapAnswer($idx, $order, $value);
                            }
                        } else {
                            $this->object->addGapAnswer($idx, 0, '');
                        }
                    }

                    if (is_array($_POST['gap_' . $idx]['points'])) {
                        foreach ($_POST['gap_' . $idx]['points'] as $order => $value) {
                            $this->object->setGapAnswerPoints($idx, $order, $value);
                        }
                    }

                    $k_gapsize = 'gap_' . $idx . '_gapsize';
                    if ($this->request->isset($k_gapsize)) {
                        $this->object->setGapSize($idx, $_POST[$k_gapsize]);
                    }
                    break;

                case CLOZE_SELECT:

                    $this->object->setGapShuffle($idx, (int) (isset($_POST["shuffle_$idx"]) && $_POST["shuffle_$idx"]));

                    if ($this->ctrl->getCmd() != 'createGaps') {
                        if (is_array($_POST['gap_' . $idx]['answer'])) {
                            foreach ($_POST['gap_' . $idx]['answer'] as $order => $value) {
                                $this->object->addGapAnswer($idx, $order, $value);
                            }
                        } else {
                            $this->object->addGapAnswer($idx, 0, '');
                        }
                    }

                    if (is_array($_POST['gap_' . $idx]['points'])) {
                        foreach ($_POST['gap_' . $idx]['points'] as $order => $value) {
                            $this->object->setGapAnswerPoints($idx, $order, $value);
                        }
                    }
                    break;

                case CLOZE_NUMERIC:

                    $this->object->setGapShuffle($idx, 0);

                    $gap = $this->object->getGap($idx);
                    if (!$gap) {
                        break;
                    }

                    $this->object->getGap($idx)->clearItems();

                    if ($this->post->has('gap_' . $idx . '_numeric')) {
                        if ($this->ctrl->getCmd() !== 'createGaps') {
                            $this->object->addGapAnswer(
                                $idx,
                                0,
                                str_replace(",", ".", $_POST['gap_' . $idx . '_numeric'])
                            );
                        }

                        $this->object->setGapAnswerLowerBound(
                            $idx,
                            0,
                            str_replace(",", ".", $_POST['gap_' . $idx . '_numeric_lower'])
                        );

                        $this->object->setGapAnswerUpperBound(
                            $idx,
                            0,
                            str_replace(",", ".", $_POST['gap_' . $idx . '_numeric_upper'])
                        );

                        $this->object->setGapAnswerPoints($idx, 0, $_POST['gap_' . $idx . '_numeric_points']);
                    } else {
                        if ($this->ctrl->getCmd() != 'createGaps') {
                            $this->object->addGapAnswer($idx, 0, '');
                        }

                        $this->object->setGapAnswerLowerBound($idx, 0, '');

                        $this->object->setGapAnswerUpperBound($idx, 0, '');
                    }

                    if ($this->post->has('gap_' . $idx . '_gapsize')) {
                        $this->object->setGapSize($idx, $_POST['gap_' . $idx . '_gapsize']);
                    }
                    break;
            }
            $assClozeGapCombinationObject = new assClozeGapCombination();
            $assClozeGapCombinationObject->clearGapCombinationsFromDb($this->object->getId());
            if (
                isset($_POST['gap_combination']) &&
                is_array($_POST['gap_combination']) &&
                count($_POST['gap_combination']) > 0
            ) {
                $assClozeGapCombinationObject->saveGapCombinationToDb(
                    $this->object->getId(),
                    $_POST['gap_combination'],
                    $_POST['gap_combination_values']
                );
            }
        }
        if ($this->ctrl->getCmd() != 'createGaps') {
            $this->object->updateClozeTextFromGaps();
        }
    }

    public function writeQuestionSpecificPostData(ilPropertyFormGUI $form): void
    {
        $this->object->setClozeText($_POST['cloze_text']);
        $this->object->setTextgapRating($_POST["textgap_rating"]);
        $this->object->setIdenticalScoring($_POST["identical_scoring"]);
        $this->object->setFixedTextLength($_POST["fixedTextLength"]);
    }

    /**
    * Creates an output of the edit form for the question
    *
    * @access public
    */
    public function editQuestion($checkonly = false): bool
    {
        $save = $this->isSaveCommand();
        $this->getQuestionTemplate();

        $form = new ilPropertyFormGUI();
        $this->editForm = $form;

        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->outQuestionType());
        $form->setMultipart(false);
        $form->setTableWidth("100%");
        $form->setId("assclozetest");

        $this->addBasicQuestionFormProperties($form);
        $this->populateQuestionSpecificFormPart($form);
        $this->populateAnswerSpecificFormPart($form);
        $this->populateTaxonomyFormSection($form);

        $this->addQuestionFormCommandButtons($form);

        $errors = false;

        if ($save) {
            $form->setValuesByPost();
            $errors = !$form->checkInput();
            $form->setValuesByPost();

            $gap_combinations = $this->request->raw('gap_combination');
            if (is_array($gap_combinations)
                && $gap_combinations !== []
                && $this->hasErrorInGapCombinationPoints($gap_combinations)) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('points_non_numeric_or_negative_msg'));
                $errors = true;
            }

            if ($errors) {
                $checkonly = false;
            }
        }

        if (!$checkonly) {
            $modal = ilModalGUI::getInstance();
            $modal->setHeading($this->lng->txt(''));
            $modal->setId('ilGapModal');
            $modal->setBody('');
            $this->tpl->setVariable("QUESTION_DATA", $form->getHTML() . $modal->getHTML());
        }
        return $errors;
    }

    private function hasErrorInGapCombinationPoints(array $gap_combinations): bool
    {
        foreach ($gap_combinations['points'] as $gaps_points) {
            foreach ($gaps_points as $points) {
                $points_standardized = str_replace(',', '.', $points);
                if (!is_numeric($points_standardized)
                    || (float) $points_standardized < 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function addBasicQuestionFormProperties(ilPropertyFormGUI $form): void
    {
        // title
        $title = new ilTextInputGUI($this->lng->txt("title"), "title");
        $title->setMaxLength(100);
        $title->setValue($this->object->getTitle());
        $title->setRequired(true);
        $form->addItem($title);

        if (!$this->object->getSelfAssessmentEditingMode()) {
            // author
            $author = new ilTextInputGUI($this->lng->txt("author"), "author");
            $author->setValue($this->object->getAuthor());
            $author->setMaxLength(512);
            $author->setRequired(true);
            $form->addItem($author);

            // description
            $description = new ilTextInputGUI($this->lng->txt("description"), "comment");
            $description->setValue($this->object->getComment());
            $description->setRequired(false);
            $form->addItem($description);
        } else {
            // author as hidden field
            $hi = new ilHiddenInputGUI("author");
            $author = ilLegacyFormElementsUtil::prepareFormOutput($this->object->getAuthor());
            if (trim($author) == "") {
                $author = "-";
            }
            $hi->setValue($author);
            $form->addItem($hi);
        }

        // lifecycle
        $lifecycle = new ilSelectInputGUI($this->lng->txt('qst_lifecycle'), 'lifecycle');
        $lifecycle->setOptions($this->object->getLifecycle()->getSelectOptions($this->lng));
        $lifecycle->setValue($this->object->getLifecycle()->getIdentifier());
        $form->addItem($lifecycle);

        // questiontext
        $question = new ilTextAreaInputGUI($this->lng->txt("question"), "question");
        $question->setValue($this->object->getQuestion());
        $question->setRequired(true);
        $question->setRows(10);
        $question->setCols(80);
        if (!$this->object->getSelfAssessmentEditingMode()) {
            if ($this->object->getAdditionalContentEditingMode() == assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_RTE) {
                $question->setUseRte(true);
                $question->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
                $question->addPlugin("latex");
                $question->addButton("latex");
                $question->addButton("pastelatex");
                $question->setRTESupport($this->object->getId(), "qpl", "assessment");
            }
        } else {
            $question->setRteTags(ilAssSelfAssessmentQuestionFormatter::getSelfAssessmentTags());
            $question->setUseTagsForRteOnly(false);
        }
        $form->addItem($question);
        $this->addNumberOfTriesToFormIfNecessary($form);
    }

    public function populateQuestionSpecificFormPart(ilPropertyFormGUI $form): ilPropertyFormGUI
    {
        // cloze text
        $cloze_text = new ilTextAreaInputGUI($this->lng->txt("cloze_text"), 'cloze_text');
        $cloze_text->setRequired(true);
        $cloze_text->setValue($this->applyIndizesToGapText($this->object->getClozeText()));
        $cloze_text->setInfo($this->lng->txt("close_text_hint"));
        $cloze_text->setRows(10);
        $cloze_text->setCols(80);
        if (!$this->object->getSelfAssessmentEditingMode()) {
            if ($this->object->getAdditionalContentEditingMode() == assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_RTE) {
                $cloze_text->setUseRte(true);
                $cloze_text->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
                $cloze_text->addPlugin("latex");
                $cloze_text->addButton("latex");
                $cloze_text->addButton("pastelatex");
            }
        } else {
            $cloze_text->setRteTags(ilAssSelfAssessmentQuestionFormatter::getSelfAssessmentTags());
            $cloze_text->setUseTagsForRteOnly(false);
        }
        $cloze_text->setRTESupport($this->object->getId(), "qpl", "assessment");
        $form->addItem($cloze_text);

        $tpl = new ilTemplate("tpl.il_as_qpl_cloze_gap_button_code.html", true, true, "Modules/TestQuestionPool");

        $button = new ilCustomInputGUI('&nbsp;', '');

        $button_text_gap = $this->ui_factory->button()->standard($this->lng->txt('text_gap'), '')
            ->withAdditionalOnLoadCode(
                $this->getAddGapButtonClickClosure('text')
            );
        $button_select_gap = $this->ui_factory->button()->standard($this->lng->txt('select_gap'), '')
            ->withAdditionalOnLoadCode(
                $this->getAddGapButtonClickClosure('select')
            );
        $button_numeric_gap = $this->ui_factory->button()->standard($this->lng->txt('numeric_gap'), '')
            ->withAdditionalOnLoadCode(
                $this->getAddGapButtonClickClosure('numeric')
            );

        $tpl->setVariable('BUTTON', $this->ui_renderer->render([
            $button_text_gap,
            $button_select_gap,
            $button_numeric_gap
        ]));
        $tpl->parseCurrentBlock();

        $button->setHtml($tpl->get());
        $this->tpl->addOnloadCode(self::JS_INSERT_GAP_CODE_AT_CARET);
        $form->addItem($button);

        // text rating
        if (!$this->object->getSelfAssessmentEditingMode()) {
            $textrating = new ilSelectInputGUI($this->lng->txt("text_rating"), "textgap_rating");
            $text_options = [
                "ci" => $this->lng->txt("cloze_textgap_case_insensitive"),
                "cs" => $this->lng->txt("cloze_textgap_case_sensitive"),
                "l1" => sprintf($this->lng->txt("cloze_textgap_levenshtein_of"), "1"),
                "l2" => sprintf($this->lng->txt("cloze_textgap_levenshtein_of"), "2"),
                "l3" => sprintf($this->lng->txt("cloze_textgap_levenshtein_of"), "3"),
                "l4" => sprintf($this->lng->txt("cloze_textgap_levenshtein_of"), "4"),
                "l5" => sprintf($this->lng->txt("cloze_textgap_levenshtein_of"), "5")
            ];
            $textrating->setOptions($text_options);
            $textrating->setValue($this->object->getTextgapRating());
            $form->addItem($textrating);

            // text field length
            $fixedTextLength = new ilNumberInputGUI($this->lng->txt("cloze_fixed_textlength"), "fixedTextLength");
            $ftl = $this->object->getFixedTextLength();

            $fixedTextLength->setValue($ftl > 0 ? $ftl : '');
            $fixedTextLength->setMinValue(0);
            $fixedTextLength->setSize(3);
            $fixedTextLength->setMaxLength(6);
            $fixedTextLength->setInfo($this->lng->txt('cloze_fixed_textlength_description'));
            $fixedTextLength->setRequired(false);
            $form->addItem($fixedTextLength);

            // identical scoring
            $identical_scoring = new ilCheckboxInputGUI($this->lng->txt("identical_scoring"), "identical_scoring");
            $identical_scoring->setValue(1);
            $identical_scoring->setChecked($this->object->getIdenticalScoring());
            $identical_scoring->setInfo($this->lng->txt('identical_scoring_desc'));
            $identical_scoring->setRequired(false);
            $form->addItem($identical_scoring);
        }
        return $form;
    }

    private function getAddGapButtonClickClosure(string $gap_type): Closure
    {
        return fn($id) => "var el = document.getElementById('{$id}').addEventListener('click', "
            . '(e) => {'
            . ' e.preventDefault();'
            . " ClozeQuestionGapBuilder.addGapClickFunction('{$gap_type}');"
            . '});';
    }

    public function populateAnswerSpecificFormPart(ilPropertyFormGUI $form): ilPropertyFormGUI
    {
        if (self::OLD_CLOZE_TEST_UI) {
            for ($gapCounter = 0; $gapCounter < $this->object->getGapCount(); $gapCounter++) {
                $this->populateGapFormPart($form, $gapCounter);
            }
            return $form;
        } else {
            $json = $this->populateJSON();
            $assClozeGapCombinationObject = new assClozeGapCombination();
            $combination_exists = $assClozeGapCombinationObject->combinationExistsForQid($this->object->getId());
            $combinations = [];
            if ($combination_exists) {
                $combinations = $assClozeGapCombinationObject->loadFromDb($this->object->getId());
            }
            $new_builder = new ilClozeGapInputBuilderGUI();
            $header = new ilFormSectionHeaderGUI();
            $form->addItem($header);
            $new_builder->setValueByArray($json);
            $new_builder->setValueCombinationFromDb($combinations);
            $form->addItem($new_builder);
            return $form;
        }
    }

    protected function populateJSON(): array
    {
        $gap = $this->object->getGaps();
        $array = [];
        if ($gap == null) {
            return $array;
        }
        $translate_type = ['text','select','numeric'];
        $i = 0;
        foreach ($gap as $content) {
            $shuffle = false;
            $value = $content->getItemsRaw();
            $items = [];
            for ($j = 0, $jMax = count($value); $j < $jMax; $j++) {
                if ($content->isNumericGap()) {
                    $items[$j] = [
                        'answer' => $value[$j]->getAnswerText(),
                        'lower' => $value[$j]->getLowerBound(),
                        'upper' => $value[$j]->getUpperBound(),
                        'points' => $value[$j]->getPoints(),
                        'error' => false
                    ];
                } else {
                    $items[$j] = [
                        'answer' => $this->escapeTemplatePlaceholders($value[$j]->getAnswerText()),
                        'points' => $value[$j]->getPoints(),
                        'error' => false
                    ];

                    if ($content->isSelectGap()) {
                        $shuffle = $content->getShuffle();
                    }
                }
            }
            $answers[$i] = [
            'type' => $translate_type[$content->getType()] ,
            'values' => $items ,
            'shuffle' => $shuffle,
            'text_field_length' => $content->getGapSize() > 0 ? $content->getGapSize() : '',
            'used_in_gap_combination' => true];
            $i++;
        }
        return $answers;
    }
    /**
     * Populates a gap form-part.
     *
     * This includes: A section header with the according gap-ordinal, the type select-box.
     * Furthermore, this method calls the gap-type-specific methods for their contents.
     *
     * @param $form	 		ilPropertyFormGUI	Reference to the form, that receives the point.
     * @param $gapCounter	integer				Ordinal number of the gap in the sequence of gaps
     *
     * @return ilPropertyFormGUI
     */
    protected function populateGapFormPart($form, $gapCounter): ilPropertyFormGUI
    {
        $gap = $this->object->getGap($gapCounter);

        if ($gap == null) {
            return $form;
        }

        $header = new ilFormSectionHeaderGUI();
        $header->setTitle($this->lng->txt("gap") . " " . ($gapCounter + 1));
        $form->addItem($header);

        $gapcounter = new ilHiddenInputGUI("gap[$gapCounter]");
        $gapcounter->setValue($gapCounter);
        $form->addItem($gapcounter);

        $gaptype = new ilSelectInputGUI($this->lng->txt('type'), "clozetype_$gapCounter");
        $options = [
            0 => $this->lng->txt("text_gap"),
            1 => $this->lng->txt("select_gap"),
            2 => $this->lng->txt("numeric_gap")
        ];
        $gaptype->setOptions($options);
        $gaptype->setValue($gap->getType());
        $form->addItem($gaptype);

        if ($gap->getType() == CLOZE_TEXT) {
            $this->populateGapSizeFormPart($form, $gap, $gapCounter);

            if (count($gap->getItemsRaw()) == 0) {
                $gap->addItem(new assAnswerCloze("", 0, 0));
            }
            $this->populateTextGapFormPart($form, $gap, $gapCounter);
        } elseif ($gap->getType() == CLOZE_SELECT) {
            if (count($gap->getItemsRaw()) == 0) {
                $gap->addItem(new assAnswerCloze("", 0, 0));
            }
            $this->populateSelectGapFormPart($form, $gap, $gapCounter);
        } elseif ($gap->getType() == CLOZE_NUMERIC) {
            $this->populateGapSizeFormPart($form, $gap, $gapCounter);

            if (count($gap->getItemsRaw()) == 0) {
                $gap->addItem(new assAnswerCloze("", 0, 0));
            }
            foreach ($gap->getItemsRaw() as $item) {
                $this->populateNumericGapFormPart($form, $item, $gapCounter);
            }
        }
        return $form;
    }

    /**
     * @param $form			ilPropertyFormGUI	Reference to the form, that receives the point.
     * @param $gap			mixed				Raw text gap item.
     * @param $gapCounter	integer				Ordinal number of the gap in the sequence of gaps
     */
    protected function populateGapSizeFormPart($form, $gap, $gapCounter): ilPropertyFormGUI
    {
        $gapSizeFormItem = new ilNumberInputGUI($this->lng->txt('cloze_fixed_textlength'), "gap_" . $gapCounter . '_gapsize');

        $gapSizeFormItem->allowDecimals(false);
        $gapSizeFormItem->setMinValue(0);
        $gapSizeFormItem->setSize(3);
        $gapSizeFormItem->setMaxLength(6);
        $gapSizeFormItem->setInfo($this->lng->txt('cloze_gap_size_info'));
        $gapSizeFormItem->setValue($gap->getGapSize());
        $form->addItem($gapSizeFormItem);

        return $form;
    }

    /**
     * Populates the form-part for a select gap.
     *
     * This includes: The AnswerWizardGUI for the individual select items and points as well as
     * the the checkbox for the shuffle option.
     *
     * @param $form			ilPropertyFormGUI	Reference to the form, that receives the point.
     * @param $gap			mixed				Raw text gap item.
     * @param $gapCounter	integer				Ordinal number of the gap in the sequence of gaps
     *
     * @return ilPropertyFormGUI
     */
    protected function populateSelectGapFormPart($form, $gap, $gapCounter): ilPropertyFormGUI
    {
        $values = new ilAnswerWizardInputGUI($this->lng->txt("values"), "gap_" . $gapCounter . "");
        $values->setRequired(true);
        $values->setQuestionObject($this->object);
        $values->setSingleline(true);
        $values->setAllowMove(false);

        $values->setValues($gap->getItemsRaw());
        $form->addItem($values);

        // shuffle
        $shuffle = new ilCheckboxInputGUI($this->lng->txt("shuffle_answers"), "shuffle_" . $gapCounter . "");
        $shuffle->setValue(1);
        $shuffle->setChecked($gap->getShuffle());
        $shuffle->setRequired(false);
        $form->addItem($shuffle);
        return $form;
    }

    /**
     * Populates the form-part for a text gap.
     *
     * This includes: The AnswerWizardGUI for the individual text answers and points.
     *
     * @param $form			ilPropertyFormGUI	Reference to the form, that receives the point.
     * @param $gap			mixed				Raw text gap item.
     * @param $gapCounter	integer				Ordinal number of the gap in the sequence of gaps
     *
     * @return ilPropertyFormGUI
     */
    protected function populateTextGapFormPart($form, $gap, $gapCounter): ilPropertyFormGUI
    {
        $values = new ilAnswerWizardInputGUI($this->lng->txt("values"), "gap_" . $gapCounter . "");
        $values->setRequired(true);
        $values->setQuestionObject($this->object);
        $values->setSingleline(true);
        $values->setAllowMove(false);
        $values->setValues($gap->getItemsRaw());
        $form->addItem($values);

        if ($this->object->getFixedTextLength() > 0) {
            $values->setSize($this->object->getFixedTextLength());
            $values->setMaxLength($this->object->getFixedTextLength());
        }

        return $form;
    }

    /**
     * Populates the form-part for a numeric gap.
     *
     * This includes: The type selector, value, lower bound, upper bound and points.
     *
     * @param $form			ilPropertyFormGUI	Reference to the form, that receives the point.
     * @param $gap			mixed				Raw numeric gap item.
     * @param $gapCounter	integer				Ordinal number of the gap in the sequence of gaps.
     *
     * @return ilPropertyFormGUI
     */
    protected function populateNumericGapFormPart($form, $gap, $gapCounter): ilPropertyFormGUI
    {
        // #8944: the js-based ouput in self-assessment cannot support formulas
        if (!$this->object->getSelfAssessmentEditingMode()) {
            $value = new ilFormulaInputGUI($this->lng->txt('value'), "gap_" . $gapCounter . "_numeric");
            $value->setInlineStyle('text-align: right;');

            $lowerbound = new ilFormulaInputGUI($this->lng->txt('range_lower_limit'), "gap_" . $gapCounter . "_numeric_lower");
            $lowerbound->setInlineStyle('text-align: right;');

            $upperbound = new ilFormulaInputGUI($this->lng->txt('range_upper_limit'), "gap_" . $gapCounter . "_numeric_upper");
            $upperbound->setInlineStyle('text-align: right;');
        } else {
            $value = new ilNumberInputGUI($this->lng->txt('value'), "gap_" . $gapCounter . "_numeric");
            $value->allowDecimals(true);

            $lowerbound = new ilNumberInputGUI($this->lng->txt('range_lower_limit'), "gap_" . $gapCounter . "_numeric_lower");
            $lowerbound->allowDecimals(true);

            $upperbound = new ilNumberInputGUI($this->lng->txt('range_upper_limit'), "gap_" . $gapCounter . "_numeric_upper");
            $upperbound->allowDecimals(true);
        }

        $value->setSize(10);
        $value->setValue(ilLegacyFormElementsUtil::prepareFormOutput($gap->getAnswertext()));
        $value->setRequired(true);
        $form->addItem($value);

        $lowerbound->setSize(10);
        $lowerbound->setRequired(true);
        $lowerbound->setValue(ilLegacyFormElementsUtil::prepareFormOutput($gap->getLowerBound()));
        $form->addItem($lowerbound);

        $upperbound->setSize(10);
        $upperbound->setRequired(true);
        $upperbound->setValue(ilLegacyFormElementsUtil::prepareFormOutput($gap->getUpperBound()));
        $form->addItem($upperbound);

        if ($this->object->getFixedTextLength() > 0) {
            $value->setSize($this->object->getFixedTextLength());
            $value->setMaxLength($this->object->getFixedTextLength());
            $lowerbound->setSize($this->object->getFixedTextLength());
            $lowerbound->setMaxLength($this->object->getFixedTextLength());
            $upperbound->setSize($this->object->getFixedTextLength());
            $upperbound->setMaxLength($this->object->getFixedTextLength());
        }

        $points = new ilNumberInputGUI($this->lng->txt('points'), "gap_" . $gapCounter . "_numeric_points");
        $points->allowDecimals(true);
        $points->setSize(3);
        $points->setRequired(true);
        $points->setValue(ilLegacyFormElementsUtil::prepareFormOutput($gap->getPoints()));
        $form->addItem($points);
        return $form;
    }

    /**
    * Create gaps from cloze text
    */
    public function createGaps(): void
    {
        $this->writePostData(true);
        $this->object->saveToDb();
        $this->editQuestion();
    }

    /**
    * Remove a gap answer
    */
    public function removegap(): void
    {
        $this->writePostData(true);
        $this->object->deleteAnswerText($this->gapIndex, key($_POST['cmd']['removegap_' . $this->gapIndex]));
        $this->editQuestion();
    }

    /**
    * Add a gap answer
    */
    public function addgap(): void
    {
        $this->writePostData(true);
        $this->object->addGapAnswer($this->gapIndex, key($_POST['cmd']['addgap_' . $this->gapIndex]) + 1, "");
        $this->editQuestion();
    }

    /**
     * Creates a preview output of the question
     *
     * @param bool $show_question_only
     *
     * @return string HTML code which contains the preview output of the question
     * @access public
     */
    public function getPreview($show_question_only = false, $showInlineFeedback = false): string
    {
        $user_solution = is_object($this->getPreviewSession()) ? (array) $this->getPreviewSession()->getParticipantsSolution() : [];

        $template = new ilTemplate("tpl.il_as_qpl_cloze_question_output.html", true, true, "Modules/TestQuestionPool");
        $output = $this->object->getClozeTextForHTMLOutput();
        foreach ($this->object->getGaps() as $gap_index => $gap) {
            switch ($gap->getType()) {
                case CLOZE_TEXT:
                    $gaptemplate = new ilTemplate("tpl.il_as_qpl_cloze_question_gap_text.html", true, true, "Modules/TestQuestionPool");

                    $gap_size = $gap->getGapSize() > 0 ? $gap->getGapSize() : $this->object->getFixedTextLength();
                    if ($gap_size > 0) {
                        $gaptemplate->setCurrentBlock('size_and_maxlength');
                        $gaptemplate->setVariable("TEXT_GAP_SIZE", $gap_size);
                        $gaptemplate->parseCurrentBlock();
                    }
                    $gaptemplate->setVariable("GAP_COUNTER", $gap_index);
                    foreach ($user_solution as $val1 => $val2) {
                        if (strcmp($val1, $gap_index) == 0) {
                            $gaptemplate->setVariable("VALUE_GAP", " value=\"" . ilLegacyFormElementsUtil::prepareFormOutput(
                                $val2
                            ) . "\"");
                        }
                    }
                    // fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
                case CLOZE_SELECT:
                    $gaptemplate = new ilTemplate("tpl.il_as_qpl_cloze_question_gap_select.html", true, true, "Modules/TestQuestionPool");
                    foreach ($gap->getItems($this->object->getShuffler(), $gap_index) as $item) {
                        $gaptemplate->setCurrentBlock("select_gap_option");
                        $gaptemplate->setVariable("SELECT_GAP_VALUE", $item->getOrder());
                        $gaptemplate->setVariable(
                            "SELECT_GAP_TEXT",
                            ilLegacyFormElementsUtil::prepareFormOutput($item->getAnswerText())
                        );
                        foreach ($user_solution as $val1 => $val2) {
                            if (strcmp($val1, $gap_index) == 0) {
                                if (strcmp($val2, $item->getOrder()) == 0) {
                                    $gaptemplate->setVariable("SELECT_GAP_SELECTED", " selected=\"selected\"");
                                }
                            }
                        }
                        $gaptemplate->parseCurrentBlock();
                    }
                    $gaptemplate->setVariable("PLEASE_SELECT", $this->lng->txt("please_select"));
                    $gaptemplate->setVariable("GAP_COUNTER", $gap_index);// fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
                case CLOZE_NUMERIC:
                    $gaptemplate = new ilTemplate("tpl.il_as_qpl_cloze_question_gap_numeric.html", true, true, "Modules/TestQuestionPool");
                    $gap_size = $gap->getGapSize() > 0 ? $gap->getGapSize() : $this->object->getFixedTextLength();
                    if ($gap_size > 0) {
                        $gaptemplate->setCurrentBlock('size_and_maxlength');
                        $gaptemplate->setVariable("TEXT_GAP_SIZE", $gap_size);
                        $gaptemplate->parseCurrentBlock();
                    }
                    $gaptemplate->setVariable("GAP_COUNTER", $gap_index);
                    foreach ($user_solution as $val1 => $val2) {
                        if (strcmp($val1, $gap_index) == 0) {
                            $gaptemplate->setVariable("VALUE_GAP", " value=\"" . ilLegacyFormElementsUtil::prepareFormOutput(
                                $val2
                            ) . "\"");
                        }
                    }
                    // fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
            }
        }
        $template->setVariable("QUESTIONTEXT", $this->object->getQuestionForHTMLOutput());
        $template->setVariable("CLOZETEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($output, true));
        $questionoutput = $template->get();
        if (!$show_question_only) {
            // get page object output
            $questionoutput = $this->getILIASPage($questionoutput);
        }
        return $questionoutput;
    }

    /**
     * Get the question solution output
     * @param integer $active_id             The active user id
     * @param integer $pass                  The test pass
     * @param boolean $graphicalOutput       Show visual feedback for right/wrong answers
     * @param boolean $result_output         Show the reached points for parts of the question
     * @param boolean $show_question_only    Show the question without the ILIAS content around
     * @param boolean $show_feedback         Show the question feedback
     * @param boolean $show_correct_solution Show the correct solution instead of the user solution
     * @param boolean $show_manual_scoring   Show specific information for the manual scoring output
     * @param bool    $show_question_text
     * @return string The solution output of the question as HTML code
     */
    public function getSolutionOutput(
        $active_id,
        $pass = null,
        $graphicalOutput = false,
        $result_output = false,
        $show_question_only = true,
        $show_feedback = false,
        $show_correct_solution = false,
        $show_manual_scoring = false,
        $show_question_text = true
    ): string {
        // get the solution of the user for the active pass or from the last pass if allowed
        $user_solution = [];
        if (($active_id > 0) && (!$show_correct_solution)) {
            // get the solutions of a user
            $user_solution = $this->object->getSolutionValues($active_id, $pass);
            if (!is_array($user_solution)) {
                $user_solution = [];
            }
        }

        return $this->renderSolutionOutput(
            $user_solution,
            $active_id,
            $pass,
            $graphicalOutput,
            $result_output,
            $show_question_only,
            $show_feedback,
            $show_correct_solution,
            $show_manual_scoring,
            $show_question_text,
            false,
            false
        );
    }

    public function renderSolutionOutput(
        mixed $user_solutions,
        int $active_id,
        ?int $pass,
        bool $graphical_output = false,
        bool $result_output = false,
        bool $show_question_only = true,
        bool $show_feedback = false,
        bool $show_correct_solution = false,
        bool $show_manual_scoring = false,
        bool $show_question_text = true,
        bool $show_autosave_title = false,
        bool $show_inline_feedback = false,
    ): ?string {

        $template = new ilTemplate("tpl.il_as_qpl_cloze_question_output_solution.html", true, true, "Modules/TestQuestionPool");
        $output = $this->object->getClozeTextForHTMLOutput();
        $assClozeGapCombinationObject = new assClozeGapCombination();
        $check_for_gap_combinations = $assClozeGapCombinationObject->loadFromDb($this->object->getId());

        foreach ($this->object->getGaps() as $gap_index => $gap) {
            $gaptemplate = new ilTemplate("tpl.il_as_qpl_cloze_question_output_solution_gap.html", true, true, "Modules/TestQuestionPool");
            $found = [];
            foreach ($user_solutions as $solutionarray) {
                if ($solutionarray['value1'] == $gap_index) {
                    $found = $solutionarray;
                }
            }

            if ($active_id) {
                if ($graphical_output) {
                    // output of ok/not ok icons for user entered solutions
                    $details = $this->object->calculateReachedPoints($active_id, $pass, true, true);
                    $check = $details[$gap_index] ?? [];

                    if (count($check_for_gap_combinations) != 0) {
                        $gaps_used_in_combination = $assClozeGapCombinationObject->getGapsWhichAreUsedInCombination($this->object->getId());
                        $custom_user_solution = [];
                        if (array_key_exists($gap_index, $gaps_used_in_combination)) {
                            $combination_id = $gaps_used_in_combination[$gap_index];
                            foreach ($gaps_used_in_combination as $key => $value) {
                                $a = 0;
                                if ($value == $combination_id) {
                                    foreach ($user_solutions as $solution_key => $solution_value) {
                                        if ($solution_value['value1'] == $key) {
                                            $result_row = [];
                                            $result_row['gap_id'] = $solution_value['value1'];
                                            $result_row['value'] = $solution_value['value2'];
                                            array_push($custom_user_solution, $result_row);
                                        }
                                    }
                                }
                            }
                            $points_array = $this->object->calculateCombinationResult($custom_user_solution);
                            $max_combination_points = $assClozeGapCombinationObject->getMaxPointsForCombination($this->object->getId(), $combination_id);
                            if ($points_array[0] == $max_combination_points) {
                                $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_OK));
                            } elseif ($points_array[0] > 0) {
                                $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_MOSTLY_OK));
                            } else {
                                $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_NOT_OK));
                            }
                        } else {
                            if (array_key_exists('best', $check) && $check["best"]) {
                                $gaptemplate->setCurrentBlock("icon_ok");
                                $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_OK));
                                $gaptemplate->parseCurrentBlock();
                            } else {
                                $gaptemplate->setCurrentBlock("icon_ok");
                                if (array_key_exists('positive', $check) && $check["positive"]) {
                                    $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_MOSTLY_OK));
                                } else {
                                    $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_NOT_OK));
                                }
                                $gaptemplate->parseCurrentBlock();
                            }
                        }
                    } else {
                        if (array_key_exists('best', $check) && $check["best"]) {
                            $gaptemplate->setCurrentBlock("icon_ok");
                            $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_OK));
                            $gaptemplate->parseCurrentBlock();
                        } else {
                            $gaptemplate->setCurrentBlock("icon_ok");
                            if (array_key_exists('positive', $check) && $check["positive"]) {
                                $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_MOSTLY_OK));
                            } else {
                                $gaptemplate->setVariable("ICON_OK", $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_NOT_OK));
                            }
                            $gaptemplate->parseCurrentBlock();
                        }
                    }
                }
            }
            $combination = null;
            switch ($gap->getType()) {
                case CLOZE_NUMERIC:
                case CLOZE_TEXT:
                    $solutiontext = "";
                    if (($active_id > 0) && (!$show_correct_solution)) {
                        if ((count($found) == 0) || (strlen(trim($found['value2'])) == 0)) {
                            for ($chars = 0; $chars < $gap->getMaxWidth(); $chars++) {
                                $solutiontext .= "&nbsp;";
                            }
                        } else {
                            $solutiontext = ilLegacyFormElementsUtil::prepareFormOutput($found['value2']);
                        }
                    } else {
                        $solutiontext = $this-> getBestSolutionText($gap, $gap_index, $check_for_gap_combinations);
                    }
                    $this->populateSolutiontextToGapTpl($gaptemplate, $gap, $solutiontext);
                    // fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
                case CLOZE_SELECT:
                    $solutiontext = "";
                    if (($active_id > 0) && (!$show_correct_solution)) {
                        if ((count($found) == 0) || (strlen(trim($found['value2'])) == 0)) {
                            for ($chars = 0; $chars < $gap->getMaxWidth(); $chars++) {
                                $solutiontext .= "&nbsp;";
                            }
                        } else {
                            $item = $gap->getItem($found['value2']);
                            if (is_object($item)) {
                                $solutiontext = ilLegacyFormElementsUtil::prepareFormOutput($item->getAnswertext());
                            } else {
                                for ($chars = 0; $chars < $gap->getMaxWidth(); $chars++) {
                                    $solutiontext .= "&nbsp;";
                                }
                            }
                        }
                    } else {
                        $solutiontext = $this-> getBestSolutionText($gap, $gap_index, $check_for_gap_combinations);
                    }
                    $this->populateSolutiontextToGapTpl($gaptemplate, $gap, $solutiontext);
                    // fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
            }
        }

        if ($show_question_text) {
            $template->setVariable(
                "QUESTIONTEXT",
                $this->object->getQuestionForHTMLOutput()
            );
        }

        $template->setVariable("CLOZETEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($output, true));
        // generate the question output
        $solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", true, true, "Modules/TestQuestionPool");
        $questionoutput = $template->get();

        $feedback = '';
        if ($show_feedback) {
            if (!$this->isTestPresentationContext()) {
                $fb = $this->getGenericFeedbackOutput((int) $active_id, $pass);
                $feedback .= strlen($fb) ? $fb : '';
            }

            $fb = $this->getSpecificFeedbackOutput(
                $this->object->fetchIndexedValuesFromValuePairs($user_solutions)
            );
            $feedback .= strlen($fb) ? $fb : '';
        }
        if (strlen($feedback)) {
            $cssClass = (
                $this->hasCorrectSolution($active_id, $pass) ?
                ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_CORRECT : ilAssQuestionFeedback::CSS_CLASS_FEEDBACK_WRONG
            );

            $solutiontemplate->setVariable("ILC_FB_CSS_CLASS", $cssClass);
            $solutiontemplate->setVariable("FEEDBACK", ilLegacyFormElementsUtil::prepareTextareaOutput($feedback, true));
        }

        $solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);

        $solutionoutput = $solutiontemplate->get();

        if (!$show_question_only) {
            // get page object output
            $solutionoutput = $this->getILIASPage($solutionoutput);
        }

        return $solutionoutput;
    }

    /**
     * @param assClozeGap $gap
     * @param $gap_index
     * @param $gap_combinations
     * @return string
     */
    protected function getBestSolutionText($gap, $gap_index, $gap_combinations): string
    {
        $combination = null;
        foreach ((array) $gap_combinations as $combiGapSolRow) {
            if ($combiGapSolRow['gap_fi'] == $gap_index && $combiGapSolRow['best_solution']) {
                $combination = $combiGapSolRow;
                break;
            }
        }
        $best_solution_text = ilLegacyFormElementsUtil::prepareFormOutput(
            $gap->getBestSolutionOutput(
                $this->object->getShuffler(),
                $combination
            )
        );
        return $best_solution_text;
    }

    public function getGenericFeedbackOutput(int $active_id, $pass): string
    {
        $manual_feedback = ilObjTest::getManualFeedback($active_id, $this->object->getId(), $pass);
        if (strlen($manual_feedback)) {
            return $manual_feedback;
        }
        $correct_feedback = $this->object->feedbackOBJ->getGenericFeedbackTestPresentation($this->object->getId(), true);
        $incorrect_feedback = $this->object->feedbackOBJ->getGenericFeedbackTestPresentation($this->object->getId(), false);

        $output = '';
        if ($correct_feedback . $incorrect_feedback !== '') {
            $output = $this->genericFeedbackOutputBuilder($correct_feedback, $incorrect_feedback, $active_id, $pass);
        }
        //$test = new ilObjTest($this->object->active_id);
        return ilLegacyFormElementsUtil::prepareTextareaOutput($output, true);
    }

    public function getTestOutput(
        $active_id,
        // hey: prevPassSolutions - will be always available from now on
        $pass,
        // hey.
        $is_postponed = false,
        $use_post_solutions = false,
        $show_feedback = false
    ): string {
        // get the solution of the user for the active pass or from the last pass if allowed
        $user_solution = [];
        if ($use_post_solutions !== false) {
            $indexedSolution = $this->object->fetchSolutionSubmit($use_post_solutions);
            $user_solution = $this->object->fetchValuePairsFromIndexedValues($indexedSolution);
        } elseif ($active_id) {
            $user_solution = $this->object->getTestOutputSolutions($active_id, $pass);
            // hey.
            if (!is_array($user_solution)) {
                $user_solution = [];
            }
        }

        $template = new ilTemplate("tpl.il_as_qpl_cloze_question_output.html", true, true, "Modules/TestQuestionPool");
        $output = $this->object->getClozeTextForHTMLOutput();
        foreach ($this->object->getGaps() as $gap_index => $gap) {
            switch ($gap->getType()) {
                case CLOZE_TEXT:
                    $gaptemplate = new ilTemplate("tpl.il_as_qpl_cloze_question_gap_text.html", true, true, "Modules/TestQuestionPool");
                    $gap_size = $gap->getGapSize() > 0 ? $gap->getGapSize() : $this->object->getFixedTextLength();

                    if ($gap_size > 0) {
                        $gaptemplate->setCurrentBlock('size_and_maxlength');
                        $gaptemplate->setVariable("TEXT_GAP_SIZE", $gap_size);
                        $gaptemplate->parseCurrentBlock();
                    }

                    $gaptemplate->setVariable("GAP_COUNTER", $gap_index);
                    foreach ($user_solution as $solution) {
                        if (strcmp($solution['value1'], $gap_index) == 0) {
                            $gaptemplate->setVariable("VALUE_GAP", " value=\"" . ilLegacyFormElementsUtil::prepareFormOutput(
                                $solution['value2']
                            ) . "\"");
                        }
                    }
                    // fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
                case CLOZE_SELECT:
                    $gaptemplate = new ilTemplate("tpl.il_as_qpl_cloze_question_gap_select.html", true, true, "Modules/TestQuestionPool");
                    foreach ($gap->getItems($this->object->getShuffler(), $gap_index) as $item) {
                        $gaptemplate->setCurrentBlock("select_gap_option");
                        $gaptemplate->setVariable("SELECT_GAP_VALUE", $item->getOrder());
                        $gaptemplate->setVariable(
                            "SELECT_GAP_TEXT",
                            ilLegacyFormElementsUtil::prepareFormOutput($item->getAnswerText())
                        );
                        foreach ($user_solution as $solution) {
                            if (strcmp($solution['value1'], $gap_index) == 0) {
                                if (strcmp($solution['value2'], $item->getOrder()) == 0) {
                                    $gaptemplate->setVariable("SELECT_GAP_SELECTED", " selected=\"selected\"");
                                }
                            }
                        }
                        $gaptemplate->parseCurrentBlock();
                    }
                    $gaptemplate->setVariable("PLEASE_SELECT", $this->lng->txt("please_select"));
                    $gaptemplate->setVariable("GAP_COUNTER", $gap_index);// fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
                case CLOZE_NUMERIC:
                    $gaptemplate = new ilTemplate("tpl.il_as_qpl_cloze_question_gap_numeric.html", true, true, "Modules/TestQuestionPool");
                    $gap_size = $gap->getGapSize() > 0 ? $gap->getGapSize() : $this->object->getFixedTextLength();
                    if ($gap_size > 0) {
                        $gaptemplate->setCurrentBlock('size_and_maxlength');
                        $gaptemplate->setVariable("TEXT_GAP_SIZE", $gap_size);
                        $gaptemplate->parseCurrentBlock();
                    }

                    $gaptemplate->setVariable("GAP_COUNTER", $gap_index);
                    foreach ($user_solution as $solution) {
                        if (strcmp($solution['value1'], $gap_index) == 0) {
                            $gaptemplate->setVariable("VALUE_GAP", " value=\"" . ilLegacyFormElementsUtil::prepareFormOutput(
                                $solution['value2']
                            ) . "\"");
                        }
                    }
                    // fau: fixGapReplace - use replace function
                    $output = $this->object->replaceFirstGap($output, $gaptemplate->get());
                    // fau.
                    break;
            }
        }

        $template->setVariable("QUESTIONTEXT", $this->object->getQuestionForHTMLOutput());
        $template->setVariable("CLOZETEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($output, true));
        $questionoutput = $template->get();
        $pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
        return $pageoutput;
    }

    public function getSpecificFeedbackOutput(array $user_solution): string
    {
        if (!$this->object->feedbackOBJ->isSpecificAnswerFeedbackAvailable($this->object->getId())) {
            return '';
        }

        $feedback = '<table class="test_specific_feedback"><tbody>';

        foreach ($this->object->gaps as $gap_index => $gap) {
            $answer_value = $this->object->fetchAnswerValueForGap($user_solution, $gap_index);
            if ($gap->getType() !== assClozeGap::TYPE_TEXT
                && $answer_value === '') {
                continue;
            }
            $answer_index = $this->object->feedbackOBJ->determineAnswerIndexForAnswerValue($gap, $answer_value);
            $fb = $this->object->feedbackOBJ->determineTestOutputGapFeedback($gap_index, $answer_index);

            $caption = $this->lng->txt('gap') . ' ' . ($gap_index + 1) . ': ';
            $feedback .= '<tr><td>';
            $feedback .= $caption . '</td><td>';
            $feedback .= $fb . '</td> </tr>';
        }
        $feedback .= '</tbody></table>';

        return ilLegacyFormElementsUtil::prepareTextareaOutput($feedback, true);
    }

    /**
     * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
     * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
     * make sense in the given context.
     *
     * E.g. array('cloze_type', 'image_filename')
     *
     * @return string[]
     */
    public function getAfterParticipationSuppressionAnswerPostVars(): array
    {
        return [];
    }

    /**
     * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
     * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
     * make sense in the given context.
     *
     * E.g. array('cloze_type', 'image_filename')
     *
     * @return string[]
     */
    public function getAfterParticipationSuppressionQuestionPostVars(): array
    {
        return [];
    }

    /**
     * Returns an html string containing a question specific representation of the answers so far
     * given in the test for use in the right column in the scoring adjustment user interface.
     * @param array $relevant_answers
     * @return string
     */
    public function getAggregatedAnswersView(array $relevant_answers): string
    {
        $overview = [];
        $aggregation = [];
        foreach ($relevant_answers as $answer) {
            $overview[$answer['active_fi']][$answer['pass']][$answer['value1']] = $answer['value2'];
        }

        foreach ($overview as $active) {
            foreach ($active as $answer) {
                foreach ($answer as $option => $value) {
                    $aggregation[$option][$value] = $aggregation[$option][$value] + 1;
                }
            }
        }

        $html = '<div>';
        $i = 0;
        foreach ($this->object->getGaps() as $gap_index => $gap) {
            if ($gap->type == CLOZE_SELECT) {
                $html .= '<p>Gap ' . ($i + 1) . ' - SELECT</p>';
                $html .= '<ul>';
                $j = 0;
                foreach ($gap->getItems($this->object->getShuffler(), $gap_index) as $gap_item) {
                    $aggregate = $aggregation[$i];
                    $html .= '<li>' . $gap_item->getAnswerText() . ' - ' . ($aggregate[$j] ? $aggregate[$j] : 0) . '</li>';
                    $j++;
                }
                $html .= '</ul>';
            }

            if ($gap->type == CLOZE_TEXT) {
                $present_elements = [];
                foreach ($gap->getItems($this->randomGroup->shuffleArray(new Seed\RandomSeed())) as $item) {
                    /** @var assAnswerCloze $item */
                    $present_elements[] = $item->getAnswertext();
                }

                $html .= '<p>Gap ' . ($i + 1) . ' - TEXT</p>';
                $html .= '<ul>';
                $aggregate = (array) $aggregation[$i];
                foreach ($aggregate as $answer => $count) {
                    $show_mover = '';
                    if (in_array($answer, $present_elements)) {
                        $show_mover = ' style="display: none;" ';
                    }

                    $html .= '<li>' . $answer . ' - ' . $count
                        . '&nbsp;<button class="clone_fields_add btn btn-link" ' . $show_mover . ' data-answer="' . $answer . '" name="add_gap_' . $i . '_0">
						<span class="sr-only"></span><span class="glyphicon glyphicon-plus"></span></button>
						</li>';
                }
                $html .= '</ul>';
            }

            if ($gap->type == CLOZE_NUMERIC) {
                $html .= '<p>Gap ' . ($i + 1) . ' - NUMERIC</p>';
                $html .= '<ul>';
                $j = 0;
                foreach ($gap->getItems($this->object->getShuffler(), $gap_index) as $gap_item) {
                    $aggregate = (array) $aggregation[$i];
                    foreach ($aggregate as $answer => $count) {
                        $html .= '<li>' . $answer . ' - ' . $count . '</li>';
                    }
                    $j++;
                }
                $html .= '</ul>';
            }
            $i++;
            $html .= '<hr />';
        }

        $html .= '</div>';
        return $html;
    }

    public function applyIndizesToGapText($question_text): string
    {
        $parts = explode('[gap', $question_text);
        $i = 0;
        $question_text = '';
        foreach ($parts as $part) {
            if ($i == 0) {
                $question_text .= $part;
            } else {
                $question_text .= '[gap ' . $i . $part;
            }
            $i++;
        }
        return $question_text;
    }

    public function removeIndizesFromGapText($question_text): string
    {
        $parts = preg_split('/\[gap \d*\]/', $question_text);
        $question_text = implode('[gap]', $parts);
        return $question_text;
    }

    /**
     * @param $gaptemplate
     * @param $solutiontext
     */
    private function populateSolutiontextToGapTpl($gaptemplate, $gap, $solutiontext): void
    {
        if ($this->isRenderPurposePrintPdf()) {
            $gaptemplate->setCurrentBlock('gap_span');
            $gaptemplate->setVariable('SPAN_SOLUTION', $solutiontext);
        } elseif ($gap->getType() == CLOZE_SELECT) {
            $gaptemplate->setCurrentBlock('gap_select');
            $gaptemplate->setVariable('SELECT_SOLUTION', $solutiontext);
        } else {
            $gap_size = $gap->getGapSize() > 0 ? $gap->getGapSize() : $this->object->getFixedTextLength();

            if ($gap_size > 0) {
                $gaptemplate->setCurrentBlock('gap_size');
                $gaptemplate->setVariable("GAP_SIZE", $gap_size);
                $gaptemplate->parseCurrentBlock();
            }

            $gaptemplate->setCurrentBlock('gap_input');
            $gaptemplate->setVariable('INPUT_SOLUTION', $solutiontext);
        }


        $gaptemplate->parseCurrentBlock();
    }

    protected function hasAddAnswerAction($relevantAnswers, $questionIndex): bool
    {
        foreach ($this->getAnswersFrequency($relevantAnswers, $questionIndex) as $answer) {
            if (isset($answer['actions'])) {
                return true;
            }
        }

        return false;
    }

    public function getAnswerFrequencyTableGUI($parentGui, $parentCmd, $relevantAnswers, $questionIndex): ilAnswerFrequencyStatisticTableGUI
    {
        $table = parent::getAnswerFrequencyTableGUI(
            $parentGui,
            $parentCmd,
            $relevantAnswers,
            $questionIndex
        );

        $table->setTitle(
            sprintf(
                $this->lng->txt('tst_corrections_answers_tbl_subindex'),
                $this->lng->txt('gap') . ' ' . ($questionIndex + 1)
            )
        );

        if ($this->hasAddAnswerAction($relevantAnswers, $questionIndex)) {
            $table->addColumn('', '', '200');
        }

        return $table;
    }

    public function getSubQuestionsIndex(): array
    {
        return array_keys($this->object->getGaps());
    }

    protected function getAnswerTextLabel($gapIndex, $answer)
    {
        $gap = $this->object->getGap($gapIndex);

        switch ($gap->type) {
            case CLOZE_NUMERIC:
            case CLOZE_TEXT:
                return $answer;

            case CLOZE_SELECT:
            default:
                $items = $gap->getItems($this->randomGroup->dontShuffle());
                return $items[$answer]->getAnswertext();
        }
    }

    protected function completeAddAnswerAction($answers, $gap_index): array
    {
        $gap = $this->object->getGap($gap_index);

        if ($gap->type != CLOZE_TEXT ||
            $this->isUsedInCombinations($gap_index)) {
            return $answers;
        }

        foreach ($answers as $key => $ans) {
            $found = false;

            foreach ($gap->getItems($this->randomGroup->dontShuffle()) as $item) {
                if ($ans['answer'] !== $item->getAnswerText()) {
                    continue;
                }

                $found = true;
                break;
            }

            if (!$found) {
                $answers[$key]['addable'] = true;
            }
        }

        return $answers;
    }

    public function getAnswersFrequency($relevantAnswers, $questionIndex): array
    {
        $answers = [];

        foreach ($relevantAnswers as $row) {
            if ($row['value1'] != $questionIndex) {
                continue;
            }

            if (!isset($answers[$row['value2']])) {
                $label = $this->getAnswerTextLabel($row['value1'], $row['value2']);

                $answers[$row['value2']] = [
                    'answer' => $label, 'frequency' => 0
                ];
            }

            $answers[$row['value2']]['frequency']++;
        }

        $answers = $this->completeAddAnswerAction($answers, $questionIndex);

        return $answers;
    }

    protected function isUsedInCombinations($gapIndex): bool
    {
        foreach ($this->object->getGapCombinations() as $combination) {
            if ($combination['gap_fi'] != $gapIndex) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function getGapCombinations(): array
    {
        $combinations = [];

        foreach ($this->object->getGapCombinations() as $c) {
            if (!isset($combinations[$c['cid']])) {
                $combinations[$c['cid']] = [];
            }

            if (!isset($combinations[$c['cid']][$c['row_id']])) {
                $combinations[$c['cid']][$c['row_id']] = [
                    'gaps' => [], 'points' => $c['points'],
                ];
            }

            if (!isset($combinations[$c['cid']][$c['row_id']]['gaps'][$c['gap_fi']])) {
                $combinations[$c['cid']][$c['row_id']]['gaps'][$c['gap_fi']] = [];
            }

            $combinations[$c['cid']][$c['row_id']]['gaps'][$c['gap_fi']] = $c['answer'];
        }

        return $combinations;
    }

    public function populateCorrectionsFormProperties(ilPropertyFormGUI $form): void
    {
        foreach ($this->object->getGaps() as $gapIndex => $gap) {
            $this->populateGapCorrectionFormProperties(
                $form,
                $gap,
                $gapIndex,
                $this->isUsedInCombinations($gapIndex)
            );
        }

        if ($this->object->getGapCombinationsExists()) {
            foreach ($this->getGapCombinations() as $combiIndex => $gapCombination) {
                $this->populateGapCombinationCorrectionFormProperty($form, $gapCombination, $combiIndex);
            }
        }
    }

    protected function populateGapCombinationCorrectionFormProperty(ilPropertyFormGUI $form, $gapCombi, $combiIndex): void
    {
        $header = new ilFormSectionHeaderGUI();
        $header->setTitle("Gap Combination " . ($combiIndex + 1));
        $form->addItem($header);

        $inp = new ilAssClozeTestCombinationVariantsInputGUI('Answers', 'combination_' . $combiIndex);
        $inp->setValues($gapCombi);
        $form->addItem($inp);
    }

    /**
     * @param ilPropertyFormGUI $form
     * @param assClozeGap $gap
     * @param integer $gapIndex
     */
    protected function populateGapCorrectionFormProperties($form, $gap, $gapIndex, $hidePoints): void
    {
        $header = new ilFormSectionHeaderGUI();
        $header->setTitle($this->lng->txt("gap") . " " . ($gapIndex + 1));
        $form->addItem($header);

        if ($gap->getType() == CLOZE_TEXT || $gap->getType() == CLOZE_SELECT) {
            $this->populateTextOrSelectGapCorrectionFormProperty($form, $gap, $gapIndex, $hidePoints);
        } elseif ($gap->getType() == CLOZE_NUMERIC) {
            foreach ($gap->getItemsRaw() as $item) {
                $this->populateNumericGapCorrectionFormProperty($form, $item, $gapIndex, $hidePoints);
            }
        }
    }

    protected function populateTextOrSelectGapCorrectionFormProperty($form, $gap, $gapIndex, $hidePoints): void
    {
        $values = new ilAssAnswerCorrectionsInputGUI($this->lng->txt("values"), "gap_" . $gapIndex);
        $values->setHidePointsEnabled($hidePoints);
        $values->setRequired(true);
        $values->setQuestionObject($this->object);
        $values->setValues($gap->getItemsRaw());
        $form->addItem($values);
    }

    protected function populateNumericGapCorrectionFormProperty($form, $item, $gapIndex, $hidePoints): void
    {
        $value = new ilNumberInputGUI($this->lng->txt('value'), "gap_" . $gapIndex . "_numeric");
        $value->allowDecimals(true);
        $value->setSize(10);
        $value->setValue(ilLegacyFormElementsUtil::prepareFormOutput($item->getAnswertext()));
        $value->setRequired(true);
        $form->addItem($value);

        $lowerbound = new ilNumberInputGUI($this->lng->txt('range_lower_limit'), "gap_" . $gapIndex . "_numeric_lower");
        $lowerbound->allowDecimals(true);
        $lowerbound->setSize(10);
        $lowerbound->setRequired(true);
        $lowerbound->setValue(ilLegacyFormElementsUtil::prepareFormOutput($item->getLowerBound()));
        $form->addItem($lowerbound);

        $upperbound = new ilNumberInputGUI($this->lng->txt('range_upper_limit'), "gap_" . $gapIndex . "_numeric_upper");
        $upperbound->allowDecimals(true);
        $upperbound->setSize(10);
        $upperbound->setRequired(true);
        $upperbound->setValue(ilLegacyFormElementsUtil::prepareFormOutput($item->getUpperBound()));
        $form->addItem($upperbound);

        if (!$hidePoints) {
            $points = new ilNumberInputGUI($this->lng->txt('points'), "gap_" . $gapIndex . "_numeric_points");
            $points->allowDecimals(true);
            $points->setSize(3);
            $points->setRequired(true);
            $points->setValue(ilLegacyFormElementsUtil::prepareFormOutput($item->getPoints()));
            $form->addItem($points);
        }
    }

    /**
     * @param ilPropertyFormGUI $form
     */
    public function saveCorrectionsFormProperties(ilPropertyFormGUI $form): void
    {
        foreach ($this->object->getGaps() as $gapIndex => $gap) {
            if ($this->isUsedInCombinations($gapIndex)) {
                continue;
            }

            $this->saveGapCorrectionFormProperty($form, $gap, $gapIndex);
        }

        if ($this->object->getGapCombinationsExists()) {
            $this->saveGapCombinationCorrectionFormProperties($form);
        }
    }

    protected function saveGapCorrectionFormProperty(ilPropertyFormGUI $form, assClozeGap $gap, $gapIndex): void
    {
        if ($gap->getType() == CLOZE_TEXT || $gap->getType() == CLOZE_SELECT) {
            $this->saveTextOrSelectGapCorrectionFormProperty($form, $gap, $gapIndex);
        } elseif ($gap->getType() == CLOZE_NUMERIC) {
            foreach ($gap->getItemsRaw() as $item) {
                $this->saveNumericGapCorrectionFormProperty($form, $item, $gapIndex);
            }
        }
    }

    protected function saveTextOrSelectGapCorrectionFormProperty(ilPropertyFormGUI $form, assClozeGap $gap, $gapIndex): void
    {
        $answers = $form->getItemByPostVar('gap_' . $gapIndex)->getValues();

        foreach ($gap->getItemsRaw() as $index => $item) {
            $item->setPoints((float) str_replace(',', '.', $answers[$index]->getPoints()));
        }
    }

    protected function saveNumericGapCorrectionFormProperty(ilPropertyFormGUI $form, assAnswerCloze $item, $gapIndex): void
    {
        $item->setAnswertext($form->getInput('gap_' . $gapIndex . '_numeric'));
        $item->setLowerBound($form->getInput('gap_' . $gapIndex . '_numeric_lower'));
        $item->setUpperBound($form->getInput('gap_' . $gapIndex . '_numeric_upper'));
        $item->setPoints((float) str_replace(',', '.', $form->getInput('gap_' . $gapIndex . '_numeric_points')));
    }

    protected function saveGapCombinationCorrectionFormProperties(ilPropertyFormGUI $form): void
    {
        // please dont ask (!) -.-

        $combinationPoints = ['points' => [], 'select' => []];
        $combinationValues = [];

        foreach ($this->getGapCombinations() as $combiId => $combi) {
            $values = $form->getItemByPostVar('combination_' . $combiId)->getValues();

            if (!isset($combinationPoints['points'][$combiId])) {
                $combinationPoints['points'][$combiId] = [];
                $combinationPoints['select'][$combiId] = [];
                $combinationValues[$combiId] = [];
            }

            foreach ($combi as $varId => $variant) {
                $combinationPoints['points'][$combiId][$varId] = (float) str_replace(',', '.', $values[$varId]['points']);
                $combinationPoints['select'][$combiId] = array_keys($values[$varId]['gaps']);
                $combinationValues[$combiId][$varId] = array_values($values[$varId]['gaps']);
            }
        }

        $combinationPoints = $combinationPoints;
        $combinationValues = $combinationValues;

        $assClozeGapCombinationObject = new assClozeGapCombination();
        $assClozeGapCombinationObject->clearGapCombinationsFromDb($this->object->getId());

        $assClozeGapCombinationObject->saveGapCombinationToDb(
            $this->object->getId(),
            $combinationPoints,
            $combinationValues
        );
    }
}
