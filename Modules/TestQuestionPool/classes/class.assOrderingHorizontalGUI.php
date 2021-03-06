<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once './Modules/TestQuestionPool/classes/class.assQuestionGUI.php';
require_once './Modules/TestQuestionPool/interfaces/interface.ilGuiQuestionScoringAdjustable.php';
require_once './Modules/Test/classes/inc.AssessmentConstants.php';

/**
 * The assOrderingHorizontalGUI class encapsulates the GUI representation for horizontal ordering questions.
 *
 * @author	Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author	Björn Heyser <bheyser@databay.de>
 * @author	Maximilian Becker <mbecker@databay.de>
 *          
 * @version	$Id: class.assOrderingHorizontalGUI.php 54008 2014-10-03 12:37:33Z mjansen $
 *          
 * @ingroup ModulesTestQuestionPool
 *          
 * @ilctrl_iscalledby assOrderingHorizontalGUI: ilObjQuestionPoolGUI
 */
class assOrderingHorizontalGUI extends assQuestionGUI implements ilGuiQuestionScoringAdjustable
{
	/**
	* assOrderingHorizontalGUI constructor
	*
	* The constructor takes possible arguments an creates an instance of the assOrderingHorizontalGUI object.
	*
	* @param integer $id The database id of a single choice question object
	* @access public
	*/
	function __construct($id = -1)
	{
		parent::__construct();
		include_once "./Modules/TestQuestionPool/classes/class.assOrderingHorizontal.php";
		$this->object = new assOrderingHorizontal();
		$this->setErrorMessage($this->lng->txt("msg_form_save_error"));
		if ($id >= 0)
		{
			$this->object->loadFromDb($id);
		}
	}

	function getCommand($cmd)
	{
		return $cmd;
	}

	/**
	 * Evaluates a posted edit form and writes the form data in the question object
	 *
	 * @param bool $always
	 *
	 * @return integer A positive value, if one of the required fields wasn't set, else 0
	 */
	public function writePostData($always = false)
	{
		$hasErrors = (!$always) ? $this->editQuestion(true) : false;
		if (!$hasErrors)
		{
			$this->writeQuestionGenericPostData();
			$this->writeQuestionSpecificPostData();
			$this->saveTaxonomyAssignments();
			return 0;
		}
		return 1;
	}

	/**
	* Creates an output of the edit form for the question
	*
	* @access public
	*/
	public function editQuestion($checkonly = FALSE)
	{
		$save = $this->isSaveCommand();
		$this->getQuestionTemplate();

		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->outQuestionType());
		$form->setMultipart(FALSE);
		$form->setTableWidth("100%");
		$form->setId("orderinghorizontal");

		$this->addBasicQuestionFormProperties( $form );
		$this->populateQuestionSpecificFormPart( $form );


		$this->populateTaxonomyFormSection($form);

		$this->addQuestionFormCommandButtons($form);
		
		$errors = false;
	
		if ($save)
		{
			$form->setValuesByPost();
			$errors = !$form->checkInput();
			$form->setValuesByPost(); // again, because checkInput now performs the whole stripSlashes handling and we need this if we don't want to have duplication of backslashes
			if ($errors) $checkonly = false;
		}

		if (!$checkonly) $this->tpl->setVariable("QUESTION_DATA", $form->getHTML());
		return $errors;
	}
	
	function outQuestionForTest($formaction, $active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
		$test_output = $this->getTestOutput($active_id, $pass, $is_postponed, $use_post_solutions, $show_feedback); 
		$this->tpl->setVariable("QUESTION_OUTPUT", $test_output);
		$this->tpl->setVariable("FORMACTION", $formaction);
	}

	/**
	* Get the question solution output
	*
	* @param integer $active_id The active user id
	* @param integer $pass The test pass
	* @param boolean $graphicalOutput Show visual feedback for right/wrong answers
	* @param boolean $result_output Show the reached points for parts of the question
	* @param boolean $show_question_only Show the question without the ILIAS content around
	* @param boolean $show_feedback Show the question feedback
	* @param boolean $show_correct_solution Show the correct solution instead of the user solution
	* @param boolean $show_manual_scoring Show specific information for the manual scoring output
	* @return The solution output of the question as HTML code
	*/
	function getSolutionOutput(
		$active_id,
		$pass = NULL,
		$graphicalOutput = FALSE,
		$result_output = FALSE,
		$show_question_only = TRUE,
		$show_feedback = FALSE,
		$show_correct_solution = FALSE,
		$show_manual_scoring = FALSE,
		$show_question_text = TRUE
	)
	{
		// get the solution of the user for the active pass or from the last pass if allowed
		$template = new ilTemplate("tpl.il_as_qpl_orderinghorizontal_output_solution.html", TRUE, TRUE, "Modules/TestQuestionPool");

		$solutionvalue = "";
		if (($active_id > 0) && (!$show_correct_solution))
		{
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			if (strlen($solutions[0]["value1"]))
			{
				$elements = split("{::}", $solutions[0]["value1"]);
				foreach ($elements as $id => $element)
				{
					$template->setCurrentBlock("element");
					$template->setVariable("ELEMENT_ID", "e$id");
					$template->setVariable("ELEMENT_VALUE", ilUtil::prepareFormOutput($element));
					$template->parseCurrentBlock();
				}
			}
			$solutionvalue = str_replace("{::}", " ", $solutions[0]["value1"]);
		}
		else
		{
			$elements = $this->object->getOrderingElements();
			foreach ($elements as $id => $element)
			{
				$template->setCurrentBlock("element");
				$template->setVariable("ELEMENT_ID", "e$id");
				$template->setVariable("ELEMENT_VALUE", ilUtil::prepareFormOutput($element));
				$template->parseCurrentBlock();
			}
			$solutionvalue = join($this->object->getOrderingElements(), " ");
		}

		if (($active_id > 0) && (!$show_correct_solution))
		{
			$reached_points = $this->object->getReachedPoints($active_id, $pass);
			if ($graphicalOutput)
			{
				// output of ok/not ok icons for user entered solutions
				if ($reached_points == $this->object->getMaximumPoints())
				{
					$template->setCurrentBlock("icon_ok");
					$template->setVariable("ICON_OK", ilUtil::getImagePath("icon_ok.png"));
					$template->setVariable("TEXT_OK", $this->lng->txt("answer_is_right"));
					$template->parseCurrentBlock();
				}
				else
				{
					$template->setCurrentBlock("icon_ok");
					if ($reached_points > 0)
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_mostly_ok.png"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_not_correct_but_positive"));
					}
					else
					{
						$template->setVariable("ICON_NOT_OK", ilUtil::getImagePath("icon_not_ok.png"));
						$template->setVariable("TEXT_NOT_OK", $this->lng->txt("answer_is_wrong"));
					}
					$template->parseCurrentBlock();
				}
			}
		}
		else
		{
			$reached_points = $this->object->getPoints();
		}

		if ($result_output)
		{
			$resulttext = ($reached_points == 1) ? "(%s " . $this->lng->txt("point") . ")" : "(%s " . $this->lng->txt("points") . ")"; 
			$template->setVariable("RESULT_OUTPUT", sprintf($resulttext, $reached_points));
		}
		if($show_question_text==true)
		{
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		}
//		$template->setVariable("SOLUTION_TEXT", ilUtil::prepareFormOutput($solutionvalue));
		if ($this->object->textsize >= 10) echo $template->setVariable("STYLE", " style=\"font-size: " . $this->object->textsize . "%;\"");

		$questionoutput = $template->get();
		$solutiontemplate = new ilTemplate("tpl.il_as_tst_solution_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$solutiontemplate->setVariable("SOLUTION_OUTPUT", $questionoutput);


		$feedback = '';
		if($show_feedback)
		{
			$fb = $this->getGenericFeedbackOutput($active_id, $pass);
			$feedback .=  strlen($fb) ? $fb : '';

			$fb = $this->getSpecificFeedbackOutput($active_id, $pass);
			$feedback .=  strlen($fb) ? $fb : '';
		}
		if (strlen($feedback)) $solutiontemplate->setVariable("FEEDBACK", $this->object->prepareTextareaOutput( $feedback, true ));
		$solutionoutput = $solutiontemplate->get();
		if (!$show_question_only)
		{
			// get page object output
			$solutionoutput = '<div class="ilc_question_Standard">'.$solutionoutput. $feedback."</div>" ;
		}
		return $solutionoutput;
	}
	
	function getPreview($show_question_only = FALSE)
	{
		$template = new ilTemplate("tpl.il_as_qpl_orderinghorizontal_preview.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$elements = $this->object->getRandomOrderingElements();
		foreach ($elements as $id => $element)
		{
			$template->setCurrentBlock("element");
			$template->setVariable("ELEMENT_ID", "e$id");
			$template->setVariable("ELEMENT_VALUE", ilUtil::prepareFormOutput($element));
			
			$template->parseCurrentBlock();
		}
		if ($this->object->textsize >= 10) echo $template->setVariable("STYLE", " style=\"font-size: " . $this->object->textsize . "%;\"");
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initDragDropAnimation();
		$this->tpl->addJavascript("./Modules/TestQuestionPool/templates/default/orderinghorizontal.js");
		return $questionoutput;
	}

	function getTestOutput($active_id, $pass = NULL, $is_postponed = FALSE, $use_post_solutions = FALSE, $show_feedback = FALSE)
	{
		// generate the question output
		$template = new ilTemplate("tpl.il_as_qpl_orderinghorizontal_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$elements = $this->object->getRandomOrderingElements();
		
		if ($active_id)
		{
			$solutions = NULL;
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			if (!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if (is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			if (count($solutions) == 1)
			{
				$elements = split("{::}", $solutions[0]["value1"]);
			}
		}
		if (strlen($_SESSION['qst_selection']))
		{
			$this->object->moveRight($_SESSION['qst_selection'], $active_id, $pass);
			unset($_SESSION['qst_selection']);
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			if (count($solutions) == 1)
			{
				$elements = split("{::}", $solutions[0]["value1"]);
			}
		}
		if (count($solutions) == 0)
		{
			$_SESSION['qst_ordering_horizontal_elements'] = $elements;
		}
		else
		{
			unset($_SESSION['qst_ordering_horizontal_elements']);
		}
		$idx = 0;
		foreach ($elements as $id => $element)
		{
			$template->setCurrentBlock("element");
			$template->setVariable("ELEMENT_ID", "e_" . $this->object->getId() . "_$id");
			$template->setVariable("ORDERING_VALUE", ilUtil::prepareFormOutput($element));
			$template->setVariable("ELEMENT_VALUE", ilUtil::prepareFormOutput($element));
			$this->ctrl->setParameterByClass('iltestoutputgui', 'qst_selection', $idx);
			$idx++;
			$url = $this->ctrl->getLinkTargetByClass('iltestoutputgui', 'gotoQuestion');
			$template->setVariable("MOVE_RIGHT", $url);
			$template->setVariable("TEXT_MOVE_RIGHT", $this->lng->txt('move_right'));
			$template->setVariable("RIGHT_IMAGE", ilUtil::getImagePath('nav_arr_R.png'));
			$template->parseCurrentBlock();
		}
		if ($this->object->textsize >= 10) echo $template->setVariable("STYLE", " style=\"font-size: " . $this->object->textsize . "%;\"");
		$template->setVariable("VALUE_ORDERRESULT", ' value="' . join($elements, '{::}') . '"');
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($this->object->getQuestion(), TRUE));
		$questionoutput = $template->get();
		if (!$show_question_only)
		{
			// get page object output
			$questionoutput = $this->getILIASPage($questionoutput);
		}
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initDragDropAnimation();
		$this->tpl->addJavascript("./Modules/TestQuestionPool/templates/default/orderinghorizontal.js");
		$questionoutput = $template->get();
		$pageoutput = $this->outQuestionPage("", $is_postponed, $active_id, $questionoutput);
		return $pageoutput;
	}

	/**
	* Saves the feedback for a single choice question
	*
	* @access public
	*/
	function saveFeedback()
	{
		include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
		$errors = $this->feedback(true);
		$this->object->saveFeedbackGeneric(0, $_POST["feedback_incomplete"]);
		$this->object->saveFeedbackGeneric(1, $_POST["feedback_complete"]);
		$this->object->cleanupMediaObjectUsage();
		parent::saveFeedback();
	}

	/**
	 * Sets the ILIAS tabs for this question type
	 *
	 * @access public
	 * 
	 * @todo:	MOVE THIS STEPS TO COMMON QUESTION CLASS assQuestionGUI
	 */
	function setQuestionTabs()
	{
		global $rbacsystem, $ilTabs;
		
		$this->ctrl->setParameterByClass("ilAssQuestionPageGUI", "q_id", $_GET["q_id"]);
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$q_type = $this->object->getQuestionType();

		if (strlen($q_type))
		{
			$classname = $q_type . "GUI";
			$this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", $q_type);
			$this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET["q_id"]);
		}

		if ($_GET["q_id"])
		{
			if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
			{
				// edit page
				$ilTabs->addTarget("edit_page",
					$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"),
					array("edit", "insert", "exec_pg"),
					"", "", $force_active);
			}
	
			// edit page
			$ilTabs->addTarget("preview",
				$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "preview"),
				array("preview"),
				"ilAssQuestionPageGUI", "", $force_active);
		}

		$force_active = false;
		if ($rbacsystem->checkAccess('write', $_GET["ref_id"]))
		{
			$url = "";
			if ($classname) $url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
			$commands = $_POST["cmd"];
			if (is_array($commands))
			{
				foreach ($commands as $key => $value)
				{
					if (preg_match("/^suggestrange_.*/", $key, $matches))
					{
						$force_active = true;
					}
				}
			}
			// edit question properties
			$ilTabs->addTarget("edit_question",
				$url,
				array("editQuestion", "save", "saveEdit", "originalSyncForm"),
				$classname, "", $force_active);
		}

		// add tab for question feedback within common class assQuestionGUI
		$this->addTab_QuestionFeedback($ilTabs);

		// add tab for question hint within common class assQuestionGUI
		$this->addTab_QuestionHints($ilTabs);
		
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("solution_hint",
				$this->ctrl->getLinkTargetByClass($classname, "suggestedsolution"),
				array("suggestedsolution", "saveSuggestedSolution", "outSolutionExplorer", "cancel", 
				"addSuggestedSolution","cancelExplorer", "linkChilds", "removeSuggestedSolution"
				),
				$classname, 
				""
			);
		}

		// Assessment of questions sub menu entry
		if ($_GET["q_id"])
		{
			$ilTabs->addTarget("statistics",
				$this->ctrl->getLinkTargetByClass($classname, "assessment"),
				array("assessment"),
				$classname, "");
		}
		
		if (($_GET["calling_test"] > 0) || ($_GET["test_ref_id"] > 0))
		{
			$ref_id = $_GET["calling_test"];
			if (strlen($ref_id) == 0) $ref_id = $_GET["test_ref_id"];

                        global $___test_express_mode;

                        if (!$_GET['test_express_mode'] && !$___test_express_mode) {
                            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), "ilias.php?baseClass=ilObjTestGUI&cmd=questions&ref_id=$ref_id");
                        }
                        else {
                            $link = ilTestExpressPage::getReturnToPageLink();
                            $ilTabs->setBackTarget($this->lng->txt("backtocallingtest"), $link);
                        }
		}
		else
		{
			$ilTabs->setBackTarget($this->lng->txt("qpl"), $this->ctrl->getLinkTargetByClass("ilobjquestionpoolgui", "questions"));
		}
	}

	function getSpecificFeedbackOutput($active_id, $pass)
	{
		$output = '<table class="ilTstSpecificFeedbackTable"><tbody>';

		if(strpos($this->object->getOrderText(),'::'))
		{
			$answers = explode('::', $this->object->getOrderText());
		} else {
			$answers = explode(' ', $this->object->getOrderText());
		}

		foreach($answers as $idx => $answer)
		{
			$feedback = $this->object->feedbackOBJ->getSpecificAnswerFeedbackTestPresentation(
				$this->object->getId(), $idx
			);

			$output .= "<tr><td><b><i>{$answer}</i></b></td><td>{$feedback}</td></tr>";
		}

		$output .= '</tbody></table>';

		return $this->object->prepareTextareaOutput($output, TRUE);
	}

	public function writeQuestionSpecificPostData($always = true)
	{
		$this->object->setTextSize( $_POST["textsize"] );
		$this->object->setOrderText( $_POST["ordertext"] );
		$this->object->setPoints( $_POST["points"] );
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
	public function getAfterParticipationSuppressionQuestionPostVars()
	{
		return array();
	}

	public function populateQuestionSpecificFormPart(\ilPropertyFormGUI $form)
	{
		// ordertext
		$ordertext = new ilTextAreaInputGUI($this->lng->txt( "ordertext" ), "ordertext");
		$ordertext->setValue( $this->object->prepareTextareaOutput( $this->object->getOrderText() ) );
		$ordertext->setRequired( TRUE );
		$ordertext->setInfo( sprintf( $this->lng->txt( "ordertext_info" ), $this->object->separator ) );
		$ordertext->setRows( 10 );
		$ordertext->setCols( 80 );
		$form->addItem( $ordertext );
		// textsize
		$textsize = new ilNumberInputGUI($this->lng->txt( "textsize" ), "textsize");
		$textsize->setValue( $this->object->getTextSize() );
		$textsize->setInfo( $this->lng->txt( "textsize_info" ) );
		$textsize->setSize( 6 );
		$textsize->setMinValue( 10 );
		$textsize->setRequired( FALSE );
		$form->addItem( $textsize );
		// points
		$points = new ilNumberInputGUI($this->lng->txt( "points" ), "points");

		$points->allowDecimals( true );
		// mbecker: Fix for mantis bug 7866: Predefined values schould make sense.
		// This implements a default value of "1" for this question type.
		if ($this->object->getPoints() == null)
		{
			$points->setValue( "1" );
		}
		else
		{
			$points->setValue( $this->object->getPoints() );
		}
		$points->setRequired( TRUE );
		$points->setSize( 3 );
		$points->setMinValue( 0.0 );
		$points->setMinvalueShouldBeGreater( true );
		$form->addItem( $points );
	}

	/**
	 * Returns an html string containing a question specific representation of the answers so far
	 * given in the test for use in the right column in the scoring adjustment user interface.
	 *
	 * @param array $relevant_answers
	 *
	 * @return string
	 */
	public function getAggregatedAnswersView($relevant_answers)
	{
		return  $this->renderAggregateView(
					$this->aggregateAnswers( $relevant_answers, $this->object->getOrderText() ) )->get();
	}

	public function aggregateAnswers($relevant_answers_chosen, $answer_defined_on_question)
	{
		$aggregate = array();
		foreach($relevant_answers_chosen as $answer)
		{
			$answer = str_replace($this->object->getAnswerSeparator(), '&nbsp;&nbsp;-&nbsp;&nbsp;', $answer);
			if (in_array($answer['value1'], $aggregate))
			{
				$aggregate[$answer['value1']] = $aggregate[$answer['value1']]+1;
			}
			else
			{
				$aggregate[$answer['value1']] = 1;
			}
		}

		return $aggregate;
	}

	/**
	 * @param $aggregate
	 *
	 * @return ilTemplate
	 */
	public function renderAggregateView($aggregate)
	{
		$tpl = new ilTemplate('tpl.il_as_aggregated_answers_table.html', true, true, "Modules/TestQuestionPool");

		foreach ($aggregate as $key => $line_data)
		{
			$tpl->setCurrentBlock( 'aggregaterow' );
			$tpl->setVariable( 'COUNT', $line_data );
			$tpl->setVariable( 'OPTION', $key );
			$tpl->parseCurrentBlock();
		}
		return $tpl;
	}
}