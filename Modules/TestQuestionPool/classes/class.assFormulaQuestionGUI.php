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

require_once './Modules/Test/classes/inc.AssessmentConstants.php';

/**
 * Single choice question GUI representation
 * The assFormulaQuestionGUI class encapsulates the GUI representation
 * for single choice questions.
 * @author            Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @version           $Id: class.assFormulaQuestionGUI.php 1235 2010-02-15 15:21:18Z hschottm $
 * @ingroup           ModulesTestQuestionPool
 * @ilCtrl_Calls assFormulaQuestionGUI: ilFormPropertyDispatchGUI
 */
class assFormulaQuestionGUI extends assQuestionGUI
{
    protected const HAS_SPECIAL_QUESTION_COMMANDS = true;

    /**
     * assFormulaQuestionGUI constructor
     * The constructor takes possible arguments an creates an instance of the assFormulaQuestionGUI object.
     * @param integer $id The database id of a multiple choice question object
     * @access public
     */
    public function __construct($id = -1)
    {
        parent::__construct();
        $this->object = new assFormulaQuestion();
        if ($id >= 0) {
            $this->object->loadFromDb($id);
        }
    }

    protected function setQuestionSpecificTabs(ilTabsGUI $ilTabs): void
    {
        $ilTabs->addTarget('units', $this->ctrl->getLinkTargetByClass('ilLocalUnitConfigurationGUI', ''), '', 'illocalunitconfigurationgui');
    }

    protected function callSpecialQuestionCommands(string $cmd): void
    {
        if (preg_match('/suggestrange_(\$r\d+)/', $cmd, $matches)) {
            $this->addSaveOnEnterOnLoadCode();
            $this->suggestRange($matches[1]);
        }
    }

    public function suggestRange(string $suggest_range_for_result): void
    {
        if ($this->writePostData()) {
            $this->tpl->setOnScreenMessage('info', $this->getErrorMessage());
        }
        $this->editQuestion(false, $suggest_range_for_result);
    }

    /**
     * {@inheritdoc}
     */
    protected function writePostData(bool $always = false): int
    {
        $hasErrors = (!$always) ? $this->editQuestion(true) : false;
        $checked = true;
        if (!$hasErrors) {
            $this->object->setTitle($this->request->string('title'));
            $this->object->setAuthor($this->request->string('author'));
            $this->object->setComment($this->request->string('comment'));
            $this->object->setQuestion($this->request->string('question'));

            $this->object->parseQuestionText();
            $found_vars = [];
            $found_results = [];

            foreach ($this->request->getParsedBody() as $key => $value) {
                if (preg_match("/^unit_(\\\$v\d+)$/", $key, $matches)) {
                    array_push($found_vars, $matches[1]);
                }
                if (preg_match("/^unit_(\\\$r\d+)$/", $key, $matches)) {
                    array_push($found_results, $matches[1]);
                }
            }

            try {
                $lifecycle = ilAssQuestionLifecycle::getInstance($_POST['lifecycle']);
                $this->object->setLifecycle($lifecycle);
            } catch (ilTestQuestionPoolInvalidArgumentException $e) {
            }

            if (!$this->object->checkForDuplicateResults()) {
                $this->addErrorMessage($this->lng->txt("err_duplicate_results"));
                $checked = false;
            }

            foreach ($found_vars as $variable) {
                if ($this->object->getVariable($variable) != null) {
                    $varObj = new assFormulaQuestionVariable(
                        $variable,
                        str_replace(',', '.', $_POST["range_min_{$variable}"]),
                        str_replace(',', '.', $_POST["range_max_{$variable}"]),
                        isset($_POST["unit_{$variable}"]) ? $this->object->getUnitrepository()->getUnit(
                            $_POST["unit_{$variable}"]
                        ) : null,
                        (int) $this->request->float("precision_{$variable}"),
                        (int) $this->request->float("intprecision_{$variable}")
                    );
                    $this->object->addVariable($varObj);
                }
            }

            $tmp_form_vars = [];
            $tmp_quest_vars = [];
            foreach ($found_results as $result) {
                $tmp_res_match = preg_match_all('/([$][v][0-9]*)/', $_POST["formula_{$result}"], $form_vars);
                $tmp_form_vars = array_merge($tmp_form_vars, $form_vars[0]);

                $tmp_que_match = preg_match_all('/([$][v][0-9]*)/', $_POST['question'], $quest_vars);
                $tmp_quest_vars = array_merge($tmp_quest_vars, $quest_vars[0]);
            }
            $result_has_undefined_vars = array_diff($tmp_form_vars, $found_vars);
            $question_has_unused_vars = array_diff($tmp_quest_vars, $tmp_form_vars);

            if (count($result_has_undefined_vars) > 0 || count($question_has_unused_vars) > 0) {
                $error_message = '';
                if (count($result_has_undefined_vars) > 0) {
                    $error_message .= $this->lng->txt("res_contains_undef_var") . '<br>';
                }
                if (count($question_has_unused_vars) > 0) {
                    $error_message .= $this->lng->txt("que_contains_unused_var");
                }
                $checked = false;
                if ($this->isSaveCommand()) {
                    $this->tpl->setOnScreenMessage('failure', $error_message);
                }
            }
            foreach ($found_results as $result) {
                if ($this->object->getResult($result) != null) {
                    $resObj = new assFormulaQuestionResult(
                        $result,
                        str_replace(',', '.', $_POST["range_min_{$result}"]),
                        str_replace(',', '.', $_POST["range_max_{$result}"]),
                        $this->request->float("tolerance_{$result}"),
                        isset($_POST["unit_{$result}"]) ? $this->object->getUnitrepository()->getUnit(
                            $_POST["unit_{$result}"]
                        ) : null,
                        $this->request->string("formula_{$result}"),
                        $this->request->float("points_{$result}"),
                        (int) $this->request->float("precision_{$result}"),
                        $this->request->int("rating_advanced_{$result}") !== 1,
                        $this->request->int("rating_advanced_{$result}") === 1 ? $this->request->float("rating_sign_{$result}") : null,
                        $this->request->int("rating_advanced_{$result}") === 1 ? $this->request->float("rating_value_{$result}") : null,
                        $this->request->int("rating_advanced_{$result}") === 1 ? $this->request->float("rating_unit_{$result}") : null,
                        $this->request->int("result_type_{$result}")
                    );
                    $this->object->addResult($resObj);
                    if (isset($_POST["units_$result"]) && is_array($_POST["units_{$result}"])) {
                        $this->object->addResultUnits($resObj, $_POST["units_{$result}"]);
                    }
                }
            }
            if ($checked == false) {
                $this->editQuestion();
                return 1;
            } else {
                $this->resetSavedPreviewSession();
                return 0;
            }
        } else {
            return 1;
        }
    }

    public function resetSavedPreviewSession(): void
    {
        global $DIC;
        $ilUser = $DIC['ilUser'];
        $user_id = $ilUser->getId();
        $question_id = $this->object->getId();
        $ilAssQuestionPreviewSession = new ilAssQuestionPreviewSession($user_id, $question_id);
        $ilAssQuestionPreviewSession->setParticipantsSolution([]);
    }

    public function isSaveCommand(): bool
    {
        return in_array($this->ctrl->getCmd(), array('save', 'saveEdit', 'saveReturn'));
    }

    /**
     * @param bool $checkonly
     */
    public function editQuestion($checkonly = false, string $suggest_range_for_result = ''): bool
    {
        $save = $this->isSaveCommand();

        $this->getQuestionTemplate();

        $form = new ilPropertyFormGUI();
        $this->editForm = $form;

        $form->setFormAction($this->ctrl->getFormAction($this));
        $form->setTitle($this->outQuestionType());
        $form->setMultipart(false);
        $form->setTableWidth('100%');
        $form->setId('assformulaquestion');

        $this->addBasicQuestionFormProperties($form);

        // Add info text
        $question = $form->getItemByPostVar('question');
        $question->setInfo($this->lng->txt('fq_question_desc'));

        $variables = $this->object->getVariables();
        $categorized_units = $this->object->getUnitrepository()->getCategorizedUnits();
        $result_units = $this->object->getAllResultUnits();

        $unit_options = [];
        $category_name = '';
        $new_category = false;
        foreach ($categorized_units as $item) {
            /**
             * @var $item assFormulaQuestionUnitCategory|assFormulaQuestionUnit
             */
            if ($item instanceof assFormulaQuestionUnitCategory) {
                if ($category_name != $item->getDisplayString()) {
                    $new_category = true;
                    $category_name = $item->getDisplayString();
                }
                continue;
            }
            $unit_options[$item->getId()] = $item->getDisplayString() . ($new_category ? ' (' . $category_name . ')' : '');
            $new_category = false;
        }

        if (count($variables)) {
            uasort($variables, function (assFormulaQuestionVariable $v1, assFormulaQuestionVariable $v2) {
                $num_v1 = (int) substr($v1->getVariable(), 2);
                $num_v2 = (int) substr($v2->getVariable(), 2);
                if ($num_v1 > $num_v2) {
                    return 1;
                } elseif ($num_v1 < $num_v2) {
                    return -1;
                }

                return 0;
            });

            foreach ($variables as $variable) {
                /**
                 * @var $variable assFormulaQuestionVariable
                 */
                $variable_header = new ilFormSectionHeaderGUI();
                $variable_header->setTitle(sprintf($this->lng->txt('variable_x'), $variable->getVariable()));

                $range_min = new ilNumberInputGUI($this->lng->txt('range_min'), 'range_min_' . $variable->getVariable());
                $range_min->allowDecimals(true);
                $range_min->setSize(3);
                $range_min->setRequired(true);
                $range_min->setValue($variable->getRangeMin());

                $range_max = new ilNumberInputGUI($this->lng->txt('range_max'), 'range_max_' . $variable->getVariable());
                $range_max->allowDecimals(true);
                $range_max->setSize(3);
                $range_max->setRequired(true);
                $range_max->setValue($variable->getRangeMax());

                $units = new ilSelectInputGUI($this->lng->txt('unit'), 'unit_' . $variable->getVariable());
                $units->setOptions(array(0 => $this->lng->txt('no_selection')) + $unit_options);
                if (is_object($variable->getUnit())) {
                    $units->setValue($variable->getUnit()->getId());
                }

                $precision = new ilNumberInputGUI($this->lng->txt('precision'), 'precision_' . $variable->getVariable());
                $precision->setRequired(true);
                $precision->setSize(3);
                $precision->setMinValue(0);
                $precision->setValue($variable->getPrecision());
                $precision->setInfo($this->lng->txt('fq_precision_info'));

                $intprecision = new ilNumberInputGUI($this->lng->txt('intprecision'), 'intprecision_' . $variable->getVariable());
                $intprecision->setSize(3);
                $intprecision->setMinValue(1);
                $intprecision->setValue($variable->getIntprecision());
                $intprecision->setInfo($this->lng->txt('intprecision_info'));

                $form->addItem($variable_header);
                $form->addItem($range_min);
                $form->addItem($range_max);
                $form->addItem($units);
                $form->addItem($precision);
                $form->addItem($intprecision);
            }
        }
        $quest_vars = [];
        $result_vars = [];
        $results = $this->object->getResults();
        if (count($results)) {
            uasort($results, function (assFormulaQuestionResult $r1, assFormulaQuestionResult $r2) {
                $num_r1 = (int) substr($r1->getResult(), 2);
                $num_r2 = (int) substr($r2->getResult(), 2);
                if ($num_r1 > $num_r2) {
                    return 1;
                } elseif ($num_r1 < $num_r2) {
                    return -1;
                }

                return 0;
            });

            foreach ($results as $result) {
                /**
                 * @var $result assFormulaQuestionResult
                 */
                $result_header = new ilFormSectionHeaderGUI();
                $result_header->setTitle(sprintf($this->lng->txt('result_x'), $result->getResult()));

                $formula = new ilTextInputGUI($this->lng->txt('formula'), 'formula_' . $result->getResult());
                $formula->setInfo($this->lng->txt('fq_formula_desc'));
                $formula->setRequired(true);
                $formula->setSize(50);
                $formula->setValue($result->getFormula());
                $formula->setSuffix(' = ' . $result->getResult());

                if (
                    $suggest_range_for_result !== '' &&
                    strcmp($suggest_range_for_result, $result->getResult()) == 0 &&
                    strlen($result->substituteFormula($variables, $results))
                ) {
                    $result->suggestRange($variables, $results);
                }

                $range_min = new ilNumberInputGUI($this->lng->txt('range_min'), 'range_min_' . $result->getResult());
                $range_min->allowDecimals(true);
                $range_min->setSize(3);
                $range_min->setRequired(true);
                $range_min->setValue($result->getRangeMin());

                $range_max = new ilNumberInputGUI($this->lng->txt('range_max'), 'range_max_' . $result->getResult());
                $range_max->allowDecimals(true);
                $range_max->setSize(3);
                $range_max->setRequired(true);
                $range_max->setValue($result->getRangeMax());

                $matches = [];

                $precision = new ilNumberInputGUI($this->lng->txt('precision'), 'precision_' . $result->getResult());
                $precision->setRequired(true);
                $precision->setSize(3);
                $precision->setMinValue(0);
                $precision->setInfo($this->lng->txt('fq_precision_info'));
                $precision->setValue($result->getPrecision());

                $tolerance = new ilNumberInputGUI($this->lng->txt('tolerance'), 'tolerance_' . $result->getResult());
                $tolerance->setSize(3);
                $tolerance->setMinValue(0);
                $tolerance->setMaxValue(100);
                $tolerance->allowDecimals(true);
                $tolerance->setInfo($this->lng->txt('tolerance_info'));
                $tolerance->setValue($result->getTolerance());

                $suggest_range_button = new ilCustomInputGUI('', '');
                $suggest_range_button->setHtml('<input type="submit" class="btn btn-default" name="cmd[suggestrange_' . $result->getResult() . ']" value="' . $this->lng->txt("suggest_range") . '" />');

                $sel_result_units = new ilSelectInputGUI($this->lng->txt('unit'), 'unit_' . $result->getResult());
                $sel_result_units->setOptions(array(0 => $this->lng->txt('no_selection')) + $unit_options);
                $sel_result_units->setInfo($this->lng->txt('result_unit_info'));
                if (is_object($result->getUnit())) {
                    $sel_result_units->setValue($result->getUnit()->getId());
                }

                $mc_result_units = new ilMultiSelectInputGUI($this->lng->txt('result_units'), 'units_' . $result->getResult());
                $mc_result_units->setOptions($unit_options);
                $mc_result_units->setInfo($this->lng->txt('result_units_info'));
                $selectedvalues = [];
                foreach ($unit_options as $unit_id => $txt) {
                    if ($this->hasResultUnit($result, $unit_id, $result_units)) {
                        $selectedvalues[] = $unit_id;
                    }
                }
                $mc_result_units->setValue($selectedvalues);

                $result_type = new ilRadioGroupInputGUI($this->lng->txt('result_type_selection'), 'result_type_' . $result->getResult());
                $result_type->setRequired(true);

                $no_type = new ilRadioOption($this->lng->txt('no_result_type'), '0');
                $no_type->setInfo($this->lng->txt('fq_no_restriction_info'));

                $result_dec = new ilRadioOption($this->lng->txt('result_dec'), '1');
                $result_dec->setInfo($this->lng->txt('result_dec_info'));

                $result_frac = new ilRadioOption($this->lng->txt('result_frac'), '2');
                $result_frac->setInfo($this->lng->txt('result_frac_info'));

                $result_co_frac = new ilRadioOption($this->lng->txt('result_co_frac'), '3');
                $result_co_frac->setInfo($this->lng->txt('result_co_frac_info'));

                $result_type->addOption($no_type);
                $result_type->addOption($result_dec);
                $result_type->addOption($result_frac);
                $result_type->addOption($result_co_frac);
                $result_type->setValue(strlen($result->getResultType()) ? $result->getResultType() : 0);

                $points = new ilNumberInputGUI($this->lng->txt('points'), 'points_' . $result->getResult());
                $points->allowDecimals(true);
                $points->setRequired(true);
                $points->setSize(3);
                $points->setMinValue(0);
                $points->setValue(strlen($result->getPoints()) ? $result->getPoints() : 1);

                $rating_type = new ilCheckboxInputGUI($this->lng->txt('advanced_rating'), 'rating_advanced_' . $result->getResult());
                $rating_type->setValue(1);
                $rating_type->setInfo($this->lng->txt('advanced_rating_info'));

                if (!$save) {
                    $advanced_rating = $this->canUseAdvancedRating($result);
                    if (!$advanced_rating) {
                        $rating_type->setDisabled(true);
                        $rating_type->setChecked(false);
                    } else {
                        $rating_type->setChecked(strlen($result->getRatingSimple()) && $result->getRatingSimple() ? false : true);
                    }
                }

                $sign = new ilNumberInputGUI($this->lng->txt('rating_sign'), 'rating_sign_' . $result->getResult());
                $sign->setRequired(true);
                $sign->setSize(3);
                $sign->setMinValue(0);
                $sign->setValue($result->getRatingSign());
                $rating_type->addSubItem($sign);

                $value = new ilNumberInputGUI($this->lng->txt('rating_value'), 'rating_value_' . $result->getResult());
                $value->setRequired(true);
                $value->setSize(3);
                $value->setMinValue(0);
                $value->setValue($result->getRatingValue());
                $rating_type->addSubItem($value);

                $unit = new ilNumberInputGUI($this->lng->txt('rating_unit'), 'rating_unit_' . $result->getResult());
                $unit->setRequired(true);
                $unit->setSize(3);
                $unit->setMinValue(0);
                $unit->setValue($result->getRatingUnit());
                $rating_type->addSubItem($unit);

                $info_text = new ilNonEditableValueGUI($this->lng->txt('additional_rating_info'));
                $rating_type->addSubItem($info_text);

                $form->addItem($result_header);
                $form->addItem($formula);
                $form->addItem($range_min);
                $form->addItem($range_max);
                $form->addItem($suggest_range_button);
                $form->addItem($precision);
                $form->addItem($tolerance);
                $form->addItem($sel_result_units);
                $form->addItem($mc_result_units);
                $form->addItem($result_type);
                $form->addItem($points);
                $form->addItem($rating_type);
            }

            $defined_result_vars = [];

            $defined_result_res = [];

            foreach ($variables as $key => $object) {
                $quest_vars[$key] = $key;
            }

            foreach ($results as $key => $object) {
                $result_vars[$key] = $key;
            }

            foreach ($results as $tmp_result) {
                /**
                 * @var $tmp_result assFormulaQuestionResult
                 */
                $formula = $tmp_result->getFormula() ?? '';

                preg_match_all("/([$][v][0-9]*)/", $formula, $form_vars);
                preg_match_all("/([$][r][0-9]*)/", $formula, $form_res);
                foreach ($form_vars[0] as $res_var) {
                    $defined_result_vars[$res_var] = $res_var;
                }

                foreach ($form_res[0] as $res_res) {
                    $defined_result_res[$res_res] = $res_res;
                }
            }
        }

        $result_has_undefined_vars = [];
        $question_has_unused_vars = [];
        $result_has_undefined_res = [];

        if (is_array($quest_vars) && count($quest_vars) > 0) {
            $result_has_undefined_vars = array_diff($defined_result_vars, $quest_vars);
            $question_has_unused_vars = array_diff($quest_vars, $defined_result_vars);
        }

        if (is_array($result_vars) && count($result_vars) > 0) {
            $result_has_undefined_res = array_diff($defined_result_res, $result_vars);
        }
        $error_message = '';
        $checked = false;
        if (count($result_has_undefined_vars) > 0 || count($question_has_unused_vars) > 0) {
            if (count($result_has_undefined_vars) > 0) {
                $error_message .= $this->lng->txt("res_contains_undef_var") . '<br>';
            }
            if (count($question_has_unused_vars) > 0) {
                $error_message .= $this->lng->txt("que_contains_unused_var") . '<br>';
            }

            $checked = false;
            if ($save) {
                $this->tpl->setOnScreenMessage('failure', $error_message);
            }
        }

        if (is_array($result_has_undefined_res) && count($result_has_undefined_res) > 0) {
            $error_message .= $this->lng->txt("res_contains_undef_res") . '<br>';
            $checked = false;
        }

        if ($save && !$checked) {
            $this->tpl->setOnScreenMessage('failure', $error_message);
        }

        if ($this->object->getId()) {
            $hidden = new ilHiddenInputGUI("", "ID");
            $hidden->setValue($this->object->getId());
            $form->addItem($hidden);
        }

        $this->populateTaxonomyFormSection($form);

        $form->addCommandButton('parseQuestion', $this->lng->txt("parseQuestion"));
        $form->addCommandButton('saveReturn', $this->lng->txt("save_return"));
        $form->addCommandButton('save', $this->lng->txt("save"));

        $errors = $checked;

        if ($save) {
            $found_vars = [];
            $found_results = [];
            foreach ($this->request->getParsedBody() as $key => $value) {
                if (preg_match("/^unit_(\\\$v\d+)$/", $key, $matches)) {
                    array_push($found_vars, $matches[1]);
                }
                if (preg_match("/^unit_(\\\$r\d+)$/", $key, $matches)) {
                    array_push($found_results, $matches[1]);
                }
            }


            foreach ($this->request->getParsedBody() as $key => $value) {
                $item = $form->getItemByPostVar($key);
                if ($item !== null) {
                    switch (get_class($item)) {
                        case 'ilDurationInputGUI':
                            $item->setHours($value['hh']);
                            $item->setMinutes($value['mm']);
                            $item->setSeconds($value['ss']);
                            break;
                        default:
                            $item->setValue($value);
                    }
                }
            }

            $check = array_merge($found_vars, $found_results);
            foreach ((array) $form->getItems() as $item) {
                $postvar = $item->getPostVar();
                if (preg_match("/_\\\$[r|v]\d+/", $postvar, $matches)) {
                    $k = substr(array_shift($matches), 1);
                    if (!in_array($k, $check)) {
                        $form->removeItemByPostVar($postvar);
                    }
                }
            }
            $variables = array_filter($variables, fn($k, $v) => in_array($v, $check), ARRAY_FILTER_USE_BOTH);
            $results = array_filter($results, fn($k, $v) => in_array($k, $check), ARRAY_FILTER_USE_BOTH);

            $errors = !$form->checkInput();

            $custom_errors = false;
            if (count($variables)) {
                foreach ($variables as $variable) {
                    /**
                     * @var $variable assFormulaQuestionVariable
                     */
                    $min_range = $form->getItemByPostVar('range_min_' . $variable->getVariable());
                    $max_range = $form->getItemByPostVar('range_max_' . $variable->getVariable());
                    if ($min_range->getValue() > $max_range->getValue()) {
                        $min_range->setAlert($this->lng->txt('err_range'));
                        $max_range->setAlert($this->lng->txt('err_range'));
                        $custom_errors = true;
                    }
                    $intPrecision = $form->getItemByPostVar('intprecision_' . $variable->getVariable());
                    $decimal_spots = $form->getItemByPostVar('precision_' . $variable->getVariable());
                    if ($decimal_spots->getValue() == 0
                        && $min_range->getValue() !== null
                        && $max_range->getValue() !== null
                        && !$variable->isIntPrecisionValid(
                            $intPrecision->getValue(),
                            $min_range->getValue(),
                            $max_range->getValue()
                        )
                    ) {
                        $intPrecision->setAlert($this->lng->txt('err_division'));
                        $custom_errors = true;
                    }
                }
            }

            if (count($results)) {
                foreach ($results as $result) {
                    /**
                     * @var $result assFormulaQuestionResult
                     */
                    $min_range = $form->getItemByPostVar('range_min_' . $result->getResult());
                    $max_range = $form->getItemByPostVar('range_max_' . $result->getResult());
                    if ($min_range->getValue() > $max_range->getValue()) {
                        $min_range->setAlert($this->lng->txt('err_range'));
                        $max_range->setAlert($this->lng->txt('err_range'));
                        $custom_errors = true;
                    }


                    $formula = $form->getItemByPostVar('formula_' . $result->getResult());
                    if (strpos($formula->getValue(), $result->getResult()) !== false) {
                        $formula->setAlert($this->lng->txt('errRecursionInResult'));
                        $custom_errors = true;
                    }

                    $result_unit = $form->getItemByPostVar('unit_' . $result->getResult());
                    $rating_advanced = $form->getItemByPostVar('rating_advanced_' . $result->getResult());
                    if (((int) $result_unit->getValue() <= 0) && $rating_advanced->getChecked()) {
                        unset($_POST['rating_advanced_' . $result->getResult()]);
                        $rating_advanced->setDisabled(true);
                        $rating_advanced->setChecked(false);
                        $rating_advanced->setAlert($this->lng->txt('err_rating_advanced_not_allowed'));
                        $custom_errors = true;
                    } elseif ($rating_advanced->getChecked()) {
                        $rating_sign = $form->getItemByPostVar('rating_sign_' . $result->getResult());
                        $rating_value = $form->getItemByPostVar('rating_value_' . $result->getResult());
                        $rating_unit = $form->getItemByPostVar('rating_unit_' . $result->getResult());

                        $percentage = $rating_sign->getValue() + $rating_value->getValue() + $rating_unit->getValue();
                        if ($percentage != 100) {
                            $rating_advanced->setAlert($this->lng->txt('err_wrong_rating_advanced'));
                            $custom_errors = true;
                        }
                    }

                    preg_match_all("/([$][v][0-9]*)/", $formula->getValue(), $form_vars);
                    $result_has_undefined_vars = array_diff($form_vars[0], $found_vars);
                    if (count($result_has_undefined_vars)) {
                        $errors = true;
                        $this->tpl->setOnScreenMessage('info', $this->lng->txt('res_contains_undef_var'));
                    }
                }
            }

            if ($custom_errors && !$errors) {
                $errors = true;
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('form_input_not_valid'));
            }
            foreach ($this->request->getParsedBody() as $key => $value) {
                $item = $form->getItemByPostVar($key);
                if ($item !== null) {
                    switch (get_class($item)) {
                        case 'ilDurationInputGUI':
                            $item->setHours($value['hh']);
                            $item->setMinutes($value['mm']);
                            $item->setSeconds($value['ss']);
                            break;
                        default:
                            $item->setValue($value);
                    }
                }
            } // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
            if ($errors) {
                $checkonly = false;
            }
        }

        if (!$checkonly) {
            $this->tpl->setVariable('QUESTION_DATA', $form->getHTML());
        }
        return $errors;
    }

    private function hasResultUnit($result, $unit_id, $resultunits): bool
    {
        if (array_key_exists($result->getResult(), $resultunits)) {
            if (array_key_exists($unit_id, $resultunits[$result->getResult()])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if advanced rating can be used for a result. This is only possible if there is exactly
     * one possible correct unit for the result, otherwise it is impossible to determine wheather the
     * unit is correct or the value.
     *
     * @return boolean True if advanced rating could be used, false otherwise
     */
    private function canUseAdvancedRating($result): bool
    {
        $resultunit = $result->getUnit();

        /*
         *  if there is a result-unit (unit selectbox) selected it is possible to use advanced rating
         * 	if there is no result-unit selected it is NOT possible to use advanced rating, because there is no
         * 	definition if the result-value or the unit-value should be the correct solution!!
         *
         */
        if (is_object($resultunit)) {
            return true;
        } else {
            return false;
        }
    }

    public function parseQuestion(): void
    {
        $this->writePostData();
        $this->addSaveOnEnterOnLoadCode();
        $this->editQuestion();
    }

    /**
     * check input fields
     */
    public function checkInput(): bool
    {
        if ((!$_POST["title"]) or (!$_POST["author"]) or (!$_POST["question"])) {
            $this->addErrorMessage($this->lng->txt("fill_out_all_required_fields"));
            return false;
        }


        return true;
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

        $user_solution = [];
        if ($pass !== null) {
            $user_solution = $this->object->getVariableSolutionValuesForPass($active_id, $pass);
        } else {
            $user_solution = array_reduce(
                $this->object->fetchAllVariables($this->object->getQuestion()),
                static function (array $c, assFormulaQuestionVariable $v): array {
                    $c[$v->getVariable()] = $v->getVariable();
                    return $c;
                },
                []
            );
        }

        if (($active_id > 0) && (!$show_correct_solution)) {
            $user_solution["active_id"] = $active_id;
            $user_solution["pass"] = $pass;
            $solutions = $this->object->getSolutionValues($active_id, $pass);
            foreach ($solutions as $idx => $solution_value) {
                if (preg_match("/^(\\\$v\\d+)$/", $solution_value["value1"], $matches)) {
                    $user_solution[$matches[1]] = $solution_value["value2"];
                } elseif (preg_match("/^(\\\$r\\d+)$/", $solution_value["value1"], $matches)) {
                    if (!array_key_exists($matches[1], $user_solution)) {
                        $user_solution[$matches[1]] = array();
                    }
                    $user_solution[$matches[1]]["value"] = $solution_value["value2"];
                } elseif (preg_match("/^(\\\$r\\d+)_unit$/", $solution_value["value1"], $matches)) {
                    if (!array_key_exists($matches[1], $user_solution)) {
                        $user_solution[$matches[1]] = array();
                    }
                    $user_solution[$matches[1]]["unit"] = $solution_value["value2"];
                }
            }
        } elseif ($active_id) {
            $user_solution = $this->object->getBestSolution($this->object->getSolutionValues($active_id, $pass));
        } elseif (is_object($this->getPreviewSession())) {
            $solution_values = [];

            $participantsSolution = $this->getPreviewSession()->getParticipantsSolution();
            if (is_array($participantsSolution)) {
                foreach ($participantsSolution as $val1 => $val2) {
                    $solution_values[] = array('value1' => $val1, 'value2' => $val2);
                }
            }

            $user_solution = $this->object->getBestSolution($solution_values);
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
        $template = new ilTemplate("tpl.il_as_qpl_formulaquestion_output_solution.html", true, true, 'Modules/TestQuestionPool');
        $correctness_icons = [
            'correct' => $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_OK),
            'not_correct' => $this->generateCorrectnessIconsForCorrectness(self::CORRECTNESS_NOT_OK)
        ];
        $questiontext = $this->object->substituteVariables($user_solutions, $graphical_output, true, $result_output, $correctness_icons);
        $template->setVariable("QUESTIONTEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($questiontext, true));
        $questionoutput = $template->get();
        $solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html", true, true, "Modules/TestQuestionPool");
        $feedback = ($show_feedback) ? $this->getGenericFeedbackOutput((int) $active_id, $pass) : "";
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

    public function getPreview($show_question_only = false, $showInlineFeedback = false): string
    {
        $user_solution = [];

        if (is_object($this->getPreviewSession())) {
            $solutions = (array) $this->getPreviewSession()->getParticipantsSolution();

            foreach ($solutions as $val1 => $val2) {
                if (preg_match("/^(\\\$v\\d+)$/", $val1, $matches)) {
                    $user_solution[$matches[1]] = $val2;
                } elseif (preg_match("/^(\\\$r\\d+)$/", $val1, $matches)) {
                    if (!array_key_exists($matches[1], $user_solution)) {
                        $user_solution[$matches[1]] = [];
                    }
                    $user_solution[$matches[1]]["value"] = $val2;
                } elseif (preg_match("/^(\\\$r\\d+)_unit$/", $val1, $matches)) {
                    if (!array_key_exists($matches[1], $user_solution)) {
                        $user_solution[$matches[1]] = [];
                    }
                    $user_solution[$matches[1]]["unit"] = $val2;
                }

                if (preg_match("/^(\\\$r\\d+)/", $val1, $matches) && !isset($user_solution[$matches[1]]["result_type"])) {
                    $user_solution[$matches[1]]["result_type"] = assFormulaQuestionResult::getResultTypeByQstId($this->object->getId(), $val1);
                }
            }
        }

        if (!$this->object->hasRequiredVariableSolutionValues($user_solution)) {
            $user_solution = $this->object->getInitialVariableSolutionValues();

            if (is_object($this->getPreviewSession())) {
                $this->getPreviewSession()->setParticipantsSolution($user_solution);
            }
        }

        $template = new ilTemplate("tpl.il_as_qpl_formulaquestion_output.html", true, true, 'Modules/TestQuestionPool');
        if (is_object($this->getPreviewSession())) {
            $questiontext = $this->object->substituteVariables($user_solution);
        } else {
            $questiontext = $this->object->substituteVariables([]);
        }
        $template->setVariable("QUESTIONTEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($questiontext, true));
        $questionoutput = $template->get();
        if (!$show_question_only) {
            // get page object output
            $questionoutput = $this->getILIASPage($questionoutput);
        }
        return $questionoutput;
    }

    // hey: prevPassSolutions - pass will be always available from now on
    public function getTestOutput($active_id, $pass, $is_postponed = false, $use_post_solutions = false, $show_feedback = false): string
    // hey.
    {
        $this->tpl->setOnScreenMessage('info', $this->lng->txt('enter_valid_values'));
        // get the solution of the user for the active pass or from the last pass if allowed
        $user_solution = [];
        if ($active_id) {
            $solutions = $this->object->getTestOutputSolutions($active_id, $pass);
            $actualPassIndex = null;
            if ($this->object->getTestPresentationConfig()->isSolutionInitiallyPrefilled()) {
                $actualPassIndex = ilObjTest::_getPass($active_id);
            }

            foreach ($solutions as $idx => $solution_value) {
                if (preg_match("/^(\\\$v\\d+)$/", $solution_value['value1'], $matches)) {
                    if ($this->object->getTestPresentationConfig()->isSolutionInitiallyPrefilled()) {
                        $this->object->saveCurrentSolution($active_id, $actualPassIndex, $matches[1], $solution_value['value2'], true);
                    }

                    $user_solution[$matches[1]] = $solution_value['value2'];
                } elseif (preg_match("/^(\\\$r\\d+)$/", $solution_value['value1'], $matches)) {
                    if (!array_key_exists($matches[1], $user_solution)) {
                        $user_solution[$matches[1]] = [];
                    }
                    $user_solution[$matches[1]]["value"] = $solution_value['value2'];
                } elseif (preg_match("/^(\\\$r\\d+)_unit$/", $solution_value['value1'], $matches)) {
                    if (!array_key_exists($matches[1], $user_solution)) {
                        $user_solution[$matches[1]] = [];
                    }
                    $user_solution[$matches[1]]["unit"] = $solution_value['value2'];
                }
                if (preg_match("/^(\\\$r\\d+)/", $solution_value['value1'], $matches) && !isset($user_solution[$matches[1]]["result_type"])) {
                    $user_solution[$matches[1]]["result_type"] = assFormulaQuestionResult::getResultTypeByQstId($this->object->getId(), $solution_value['value1']);
                }
            }
        }

        $solutions = $this->object->getSolutionValues($active_id, $pass, true);
        foreach ($solutions as $idx => $solution_value) {
            if (preg_match("/^(\\\$v\\d+)$/", $solution_value['value1'], $matches)) {
                $user_solution[$matches[1]] = $solution_value['value2'];
            }
        }

        if ($user_solution === []) {
            $user_solution = $this->object->getVariableSolutionValuesForPass($active_id, $pass);
        }

        // generate the question output
        $template = new ilTemplate("tpl.il_as_qpl_formulaquestion_output.html", true, true, 'Modules/TestQuestionPool');

        $questiontext = $this->object->substituteVariables($user_solution);

        $template->setVariable("QUESTIONTEXT", ilLegacyFormElementsUtil::prepareTextareaOutput($questiontext, true));

        $questionoutput = $template->get();
        $pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
        return $pageoutput;
    }

    public function getSpecificFeedbackOutput(array $userSolution): string
    {
        return '';
    }
}
