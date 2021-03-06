<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once "./Services/Object/classes/class.ilObjectGUI.php";
require_once("./Services/FileSystem/classes/class.ilFileSystemGUI.php");
require_once("Services/User/classes/class.ilObjUser.php");

require_once("./Modules/ScormAicc/classes/class.ilObjSAHSLearningModuleGUI.php");
require_once("./Modules/ScormAicc/classes/class.ilObjSCORMLearningModule.php");

/**
* Class ilObjSCORMLearningModuleGUI
*
* @author Alex Killing <alex.killing@gmx.de>, Hendrik Holtmann <holtmann@mac.com>
* $Id: class.ilObjSCORMLearningModuleGUI.php 47188 2014-01-12 15:46:16Z ukohnle $
*
* @ilCtrl_Calls ilObjSCORMLearningModuleGUI: ilFileSystemGUI, ilMDEditorGUI, ilPermissionGUI, ilLearningProgressGUI
* @ilCtrl_Calls ilObjSCORMLearningModuleGUI: ilInfoScreenGUI
* @ilCtrl_Calls ilObjSCORMLearningModuleGUI: ilCertificateGUI
* @ilCtrl_Calls ilObjSCORMLearningModuleGUI: ilLicenseGUI
* @ilCtrl_Calls ilObjSCORMLearningModuleGUI: ilSCORMOfflineModeUsersTableGUI
*
* @ingroup ModulesScormAicc
*/
class ilObjSCORMLearningModuleGUI extends ilObjSAHSLearningModuleGUI
{
	const EXPORT_UNDEF = 0;
	const EXPORT_ALL = 1;
	const EXPORT_SELECTED = 2;

	const EXPORT_TYPE_RAW = 1;
	const EXPORT_TYPE_SUCCESS = 2;

	/**
	* Constructor
	*
	* @access	public
	*/
	function ilObjSCORMLearningModuleGUI($a_data,$a_id,$a_call_by_reference, $a_prepare_output = true)
	{
		global $lng;

		$lng->loadLanguageModule("content");
		$lng->loadLanguageModule("search");
		
		$this->type = "sahs";
		$this->ilObjectGUI($a_data,$a_id,$a_call_by_reference,false);
	}

	/**
	* assign scorm object to scorm gui object
	*/
	function assignObject()
	{
		if ($this->id != 0)
		{
			if ($this->call_by_reference)
			{
				$this->object =& new ilObjSCORMLearningModule($this->id, true);
			}
			else
			{
				$this->object =& new ilObjSCORMLearningModule($this->id, false);
			}
		}
	}

	/**
	* scorm module properties
	*/
	function properties()
	{
		global $rbacsystem, $tree, $tpl, $lng, $ilToolbar, $ilCtrl, $ilSetting;

		//$this->setSubTabs("settings", "general_settings");
		
		$lng->loadLanguageModule("style");

		// view
		$ilToolbar->addButton($this->lng->txt("view"),
			"ilias.php?baseClass=ilSAHSPresentationGUI&amp;ref_id=".$this->object->getRefID(),
			"_blank");
			
		// upload new version
		$ilToolbar->addButton($this->lng->txt("cont_sc_new_version"),
			$this->ctrl->getLinkTarget($this, "newModuleVersion"));

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($ilCtrl->getFormAction($this));
		$this->form->setTitle($this->lng->txt("cont_lm_properties"));

		// SCORM-type
		$ne = new ilNonEditableValueGUI($this->lng->txt("type"), "");
		$ne->setValue($this->lng->txt( "lm_type_" . ilObjSAHSLearningModule::_lookupSubType( $this->object->getID() ) ) );
		$this->form->addItem($ne);

		// version
		$ne = new ilNonEditableValueGUI($this->lng->txt("cont_sc_version"), "");
		$ne->setValue($this->object->getModuleVersion());
		$this->form->addItem($ne);

		// online
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_online"), "cobj_online");
		$cb->setValue("y");
		if ($this->object->getOnline())
		{
			$cb->setChecked(true);
		}
		$this->form->addItem($cb);

		// offline Mode
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_offline_mode_allow"), "cobj_offline_mode");
		$cb->setValue("y");
		$cb->setChecked($this->object->getOfflineMode());
		include_once("./Modules/ScormAicc/classes/class.ilSCORMOfflineMode.php");
		if ($this->object->getOfflineMode()== true && ilSCORMOfflineMode::checkIfAnyoneIsInOfflineMode($this->object->getID()) == true) {
			$cb->setDisabled(true);
			$cb->setInfo($this->lng->txt("cont_offline_mode_disable_not_allowed_info"));
		} else {
			$cb->setInfo($this->lng->txt("cont_offline_mode_allow_info"));
		}
		$this->form->addItem($cb);

		//
		// presentation
		//
		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->lng->txt("cont_presentation"));
		$this->form->addItem($sh);
		
		// display mode (open)
		$options = array(
			"0" => $this->lng->txt("cont_open_normal"),
			"1" => $this->lng->txt("cont_open_iframe_max"),
			"2" => $this->lng->txt("cont_open_iframe_defined"),
			"5" => $this->lng->txt("cont_open_window_undefined"),
			"6" => $this->lng->txt("cont_open_window_defined")
			);
		$si = new ilSelectInputGUI($this->lng->txt("cont_open"), "open_mode");
		$si->setOptions($options);
		$si->setValue($this->object->getOpenMode());
		$this->form->addItem($si);
		
		// width
		$ni = new ilNumberInputGUI($this->lng->txt("cont_width"), "width");
		$ni->setMaxLength(4);
		$ni->setSize(4);
		$ni->setValue($this->object->getWidth());
		$this->form->addItem($ni);
		
		// height
		$ni = new ilNumberInputGUI($this->lng->txt("cont_height"), "height");
		$ni->setMaxLength(4);
		$ni->setSize(4);
		$ni->setValue($this->object->getHeight());
		$this->form->addItem($ni);
		
		// auto navigation to last visited item
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_auto_last_visited"), "cobj_auto_last_visited");
		$cb->setValue("y");
		$cb->setChecked($this->object->getAuto_last_visited());
		$cb->setInfo($this->lng->txt("cont_auto_last_visited_info"));
		$this->form->addItem($cb);

		// auto continue
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_sc_auto_continue"), "auto_continue");
		$cb->setValue("y");
		$cb->setChecked($this->object->getAutoContinue());
		$this->form->addItem($cb);

		//
		// scorm options
		//
		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->lng->txt("cont_scorm_options"));
		$this->form->addItem($sh);

		// max attempts
		$ni = new ilNumberInputGUI($this->lng->txt("cont_sc_max_attempt"), "max_attempt");
		$ni->setMaxLength(3);
		$ni->setSize(3);
		$ni->setValue($this->object->getMaxAttempt());
		$this->form->addItem($ni);
		
		// lesson mode
		$options = array("normal" => $this->lng->txt("cont_sc_less_mode_normal"),
				"browse" => $this->lng->txt("cont_sc_less_mode_browse"));
		$si = new ilSelectInputGUI($this->lng->txt("cont_def_lesson_mode"), "lesson_mode");
		$si->setOptions($options);
		$si->setValue($this->object->getDefaultLessonMode());
		$this->form->addItem($si);
		
		// credit mode
		$options = array("credit" => $this->lng->txt("cont_credit_on"),
			"no_credit" => $this->lng->txt("cont_credit_off"));
		$si = new ilSelectInputGUI($this->lng->txt("cont_credit_mode"), "credit_mode");
		$si->setOptions($options);
		$si->setValue($this->object->getCreditMode());
		$si->setInfo($this->lng->txt("cont_credit_mode_info"));
		$this->form->addItem($si);
		
		// set lesson mode review when completed
		$options = array(
			"n" => $this->lng->txt("cont_sc_auto_review_no"),
//			"r" => $this->lng->txt("cont_sc_auto_review_completed_not_failed_or_passed"),
//			"p" => $this->lng->txt("cont_sc_auto_review_passed"),
//			"q" => $this->lng->txt("cont_sc_auto_review_passed_or_failed"),
//			"c" => $this->lng->txt("cont_sc_auto_review_completed"),
//			"d" => $this->lng->txt("cont_sc_auto_review_completed_and_passed"),
			"y" => $this->lng->txt("cont_sc_auto_review_completed_or_passed"),
			);
		$si = new ilSelectInputGUI($this->lng->txt("cont_sc_auto_review_2004"), "auto_review");
		$si->setOptions($options);
		$si->setValue($this->object->getAutoReviewChar());
		$si->setInfo($this->lng->txt("cont_sc_auto_review_info_12"));
		$this->form->addItem($si);

		//
		// rte settings
		//
		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->lng->txt("cont_rte_settings"));
		$this->form->addItem($sh);
		
		// unlimited session timeout
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_sc_usession"), "cobj_session");
		$cb->setValue("y");
		$cb->setChecked($this->object->getSession());
		$cb->setInfo($this->lng->txt("cont_sc_usession_info"));
		$this->form->addItem($cb);
		
		// storage of interactions
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_interactions"), "cobj_interactions");
		$cb->setValue("y");
		$cb->setChecked($this->object->getInteractions());
		$this->form->addItem($cb);
		
		// objectives
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_objectives"), "cobj_objectives");
		$cb->setValue("y");
		$cb->setChecked($this->object->getObjectives());
		$this->form->addItem($cb);

		// time from lms
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_time_from_lms"), "cobj_time_from_lms");
		$cb->setValue("y");
		$cb->setChecked($this->object->getTime_from_lms());
		$cb->setInfo($this->lng->txt("cont_time_from_lms_info"));
		$this->form->addItem($cb);

		// check values
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_check_values"), "cobj_check_values");
		$cb->setValue("y");
		$cb->setChecked($this->object->getCheck_values());
		$this->form->addItem($cb);
		// api adapter name
		// $this->tpl->setVariable("TXT_API_ADAPTER", $this->lng->txt("cont_api_adapter"));
		// $this->tpl->setVariable("VAL_API_ADAPTER", $this->object->getAPIAdapterName());
		// api functions prefix
		// $this->tpl->setVariable("TXT_API_PREFIX", $this->lng->txt("cont_api_func_prefix"));
		// $this->tpl->setVariable("VAL_API_PREFIX", $this->object->getAPIFunctionsPrefix());

		//
		// debugging
		//
		$sh = new ilFormSectionHeaderGUI();
		$sh->setTitle($this->lng->txt("cont_debugging"));
		$this->form->addItem($sh);

		// test tool
		$cb = new ilCheckboxInputGUI($this->lng->txt("cont_debug"), "cobj_debug");
		$cb->setValue("y");
		$cb->setChecked($this->object->getDebug());
		if ($this->object->getDebugActivated() == false)
		{
			$cb->setDisabled(true);
			$cb->setInfo($this->lng->txt("cont_debug_deactivated"));
		}
		else
		{
			$cb->setInfo($this->lng->txt("cont_debug_deactivate"));
		}
		$this->form->addItem($cb);
		$this->form->addCommandButton("saveProperties", $lng->txt("save"));

		$tpl->setContent($this->form->getHTML());

	}

	/**
	* upload new version of module
	*/
	function newModuleVersion()
	{
		$obj_id = ilObject::_lookupObjectId($_GET['ref_id']);
		$type = ilObjSAHSLearningModule::_lookupSubType($obj_id);

		// display import form
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.scorm_new_version_import.html", "Modules/ScormAicc");

		$this->tpl->setVariable("TYPE_IMG",ilUtil::getImagePath('icon_slm.png'));
		$this->tpl->setVariable("ALT_IMG", $this->lng->txt("obj_sahs"));

		$this->ctrl->setParameter($this, "new_type", "sahs");
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

		$this->tpl->setVariable("BTN_NAME", "newModuleVersionUpload");
		$this->tpl->setVariable("TARGET", ' target="'.
		ilFrameTargetInfo::_getFrame("MainContent").'" ');

		$this->tpl->setVariable("TXT_SELECT_LMTYPE", $this->lng->txt("type"));

		if ($type == "scorm2004") {
			$this->tpl->setVariable("TXT_TYPE", $this->lng->txt("lm_type_scorm2004"));
		} else {
			$this->tpl->setVariable("TXT_TYPE", $this->lng->txt("lm_type_scorm"));
		}

		include_once 'Services/FileSystem/classes/class.ilUploadFiles.php';
		if (ilUploadFiles::_getUploadDirectory())
		{
			$files = ilUploadFiles::_getUploadFiles();
			foreach($files as $file)
			{
				$file = htmlspecialchars($file, ENT_QUOTES, "utf-8");
				$this->tpl->setCurrentBlock("option_uploaded_file");
				$this->tpl->setVariable("UPLOADED_FILENAME", $file);
				$this->tpl->setVariable("TXT_UPLOADED_FILENAME", $file);
				$this->tpl->parseCurrentBlock();
			}
			$this->tpl->setCurrentBlock("select_uploaded_file");
			$this->tpl->setVariable("TXT_SELECT_FROM_UPLOAD_DIR", $this->lng->txt("cont_select_from_upload_dir"));
			$this->tpl->setVariable("TXT_UPLOADED_FILE", $this->lng->txt("cont_uploaded_file"));
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setVariable("TXT_UPLOAD", $this->lng->txt("upload"));
		$this->tpl->setVariable("TXT_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->setVariable("TXT_IMPORT_LM", $this->lng->txt("import_sahs"));
		$this->tpl->setVariable("TXT_SELECT_FILE", $this->lng->txt("select_file"));

		// gives out the limit as a little notice
		$this->tpl->setVariable("TXT_FILE_INFO", $this->lng->txt("file_notice")." ".$this->getMaxFileSize());
	}


	function getMaxFileSize()
	{
		// get the value for the maximal uploadable filesize from the php.ini (if available)
		$umf=get_cfg_var("upload_max_filesize");
		// get the value for the maximal post data from the php.ini (if available)
		$pms=get_cfg_var("post_max_size");
     
		//convert from short-string representation to "real" bytes
		$multiplier_a=array("K"=>1024, "M"=>1024*1024, "G"=>1024*1024*1024);

		$umf_parts=preg_split("/(\d+)([K|G|M])/", $umf, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
		$pms_parts=preg_split("/(\d+)([K|G|M])/", $pms, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

		if (count($umf_parts) == 2) { $umf = $umf_parts[0]*$multiplier_a[$umf_parts[1]]; }
		if (count($pms_parts) == 2) { $pms = $pms_parts[0]*$multiplier_a[$pms_parts[1]]; }
     
		// use the smaller one as limit
		$max_filesize=min($umf, $pms);

		if (!$max_filesize) $max_filesize=max($umf, $pms);

		//format for display in mega-bytes
		return $max_filesize=sprintf("%.1f MB",$max_filesize/1024/1024);
	}
	
	
	function newModuleVersionUpload()
	{
		global $_FILES, $rbacsystem;

		$unzip = PATH_TO_UNZIP;
		$tocheck = "imsmanifest.xml";
		
		include_once 'Services/FileSystem/classes/class.ilUploadFiles.php';

		// check create permission before because the uploaded file will be copied
		if (!$rbacsystem->checkAccess("write", $_GET["ref_id"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_create_permission"), $this->ilias->error_obj->WARNING);
		}
		elseif ($_FILES["scormfile"]["name"])
		{
			// check if file was uploaded
			$source = $_FILES["scormfile"]["tmp_name"];
			if (($source == 'none') || (!$source))
			{
				ilUtil::sendInfo($this->lng->txt("upload_error_file_not_found"),true);
				$this->newModuleVersion();
				return;
			}
		}
		elseif ($_POST["uploaded_file"])
		{
			// check if the file is in the ftp directory and readable
 			if (!ilUploadFiles::_checkUploadFile($_POST["uploaded_file"]))
			{
				$this->ilias->raiseError($this->lng->txt("upload_error_file_not_found"),$this->ilias->error_obj->MESSAGE);
			}
			// copy the uploaded file to the client web dir to analyze the imsmanifest
			// the copy will be moved to the lm directory or deleted
 			$source = CLIENT_WEB_DIR . "/" . $_POST["uploaded_file"];
			ilUploadFiles::_copyUploadFile($_POST["uploaded_file"], $source);
			$source_is_copy = true;
		}
		else
		{
			ilUtil::sendInfo($this->lng->txt("upload_error_file_not_found"),true);
			$this->newModuleVersion();
			return;
		}
		// fim.

		//unzip the imsmanifest-file from new uploaded file
		$pathinfo = pathinfo($source);
		$dir = $pathinfo["dirname"];
		$file = $pathinfo["basename"];
		$cdir = getcwd();
		chdir($dir);

		//we need more flexible unzip here than ILIAS standard classes allow
		$unzipcmd = $unzip." -o ".ilUtil::escapeShellArg($source)." ".$tocheck;
		exec($unzipcmd);
		chdir($cdir);
		$tmp_file = $dir."/".$tocheck.".".$_GET["ref_id"];

		rename($dir."/".$tocheck,$tmp_file);
		$new_manifest = file_get_contents($tmp_file);

		//remove temp file
		unlink($tmp_file);

		//get old manifest file	
		$old_manifest = file_get_contents($this->object->getDataDirectory()."/".$tocheck);

		//reload fixed version of file
		$check ='/xmlns="http:\/\/www.imsglobal.org\/xsd\/imscp_v1p1"/';
		$replace="xmlns=\"http://www.imsproject.org/xsd/imscp_rootv1p1p2\"";
		$reload_manifest = preg_replace($check, $replace, $new_manifest);

		//do testing for converted versions as well as earlier ILIAS version messed up utf8 conversion
		if (strcmp($new_manifest,$old_manifest) == 0 || strcmp(utf8_encode($new_manifest),$old_manifest) == 0 ||
			strcmp ($reload_manifest, $old_manifest) == 0 || strcmp(utf8_encode($reload_manifest),$old_manifest) == 0 ){

			//get exisiting module version
			$module_version = $this->object->getModuleVersion();

			if ($_FILES["scormfile"]["name"])
			{
				//build targetdir in lm_data
				$file_path = $this->object->getDataDirectory()."/".$_FILES["scormfile"]["name"].".".$module_version;
				
				//move to data directory and add subfix for versioning
				ilUtil::moveUploadedFile($_FILES["scormfile"]["tmp_name"],$_FILES["scormfile"]["name"], $file_path);
			}
			else
			{
				//build targetdir in lm_data
				$file_path = $this->object->getDataDirectory()."/".$_POST["uploaded_file"].".".$module_version;
				// move the already copied file to the lm_data directory
				rename($source, $file_path);
			}
			
			//unzip and replace old extracted files
			ilUtil::unzip($file_path, true);
			ilUtil::renameExecutables($this->object->getDataDirectory()); //(security)
			
			//increase module version
			$this->object->setModuleVersion($module_version+1);
			$this->object->update();
			
			//redirect to properties and display success
			ilUtil::sendInfo( $this->lng->txt("cont_new_module_added"), true);
			ilUtil::redirect("ilias.php?baseClass=ilSAHSEditGUI&ref_id=".$_GET["ref_id"]);
			exit;
		}
		else
		{
			if ($source_is_copy)
			{
				unlink($source);
			}
			
			ilUtil::sendInfo($this->lng->txt("cont_invalid_new_module"),true);
			$this->newModuleVersion();
		}
	}

	/**
	* save properties
	*/
	function saveProperties()
	{
		//check if OfflineMode-Zip has to be created
		$tmpOfflineMode= ilUtil::yn2tf($_POST["cobj_offline_mode"]);
		if ($tmpOfflineMode == true) {
			if ($this->object->getOfflineMode() == false) {
				$this->object->zipLmForOfflineMode();
			}
		}
		$this->object->setOnline(ilUtil::yn2tf($_POST["cobj_online"]));
		$this->object->setOfflineMode($tmpOfflineMode);
		$this->object->setOpenMode($_POST["open_mode"]);
		$this->object->setWidth($_POST["width"]);
		$this->object->setHeight($_POST["height"]);
		$this->object->setAuto_last_visited(ilUtil::yn2tf($_POST["cobj_auto_last_visited"]));
		$this->object->setAutoContinue(ilUtil::yn2tf($_POST["auto_continue"]));
		$this->object->setMaxAttempt($_POST["max_attempt"]);
		$this->object->setDefaultLessonMode($_POST["lesson_mode"]);
		$this->object->setCreditMode($_POST["credit_mode"]);
		$this->object->setAutoReview(ilUtil::yn2tf($_POST["auto_review"]));
//		$this->object->setAPIAdapterName($_POST["api_adapter"]);
//		$this->object->setAPIFunctionsPrefix($_POST["api_func_prefix"]);
		$this->object->setSession(ilUtil::yn2tf($_POST["cobj_session"]));
		$this->object->setInteractions(ilUtil::yn2tf($_POST["cobj_interactions"]));
		$this->object->setObjectives(ilUtil::yn2tf($_POST["cobj_objectives"]));
		$this->object->setTime_from_lms(ilUtil::yn2tf($_POST["cobj_time_from_lms"]));
		$this->object->setCheck_values(ilUtil::yn2tf($_POST["cobj_check_values"]));
		$this->object->setDebug(ilUtil::yn2tf($_POST["cobj_debug"]));
		$this->object->update();
		ilUtil::sendInfo($this->lng->txt("msg_obj_modified"), true);
		$this->ctrl->redirect($this, "properties");
	}

	/**
	 * show tracking data
	 */
	protected function showTrackingItemsBySco()
	{
		global $ilTabs;

		include_once "./Services/Table/classes/class.ilTableGUI.php";

		$this->setSubTabs();
		$ilTabs->setTabActive("cont_tracking_data");
		$ilTabs->setSubTabActive("cont_tracking_bysco");

		include_once './Modules/ScormAicc/classes/class.ilSCORMTrackingItemsPerScoTableGUI.php';
		$tbl = new ilSCORMTrackingItemsPerScoTableGUI($this->object->getId(), $this, 'showTrackingItemsBySco');
		$tbl->parse();
		$this->tpl->setContent($tbl->getHTML());
		return true;
	}


	/**
	 * Show tracking table
	 * @global ilTabs $ilTabs
	 * $global ilToolbar $ilToolbar
	 */
	protected function showTrackingItems()
	{
		include_once('./Services/PrivacySecurity/classes/class.ilPrivacySettings.php');
		$privacy = ilPrivacySettings::_getInstance();
		if(!$privacy->enabledSahsProtocolData())
		{
			$this->ilias->raiseError($this->lng->txt('permission_denied'), $this->ilias->error_obj->MESSAGE);
		}

		global $ilTabs, $ilToolbar;

		include_once './Services/UIComponent/Toolbar/classes/class.ilToolbarGUI.php';
		$ilToolbar->addButton(
			$this->lng->txt('import'),
			$this->ctrl->getLinkTarget($this, 'importForm')
		);
		$ilToolbar->addButton(
			$this->lng->txt('cont_export_all'),
			$this->ctrl->getLinkTarget($this, 'exportSelectionAll')
		);

		$this->setSubTabs();
		$ilTabs->setTabActive('cont_tracking_data');
		$ilTabs->setSubTabActive('cont_tracking_byuser');

		include_once './Modules/ScormAicc/classes/class.ilSCORMTrackingUsersTableGUI.php';
		$tbl = new ilSCORMTrackingUsersTableGUI($this->object->getId(), $this, 'showtrackingItems');
		$tbl->parse();
		$this->tpl->setContent($tbl->getHTML());
	}

	
	/**
	 * Apply table filter
	 */
	protected function applyUserTableFilter()
	{
		include_once './Modules/ScormAicc/classes/class.ilSCORMTrackingUsersTableGUI.php';
		$tbl = new ilSCORMTrackingUsersTableGUI($this->object->getId(), $this, 'showtrackingItems');
		$tbl->writeFilterToSession();
		$tbl->resetOffset();
		$this->showTrackingItems();
	}

	/**
	 * Reset table filter
	 */
	protected function resetUserTableFilter()
	{
		include_once './Modules/ScormAicc/classes/class.ilSCORMTrackingUsersTableGUI.php';
		$tbl = new ilSCORMTrackingUsersTableGUI($this->object->getId(), $this, 'showtrackingItems');
		$tbl->resetFilter();
		$tbl->resetOffset();
		$this->showTrackingItems();
	}

	/**
	 * display deletion confirmation screen
	 */
	function deleteTrackingForUser()
	{
		if(!isset($_POST["user"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}
	
		// display confirmation message
		include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
		$cgui = new ilConfirmationGUI();
		$cgui->setFormAction($this->ctrl->getFormAction($this));
		$cgui->setHeaderText($this->lng->txt("info_delete_sure"));
		$cgui->setCancel($this->lng->txt("cancel"), "cancelDelete");
		$cgui->setConfirm($this->lng->txt("confirm"), "confirmedDelete");

		foreach($_POST["user"] as $id)
		{
			if (ilObject::_exists($id) && ilObject::_lookUpType($id)=="usr" )
			{
				$user = new ilObjUser($id);

				$caption = ilUtil::getImageTagByType("sahs", $this->tpl->tplPath).
					" ".$this->lng->txt("cont_tracking_data").
					": ".$user->getLastname().", ".$user->getFirstname();


				$cgui->addItem("user[]", $id, $caption);
			}
		}

		$this->tpl->setContent($cgui->getHTML());
	}

	/**
	 * cancel deletion of export files
	 */
	function cancelDelete()
	{
		ilUtil::sendInfo($this->lng->txt("msg_cancel"), true);
		$this->ctrl->redirect($this, "showTrackingItems");
	}

	function confirmedDelete()
	{
		global $ilDB;
		
		include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");

		foreach($_POST["user"] as $user)
		{
			$ilDB->manipulateF('
				DELETE FROM scorm_tracking
				WHERE user_id = %s
				AND obj_id = %s',
				array('integer', 'integer'),
				array($user, $this->object->getID()));

			$ilDB->manipulateF('
				DELETE FROM sahs_user
				WHERE user_id = %s
				AND obj_id = %s',
				array('integer', 'integer'),
				array($user, $this->object->getID()));

				ilLPStatusWrapper::_updateStatus($this->object->getId(), $user);
		}
			
		$this->ctrl->redirect($this, "showTrackingItems");
	}

	/**
	 * overwrite..jump back to trackingdata not parent
	 */
	function cancel()
	{
		ilUtil::sendInfo($this->lng->txt("msg_cancel"), true);
		$this->ctrl->redirect($this, "properties");
	}

	/**
	 * gui functions for GUI export
	 */
	protected function import()
	{
		$form = $this->initImportForm();
		if($form->checkInput())
		{
			$source = $form->getInput('csv');
			$error = $this->object->importTrackingData($source['tmp_name']);
			switch($error)
			{
				case 0 :
					ilUtil::sendInfo('Tracking data imported', true);
					$this->ctrl->redirect($this, "showTrackingItems");
					break;
				case -1 :
					ilUtil::sendInfo($this->lng->txt('err_check_input'));
					$this->importForm();
					break;
			}
		}
		ilUtil::sendInfo($this->lng->txt('err_check_input'));
		$form->setValuesByPost();
		$this->importForm();
	}

	/**
	 * Show import form
	 */
	protected function importForm()
	{
		global $ilTabs;

		$ilTabs->clearTargets();
		$ilTabs->setBackTarget($this->lng->txt('back'),$this->ctrl->getLinkTarget($this,'showTrackingItems'));

		$form = $this->initImportForm();
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * Init import form
	 */
	protected function initImportForm()
	{
		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->setTitle($this->lng->txt('cont_import_tracking'));
		$form->addCommandButton('import', $this->lng->txt('import'));
		$form->addCommandButton('showTrackingItems', $this->lng->txt('cancel'));

		$csv = new ilFileInputGUI($this->lng->txt('select_file'),'csv');
		$csv->setRequired(true);
		$csv->setSuffixes(array('csv'));
		$form->addItem($csv);

		return $form;
	}

	/**
	 * Show export section for all users
	 */
	protected function exportSelectionAll()
	{
		$this->exportSelection(self::EXPORT_ALL);
	}

	/**
	 * Export selection for selected users
	 */
	protected function exportSelectionUsers()
	{
		if(!count((array) $_POST['user']))
		{
			ilUtil::sendFailure($this->lng->txt('select_one'),true);
			$this->ctrl->redirect($this,'showTrackingItems');
		}

		$this->exportSelection(self::EXPORT_SELECTED);
	}

	/**
	 * Show export selection
	 * @param int $a_type
	 */
	protected function exportSelection($a_type)
	{
		global $ilTabs;

		$ilTabs->clearTargets();
		$ilTabs->setBackTarget(
			$this->lng->txt('back'),
			$this->ctrl->getLinkTarget($this,'showTrackingItems')
		);

		$form = $this->initExportForm($a_type);
		$this->tpl->setContent($form->getHTML());
	}
	
	/**
	 * Init export form
	 * @param int $a_type 
	 */
	protected function initExportForm($a_type)
	{
		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';

		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this,'showTrackingItems'));
		$form->setTitle($this->lng->txt('cont_export_tracking'));
		$form->addCommandButton('export', $this->lng->txt('export'));
		$form->addCommandButton('showTrackingItems', $this->lng->txt('cancel'));
		
		$type = new ilRadioGroupInputGUI($this->lng->txt('cont_export_type'), 'type');
		$type->setRequired(true);
		$type->setValue(self::EXPORT_TYPE_RAW);
		$form->addItem($type);
		
		$raw = new ilRadioOption($this->lng->txt('cont_export_raw'), self::EXPORT_TYPE_RAW);
		$type->addOption($raw);

		$suc = new ilRadioOption($this->lng->txt('cont_export_success'), self::EXPORT_TYPE_SUCCESS);
		$type->addOption($suc);

		$etype = new ilHiddenInputGUI('etype');
		$etype->setValue($a_type);
		$form->addItem($etype);

		switch($a_type)
		{
			case self::EXPORT_SELECTED:
				$users = new ilHiddenInputGUI('users');
				$users->setValue(htmlentities(serialize($_POST['user'])));
				$form->addItem($users);
				break;
		}
		return $form;
	}
	
	
	/**
	 * Do export
	 */
	protected function export()
	{
		$form = $this->initExportForm(self::EXPORT_UNDEF);
		if($form->checkInput())
		{
			if($form->getInput('type') == self::EXPORT_TYPE_RAW)
			{
				if($form->getInput('etype') == self::EXPORT_ALL)
				{
					return $this->object->exportSelectedRaw(true);
				}
				else
				{
					$users = (array) unserialize(html_entity_decode($form->getInput('users')));
					return $this->object->exportSelectedRaw(false,$users);
				}
			}
			else
			{
				if($form->getInput('etype') == self::EXPORT_ALL)
				{
					return $this->object->exportSelected(true);
				}
				else
				{
					$users = (array) unserialize(html_entity_decode($form->getInput('users')));
					return $this->object->exportSelected(false,$users);
				}
			}
		}
		ilUtil::sendFailure($this->lng->txt('err_check_input'));
		$this->ctrl->redirect($this,'showTrackingItems');
	}

	function decreaseAttempts()
	{
		global $ilDB, $ilUser;
		if (!isset($_POST["user"]))
		{
			ilUtil::sendInfo($this->lng->txt("no_checkbox"),true);
		}
		
		foreach ($_POST["user"] as $user)
		{
			//first check if there is a package_attempts entry
			$val_set = $ilDB->queryF('SELECT package_attempts FROM sahs_user WHERE user_id = %s AND obj_id = %s',
			array('integer','integer'),
			array($user,$this->object->getID()));
			
			$val_rec = $ilDB->fetchAssoc($val_set);
			
			if ($val_rec["package_attempts"] != null && $val_rec["package_attempts"] != 0) 
			{
				$new_rec = 0;
				//decrease attempt by 1
				if ((int)$val_rec["package_attempts"] > 0) $new_rec = (int)$val_rec["package_attempts"]-1;
				$ilDB->manipulateF('UPDATE sahs_user SET package_attempts = %s WHERE user_id = %s AND obj_id = %s',
					array('integer','integer','integer'),
					array($new_rec,$user,$this->object->getID()));

				//following 2 lines were before 4.4 only for SCORM 1.2
				include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
				ilLPStatusWrapper::_updateStatus($this->object->getId(), $user);
			}
		}

		//$this->ctrl->saveParameter($this, "cdir");
		$this->ctrl->redirect($this, "showTrackingItems");
	}
	
	
	/**
	 * show tracking data of item
	 */
	protected function showTrackingItem()
	{
		global $ilTabs;

		include_once "./Services/Table/classes/class.ilTableGUI.php";

		$this->setSubTabs();
		$ilTabs->setTabActive("cont_tracking_data");
		$ilTabs->setSubTabActive("cont_tracking_byuser");

		include_once './Modules/ScormAicc/classes/class.ilSCORMTrackingItemsPerUserTableGUI.php';
		$tbl = new ilSCORMTrackingItemsPerUserTableGUI($this->object->getId(), $this, 'showTrackingItem');
		$tbl->setUserId((int) $_REQUEST['user_id']);
		$tbl->parse();
		$this->tpl->setContent($tbl->getHTML());
		return true;
	}

	/**
	 * show tracking data of item
	 */
	protected function showTrackingItemSco()
	{
		global $ilTabs;

		include_once "./Services/Table/classes/class.ilTableGUI.php";

		$this->setSubTabs();
		$ilTabs->setTabActive("cont_tracking_data");
		$ilTabs->setSubTabActive("cont_tracking_bysco");

		include_once './Modules/ScormAicc/classes/class.ilSCORMTrackingItemsScoTableGUI.php';
		$tbl = new ilSCORMTrackingItemsScoTableGUI($this->object->getId(), $this, 'showTrackingItemSco');
		$tbl->setScoId((int) $_GET['obj_id']);
		$tbl->parse();
		$this->tpl->setContent($tbl->getHTML());
		return true;
	}

	/**
	 * show tracking data of item per user
	 */
	protected function showTrackingItemPerUser()
	{
		global $ilTabs;

		include_once "./Services/Table/classes/class.ilTableGUI.php";

		$this->setSubTabs();
		$ilTabs->setTabActive("cont_tracking_data");
		$ilTabs->setSubTabActive("cont_tracking_byuser");

		$this->ctrl->setParameter($this,'obj_id',(int) $_REQUEST['obj_id']);
		$this->ctrl->setParameter($this,'user_id',(int) $_REQUEST['user_id']);

		include_once './Modules/ScormAicc/classes/class.ilSCORMTrackingItemPerUserTableGUI.php';
		$tbl = new ilSCORMTrackingItemPerUserTableGUI($this->object->getId(), $this, 'showTrackingItemPerUser');
		$tbl->setUserId((int) $_REQUEST['user_id']);
		$tbl->setScoId((int) $_REQUEST['obj_id']);
		$tbl->parse();
		$this->tpl->setContent($tbl->getHTML());
		return true;
	}

	//setTabs
	function setSubTabs()
	{
		global $lng, $ilTabs, $ilCtrl;

		$ilTabs->addSubTabTarget("cont_tracking_byuser",
			$this->ctrl->getLinkTarget($this, "showTrackingItems"), array("edit", ""),
			get_class($this));

		$ilTabs->addSubTabTarget("cont_tracking_bysco",
			$this->ctrl->getLinkTarget($this, "showTrackingItemsBySco"), array("edit", ""),
			get_class($this));
	}

	/**
	 * Manage offline mode for users
	 * @global ilTabs $ilTabs
	 * $global ilToolbar $ilToolbar
	 */
	protected function offlineModeManager()
	{
		global $rbacsystem, $tree, $tpl, $lng, $ilToolbar, $ilCtrl, $ilSetting;

		include_once './Services/Tracking/classes/class.ilLearningProgressAccess.php';
		if(!ilLearningProgressAccess::checkAccess($this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt('permission_denied'), $this->ilias->error_obj->MESSAGE);
		}

		include_once './Modules/ScormAicc/classes/class.ilSCORMOfflineModeUsersTableGUI.php';
		$tbl = new ilSCORMOfflineModeUsersTableGUI($this->object->getId(), $this, 'offlineModeManager');
		$tbl->parse();
		$this->tpl->setContent($tbl->getHTML());

	}
	/**
	 * Stop offline mode for selected users
	 */
	protected function stopUserOfflineMode()
	{
		if(!count((array) $_POST['user']))
		{
			ilUtil::sendFailure($this->lng->txt('select_one'),true);
			$this->ctrl->redirect($this,'offlineModeManager');
		}
		// display confirmation message
		include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
		$cgui = new ilConfirmationGUI();
		$cgui->setFormAction($this->ctrl->getFormAction($this));
		$cgui->setHeaderText($this->lng->txt("info_stop_offline_mode_sure"));
		$cgui->setCancel($this->lng->txt("cancel"), "cancelStopUserOfflineMode");
		$cgui->setConfirm($this->lng->txt("confirm"), "confirmedStopUserOfflineMode");
		foreach($_POST["user"] as $id)
		{
			if (ilObject::_exists($id) && ilObject::_lookUpType($id)=="usr" )
			{
				$user = new ilObjUser($id);
				$caption = ilUtil::getImageTagByType("sahs_offline", $this->tpl->tplPath).
					" ".$this->lng->txt("stop_user_offline_mode_for_user").
					": ".$user->getLastname().", ".$user->getFirstname();
				$cgui->addItem("user[]", $id, $caption);
			}
		}
		$this->tpl->setContent($cgui->getHTML());
	}

	function cancelStopUserOfflineMode()
	{
		ilUtil::sendInfo($this->lng->txt("msg_cancel"), true);
		$this->ctrl->redirect($this, "offlineModeManager");
	}

	function confirmedStopUserOfflineMode()
	{

		include_once './Modules/ScormAicc/classes/class.ilSCORMOfflineMode.php';
		foreach($_POST["user"] as $id)
		{
			ilSCORMOfflineMode::stopOfflineModeForUser($this->object->getId(),$id);
		}

		$this->offlineModeManager();
	}


}
// END class.ilObjSCORMLearningModule
?>
