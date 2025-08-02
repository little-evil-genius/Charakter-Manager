<?php
/**
 * Charakter-Manager  - by little.evil.genius
 * https://github.com/little-evil-genius/Charakter-Manager
 * https://storming-gates.de/member.php?action=profile&uid=1712
 * 
 * Dieses Plugin erweitert das User-CP um eine Übersicht aller eigenen Charaktere auf einer separaten Seite. 
 * Es ermöglicht das einfache Erstellen neuer Charaktere/Accounts direkt im UCP und bietet zusätzlich die Option, 
 * Profilfelder zu sichern.
 * 
 * PDF EXPORT:
 * CREDITS to https://tcpdf.org/
 * and https://www.php-einfach.de/experte/php-codebeispiele/pdf-per-php-erstellen-pdf-rechnung/ 
*/

// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// HOOKS
$plugins->add_hook("admin_config_settings_change", "character_manager_settings_change");
$plugins->add_hook("admin_settings_print_peekers", "character_manager_settings_peek");
$plugins->add_hook("admin_rpgstuff_action_handler", "character_manager_admin_rpgstuff_action_handler");
$plugins->add_hook("admin_rpgstuff_permissions", "character_manager_admin_rpgstuff_permissions");
$plugins->add_hook("admin_rpgstuff_menu", "character_manager_admin_rpgstuff_menu");
$plugins->add_hook("admin_load", "character_manager_admin_manage");
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'character_manager_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'character_manager_admin_update_plugin');
$plugins->add_hook('usercp_menu', 'character_manager_usercp_menu', 40);
$plugins->add_hook('usercp_start', 'character_manager_usercp');
$plugins->add_hook('global_intermediate', 'character_manager_global');
$plugins->add_hook('member_profile_end', 'character_manager_memberprofile');
$plugins->add_hook("fetch_wol_activity_end", "character_manager_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "character_manager_online_location");
$plugins->add_hook("admin_user_users_delete_commit_end", "character_manager_user_delete");

// Die Informationen, die im Pluginmanager angezeigt werden
function character_manager_info(){
	return array(
		"name"		=> "Charakter-Manager",
		"description"	=> "Fügt dem User-CP eine Übersicht eigener Charaktere hinzu, ermöglicht das Erstellen neuer Accounts und das Sichern von Profilfeldern.",
		"website"	=> "https://github.com/little-evil-genius/Charakter-Manager",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0.2",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function character_manager_install(){
    
    global $db, $lang;

    // SPRACHDATEI
    $lang->load("character_manager");

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message($lang->character_manager_error_accountswitcher, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message($lang->character_manager_error_rpgstuff, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANKTABELLEN UND FELDER
    character_manager_database();

    // EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
    $setting_group = array(
        'name'          => 'character_manager',
        'title'         => 'Charakter-Manager',
        'description'   => 'Einstellungen für den Charakter-Manager',
        'disporder'     => $maxdisporder+1,
        'isdefault'     => 0
    );
    $db->insert_query("settinggroups", $setting_group);

    // Einstellungen
    character_manager_settings();
    rebuild_settings();

    // TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "charactermanager",
        "title" => $db->escape_string("Charakter-Manager"),
    );
    $db->insert_query("templategroups", $templategroup);
    // Templates 
    character_manager_templates();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $stylesheet = character_manager_stylesheet();
    $sid = $db->insert_query('themestylesheets', $stylesheet);
    cache_stylesheet(1, "character_manager.css", $stylesheet['stylesheet']);
    update_theme_stylesheet_list("1");
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function character_manager_is_installed(){

    global $db;

    if ($db->table_exists("character_manager")) {
        return true;
    }
    return false;
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function character_manager_uninstall(){
    
	global $db, $cache;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("character_manager"))
    {
        $db->drop_table("character_manager");
    }
    if($db->table_exists("character_manager_fields"))
    {
        $db->drop_table("character_manager_fields");
    }

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'charactermanager'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'charactermanager%'");
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'character_manager%'");
    $db->delete_query('settinggroups', "name = 'character_manager'");
    rebuild_settings();

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'character_manager.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
        
	}
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function character_manager_activate(){
    
    global $db, $cache;
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    // VARIABLEN EINFÜGEN
    find_replace_templatesets('header', '#'.preg_quote('{$bbclosedwarning}').'#', '{$bbclosedwarning}{$character_manager_banner}');
    find_replace_templatesets('member_profile', '#'.preg_quote('({$usertitle})').'#', '({$usertitle}) {$character_manager_exportLink}');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function character_manager_deactivate(){
    
    global $db, $cache;
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
	find_replace_templatesets("header", "#".preg_quote('{$character_manager_banner}')."#i", '', 0);
	find_replace_templatesets("member_profile", "#".preg_quote('{$character_manager_exportLink}')."#i", '', 0);
}

######################
### HOOK FUNCTIONS ###
######################

// EINSTELLUNGEN VERSTECKEN
function character_manager_settings_change(){
    
    global $db, $mybb, $character_manager_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='character_manager'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $character_manager_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function character_manager_settings_peek(&$peekers){

    global $character_manager_settings_peeker;

    if ($character_manager_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_character_manager_required"), $("#row_setting_character_manager_required_fields"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_character_manager_adopt"), $("#row_setting_character_manager_adopt_fields"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_character_manager_export"), $("#row_setting_character_manager_export_fields"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_character_manager_ideas"), $("#row_setting_character_manager_ideas_reminder, #row_setting_character_manager_ideas_puplic, #row_setting_character_manager_ideas_puplic_forum"),/1/,true)';
        $peekers[] = 'new Peeker($(".setting_character_manager_ideas_puplic"), $("#row_setting_character_manager_ideas_puplic_forum"),/1/,true)';
    }
}

// ADMIN BEREICH - KONFIGURATION //
// action handler fürs acp konfigurieren
function character_manager_admin_rpgstuff_action_handler(&$actions) {
	$actions['character_manager'] = array('active' => 'character_manager', 'file' => 'character_manager');
}

// Benutzergruppen-Berechtigungen im ACP
function character_manager_admin_rpgstuff_permissions(&$admin_permissions) {

	global $lang, $mybb;
	
    $lang->load('character_manager');

    if ($mybb->settings['character_manager_ideas'] == 1){
        $admin_permissions['character_manager'] = $lang->character_manager_permission;
    }

	return $admin_permissions;
}

// im Menü einfügen
function character_manager_admin_rpgstuff_menu(&$sub_menu) {
    
	global $lang, $mybb;
	
    $lang->load('character_manager');

    if ($mybb->settings['character_manager_ideas'] == 1) {
        $sub_menu[] = [
            "id" => "character_manager",
            "title" => $lang->character_manager_nav,
            "link" => "index.php?module=rpgstuff-character_manager"
        ];
    }
}

// Felder erstellen
function character_manager_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache, $parser, $parser_array;

    if ($page->active_action != 'character_manager') {
		return false;
	}

    if ($run_module == 'rpgstuff' && $action_file == 'character_manager') {

        $lang->load('character_manager');

        $select_list = array(
            "text" => $lang->character_manager_type_text,
            "textarea" => $lang->character_manager_type_textarea,
            "select" => $lang->character_manager_type_select,
            "multiselect" => $lang->character_manager_type_multiselect,
            "radio" => $lang->character_manager_type_radio,
            "checkbox" => $lang->character_manager_type_checkbox,
            "date" => $lang->character_manager_type_date,
            "url" => $lang->character_manager_type_url
        );

        // Add to page navigation
		$page->add_breadcrumb_item($lang->character_manager_breadcrumb_main, "index.php?module=rpgstuff-character_manager");

		// ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

			if ($mybb->request_method == "post" && $mybb->get_input('do') == "save_sort") {

                if(!is_array($mybb->get_input('disporder', MyBB::INPUT_ARRAY))) {
                    flash_message($lang->character_manager_error_sort, 'error');
                    admin_redirect("index.php?module=rpgstuff-character_manager");
                }

                foreach($mybb->get_input('disporder', MyBB::INPUT_ARRAY) as $field_id => $order) {
        
                    $update_sort = array(
                        "disporder" => (int)$order    
                    );

                    $db->update_query("character_manager_fields", $update_sort, "cfid = '".(int)$field_id."'");
                }

                flash_message($lang->character_manager_overview_sort_flash, 'success');
                admin_redirect("index.php?module=rpgstuff-character_manager");
            }

			$page->output_header($lang->character_manager_overview_header);

			// Tabs bilden
            // Übersichtsseite Button
			$sub_tabs['overview'] = [
				"title" => $lang->character_manager_tabs_overview,
				"link" => "index.php?module=rpgstuff-character_manager",
				"description" => $lang->character_manager_tabs_overview_desc
			];
            // Neues Feld
            $sub_tabs['add'] = [
				"title" => $lang->character_manager_tabs_add,
				"link" => "index.php?module=rpgstuff-character_manager&amp;action=add"
			];

			$page->output_nav_tabs($sub_tabs, 'overview');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // Übersichtsseite
			$form = new Form("index.php?module=rpgstuff-character_manager", "post", "", 1);
            echo $form->generate_hidden_field("do", 'save_sort');
			$form_container = new FormContainer($lang->character_manager_overview_container);
            $form_container->output_row_header($lang->character_manager_overview_container_field, array('style' => 'text-align: left;'));
            $form_container->output_row_header($lang->character_manager_overview_container_sort, array('style' => 'text-align: center; width: 5%;'));
            $form_container->output_row_header($lang->character_manager_overview_container_options, array('style' => 'text-align: center; width: 10%;'));
			
            // Alle Felder
			$query_fields = $db->query("SELECT * FROM ".TABLE_PREFIX."character_manager_fields 
            ORDER BY disporder ASC, title ASC
            ");

            while ($field = $db->fetch_array($query_fields)) {

                // Title + Beschreibung
                $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-character_manager&amp;action=edit&amp;cfid='.$field['cfid'].'">'.$field['title'].'</a></strong> <small>'.$field['identification'].'</small><br><small>'.$field['description'].'</small>');

                // Sortierung
                $form_container->output_cell($form->generate_numeric_field("disporder[{$field['cfid']}]", $field['disporder'], array('style' => 'width: 80%; text-align: center;', 'min' => 0)), array("class" => "align_center"));

                // Optionen
				$popup = new PopupMenu("character_manager_".$field['cfid'], "Optionen");	
                $popup->add_item(
                    $lang->character_manager_overview_options_edit,
                    "index.php?module=rpgstuff-character_manager&amp;action=edit&amp;cfid=".$field['cfid']
                );
                $popup->add_item(
                    $lang->character_manager_overview_options_delete,
                    "index.php?module=rpgstuff-character_manager&amp;action=delete&amp;cfid=".$field['cfid']."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->character_manager_overview_options_delete_notice."')"
                );
                $form_container->output_cell($popup->fetch(), array('style' => 'text-align: center; width: 10%;'));

                $form_container->construct_row();
            }

			// keine Felder bisher
			if($db->num_rows($query_fields) == 0){
                $form_container->output_cell($lang->character_manager_overview_none, array("colspan" => 3, 'style' => 'text-align: center;'));
                $form_container->construct_row();
			}

            $form_container->end();
            
            $buttons = array($form->generate_submit_button($lang->character_manager_overview_sort_button));
            $form->output_submit_wrapper($buttons);

            $form->end();
            $page->output_footer();
			exit;
        }

		// NEUES FELD
		if ($mybb->get_input('action') == "add") {

			if ($mybb->request_method == "post") {

                if(empty($mybb->get_input('identification'))){
                    $errors[] = $lang->character_manager_error_identification;
                }
                if(empty($mybb->get_input('title'))){
                    $errors[] = $lang->character_manager_error_title;
                }
                if(empty($mybb->get_input('description'))) {
                    $errors[] = $lang->character_manager_error_description;
                }
                if(($mybb->get_input('fieldtype') == "select" AND $mybb->get_input('fieldtype') == "multiselect" AND $mybb->get_input('fieldtype') == "radio" AND $mybb->get_input('fieldtype') == "checkbox") AND empty($mybb->get_input('selectoptions'))) {
                    $errors[] = $lang->character_manager_error_selectoptions;
                }

                if(empty($errors)) {

                    $options = preg_replace("#(\r\n|\r|\n)#s", "\n", trim($mybb->get_input('selectoptions')));
                    if($mybb->get_input('fieldtype') != "text" AND $mybb->get_input('fieldtype') != "textarea")
                    {
                        $selectoptions = $options;
                    } else {
                        $selectoptions = "";
                    }

                    $insert_character_managerfield = array(
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "type" => $db->escape_string($mybb->get_input('fieldtype')),
                        "options" => $selectoptions,
                        "disporder" => (int)$mybb->get_input('disporder'),
                        "required" => (int)$mybb->get_input('required')
                    );
                    $cfid = $db->insert_query("character_manager_fields", $insert_character_managerfield);

                    if ($mybb->get_input('type') == "date") {
                        $fieldtype = "DATE";
                    } else  {
                        $fieldtype = "TEXT";
                    }
        
                    $db->write_query("ALTER TABLE ".TABLE_PREFIX."character_manager ADD {$db->escape_string($mybb->get_input('identification'))} {$fieldtype}");
        
                    // Log admin action
                    log_admin_action($cfid, $mybb->input['title']);
        
                    flash_message($lang->character_manager_add_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-character_manager");
                }
            }

            $page->add_breadcrumb_item($lang->character_manager_breadcrumb_add);
			$page->output_header($lang->character_manager_add_header);

			// Tabs bilden
            // Übersichtsseite Button
			$sub_tabs['overview'] = [
				"title" => $lang->character_manager_tabs_overview,
				"link" => "index.php?module=rpgstuff-character_manager"
			];
            // Neue Ankündigung
            $sub_tabs['add'] = [
				"title" => $lang->character_manager_tabs_add,
				"link" => "index.php?module=rpgstuff-character_manager&amp;action=add",
				"description" => $lang->character_manager_tabs_add_desc
			];

			$page->output_nav_tabs($sub_tabs, 'add');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}
    
            // Build the form
            $form = new Form("index.php?module=rpgstuff-character_manager&amp;action=add", "post", "", 1);

            $form_container = new FormContainer($lang->character_manager_add_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
    
            // Identifikator
            $form_container->output_row(
				$lang->character_manager_container_identification,
				$lang->character_manager_container_identification_desc,
				$form->generate_text_box('identification', htmlspecialchars_uni($mybb->get_input('identification')), array('id' => 'identification')), 'identification'
			);
    
            // Titel
            $form_container->output_row(
				$lang->character_manager_container_title,
                '',
				$form->generate_text_box('title', htmlspecialchars_uni($mybb->get_input('title')), array('id' => 'title')), 'title'
			);

            // Kurzbeschreibung
            $form_container->output_row(
				$lang->character_manager_container_description,
                '',
				$form->generate_text_box('description', htmlspecialchars_uni($mybb->get_input('description')), array('id' => 'description')), 'description'
			);

            // Feldtyp
            $form_container->output_row(
				$lang->character_manager_container_type, 
				$lang->character_manager_container_type_desc,
                $form->generate_select_box('fieldtype', $select_list, $mybb->get_input('fieldtype'), array('id' => 'fieldtype')), 'fieldtype'
            );    
    
            // Auswahlmöglichkeiten
            $form_container->output_row(
				$lang->character_manager_container_selectoptions, 
				$lang->character_manager_container_selectoptions_desc,
                $form->generate_text_area('selectoptions', $mybb->get_input('selectoptions')), 
                'selectoptions',
                array('id' => 'row_selectoptions')
			);

            // Sortierung
            $form_container->output_row(
				$lang->character_manager_container_disporder, 
				$lang->character_manager_container_disporder_desc,
                $form->generate_numeric_field('disporder', $mybb->get_input('disporder'), array('id' => 'disporder', 'min' => 0)), 'disporder'
			);

            // Pflichtfeld?
            $form_container->output_row(
                $lang->character_manager_container_require, 
                $lang->character_manager_container_require_desc, 
                $form->generate_yes_no_radio('required', $mybb->get_input('required'))
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->character_manager_add_button);
            $form->output_submit_wrapper($buttons);

            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
            <script type="text/javascript">
                $(function() {
                        new Peeker($("#fieldtype"), $("#row_parser_options"), /text|textarea/, false);
                        new Peeker($("#fieldtype"), $("#row_selectoptions"), /select|multiselect|radio|checkbox/, false);
                        // Add a star to the extra row since the "extra" is required if the box is shown
                        add_star("row_selectoptions");
                });
            </script>';

            $page->output_footer();
            exit;
        }

		// FELD BEARBEITEN
		if ($mybb->get_input('action') == "edit") {

            // Get the data
            $cfid = $mybb->get_input('cfid', MyBB::INPUT_INT);
            $charactermanagerfield_query = $db->simple_select("character_manager_fields", "*", "cfid = '".$cfid."'");
            $field = $db->fetch_array($charactermanagerfield_query);

            if ($mybb->request_method == "post") {

                if(empty($mybb->get_input('title'))){
                    $errors[] = $lang->character_manager_error_title;
                }
                if(empty($mybb->get_input('description'))) {
                    $errors[] = $lang->character_manager_error_description;
                }
                if(($mybb->get_input('fieldtype') == "select" AND $mybb->get_input('fieldtype') == "multiselect" AND $mybb->get_input('fieldtype') == "radio" AND $mybb->get_input('fieldtype') == "checkbox") AND empty($mybb->get_input('selectoptions'))) {
                    $errors[] = $lang->character_manager_error_selectoptions;
                }

                if(empty($errors)) {

                    $options = preg_replace("#(\r\n|\r|\n)#s", "\n", trim($mybb->get_input('selectoptions')));
                    if($mybb->get_input('fieldtype') != "text" AND $mybb->get_input('fieldtype') != "textarea")
                    {
                        $selectoptions = $options;
                    } else {
                        $selectoptions = "";
                    }

                    $update_charactermanagerfield = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "type" => $db->escape_string($mybb->get_input('fieldtype')),
                        "options" => $selectoptions,
                        "disporder" => (int)$mybb->get_input('disporder'),
                        "required" => (int)$mybb->get_input('required')
                    );
                    $db->update_query("character_manager_fields", $update_charactermanagerfield, "cfid='".$mybb->get_input('cfid')."'");

                    // Log admin action
                    log_admin_action($mybb->get_input('cfid'), $mybb->get_input('title'));
        
                    flash_message($lang->character_manager_edit_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-character_manager");
                }

            }

            $page->add_breadcrumb_item($lang->character_manager_breadcrumb_edit);
            $page->output_header($lang->character_manager_edit_header);

			// Tabs bilden
            // Neue Ankündigung
            $sub_tabs['edit'] = [
				"title" => $lang->character_manager_tabs_edit,
				"link" => "index.php?module=rpgstuff-character_manager&amp;action=edit&amp;cfid=".$cfid,
				"description" => $lang->character_manager_tabs_edit_desc
			];
			$page->output_nav_tabs($sub_tabs, 'edit');

            // Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$title = $mybb->get_input('title');
				$description = $mybb->get_input('description');
				$fieldtype = $mybb->get_input('fieldtype');
				$selectoptions = $mybb->get_input('selectoptions');
				$disporder = $mybb->get_input('disporder');
				$required = $mybb->get_input('required');
			} else {
				$title = $field['title'];
				$description = $field['description'];
				$fieldtype = $field['type'];
				$selectoptions = $field['options'];
				$disporder = $field['disporder'];
				$required = $field['required'];
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-character_manager&amp;action=edit", "post", "", 1);

            $form_container = new FormContainer($lang->sprintf($lang->character_manager_edit_container, $field['title']));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("cfid", $cfid);
    
            // Titel
            $form_container->output_row(
				$lang->character_manager_container_title,
                '',
				$form->generate_text_box('title', htmlspecialchars_uni($title), array('id' => 'title')), 'title'
			);

            // Kurzbeschreibung
            $form_container->output_row(
				$lang->character_manager_container_description,
                '',
				$form->generate_text_box('description', htmlspecialchars_uni($description), array('id' => 'description')), 'description'
			);

            // Feldtyp
            $form_container->output_row(
				$lang->character_manager_container_type, 
				$lang->character_manager_container_type_desc,
                $form->generate_select_box('fieldtype', $select_list, $fieldtype, array('id' => 'fieldtype')), 'fieldtype'
            );    
    
            // Auswahlmöglichkeiten
            $form_container->output_row(
				$lang->character_manager_container_selectoptions, 
				$lang->character_manager_container_selectoptions_desc,
                $form->generate_text_area('selectoptions', $selectoptions), 
                'selectoptions',
                array('id' => 'row_selectoptions')
			);

            // Sortierung
            $form_container->output_row(
				$lang->character_manager_container_disporder, 
				$lang->character_manager_container_disporder_desc,
                $form->generate_numeric_field('disporder', $disporder, array('id' => 'disporder', 'min' => 0)), 'disporder'
			);

            // Pflichtfeld?
            $form_container->output_row(
                $lang->character_manager_container_require, 
                $lang->character_manager_container_require_desc, 
                $form->generate_yes_no_radio('required', $required)
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->character_manager_edit_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
            <script type="text/javascript">
                $(function() {
                        new Peeker($("#fieldtype"), $("#row_parser_options"), /text|textarea/, false);
                        new Peeker($("#fieldtype"), $("#row_selectoptions"), /select|multiselect|radio|checkbox/, false);
                        // Add a star to the extra row since the "extra" is required if the box is shown
                        add_star("row_selectoptions");
                });
            </script>';

            $page->output_footer();
            exit;
        }

        // FELD LÖSCHEN
		if ($mybb->get_input('action') == "delete") {
            
            // Get the data
            $cfid = $mybb->get_input('cfid', MyBB::INPUT_INT);

			// Error Handling
			if (empty($cfid)) {
				flash_message($lang->character_manager_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-character_manager");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-character_manager");
			}

			if ($mybb->request_method == "post") {

                // Spalte löschen bei den Szenen löschen
                $identification = $db->fetch_field($db->simple_select("character_manager_fields", "identification", "cfid= '".$cfid."'"), "identification");
                if ($db->field_exists($identification, "character_manager")) {
                    $db->drop_column("character_manager", $identification);
                }

                // Feld in der Feld DB löschen
                $db->delete_query('character_manager_fields', "cfid = '".$cfid."'");

				flash_message($lang->character_manager_delete_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-character_manager");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-character_manager&amp;action=delete&amp;cfid=".$cfid,
					$lang->character_manager_overview_options_delete_notice
				);
			}
			exit;
        }
    }
}

// Stylesheet zum Master Style hinzufügen
function character_manager_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "character_manager") {

        $css = character_manager_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "character_manager.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Charakter-Manager")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'character_manager.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=character_manager\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function character_manager_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "character_manager") {

        // Einstellungen überprüfen => Type = update
        character_manager_settings('update');
        rebuild_settings();

        // Templates 
        character_manager_templates('update');

        // Stylesheet
        $update_data = character_manager_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'character_manager.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('character_manager.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        // Datenbanktabellen & Felder
        character_manager_database();

        // Collation prüfen und korrigieren
        $charset = 'utf8mb4';
        $collation = 'utf8mb4_unicode_ci';

        $collation_string = $db->build_create_table_collation();
        if (preg_match('/CHARACTER SET ([^\s]+)\s+COLLATE ([^\s]+)/i', $collation_string, $matches)) {
            $charset = $matches[1];
            $collation = $matches[2];
        }

        $databaseTables = [
            "character_manager_fields",
            "character_manager"
        ];

        foreach ($databaseTables as $databaseTable) {
            if ($db->table_exists($databaseTable)) {
                $table = TABLE_PREFIX.$databaseTable;

                $query = $db->query("SHOW TABLE STATUS LIKE '".$db->escape_string($table)."'");
                $table_status = $db->fetch_array($query);
                $actual_collation = strtolower($table_status['Collation'] ?? '');

                if (!empty($collation) && $actual_collation !== strtolower($collation)) {
                    $db->query("ALTER TABLE {$table} CONVERT TO CHARACTER SET {$charset} COLLATE {$collation}");
                }
            }
        }

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Charakter-Manager")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = character_manager_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=character_manager\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// USER-CP
// Menü
function character_manager_usercp_menu() {

	global $templates, $lang, $usercpmenu;

	// SPRACHDATEI LADEN
	$lang->load("character_manager");

	eval("\$usercpmenu .= \"".$templates->get("charactermanager_usercp_nav")."\";");
}

// Alle Seiten & Funktionen
function character_manager_usercp() {

	global $mybb, $db, $cache, $plugins, $page, $templates, $theme, $lang, $header, $headerinclude, $footer, $usercpnav, $characters_bit, $avatarUrl, $requiredfields, $thread, $threadmessage;

    // return if the action key isn't part of the input
    $allowed_actions = [
        'character_manager',
        'character_manager_registration',
        'do_charactermanager_registration',
        'character_manager_ideas_delete',
        'character_manager_ideas_edit',
        'character_manager_ideas_add',
        'do_charactermanager_ideas',
        'character_manager_pdf',
        'character_manager_ideas_extend'
    ];
    if (!in_array($mybb->get_input('action', MyBB::INPUT_STRING), $allowed_actions)) return;

	// SPRACHDATEI LADEN
	$lang->load("character_manager");

	// DAS ACTION MENÜ
	$mybb->input['action'] = $mybb->get_input('action');

	// USER-ID
	$userID = $mybb->user['uid'];

    // Übersicht
    if ($mybb->input['action'] == "character_manager") {

		add_breadcrumb($lang->nav_usercp, "usercp.php");
        add_breadcrumb($lang->character_manager_overview, "usercp.php?action=character_manager");

        $avatar_default = $mybb->settings['character_manager_avatar_default'];
        $export_setting = $mybb->settings['character_manager_export'];
        $ideas_setting = $mybb->settings['character_manager_ideas'];
        $ideas_puplic = $mybb->settings['character_manager_ideas_puplic'];

        $characters = character_manager_get_allchars($userID);

        $characters_bit = "";
        $character = [];
        foreach($characters as $characterUID => $charactername) {

            // Leer laufen lassen
            $avatarUrl = "";
            $characternameFormatted = "";
            $characternameLink = ""; 
            $characternameFormattedLink = "";
            $characternameFirst = "";
            $characternameLast = "";
            $exportLink = "";

            // Profilfelder & Users Tabelle
            $character = get_user($characterUID);
            $userfields_query = $db->simple_select("userfields", "*", "ufid = ".$characterUID);
            $userfields = $db->fetch_array($userfields_query);
            if (!is_array($userfields)) {
                $userfields = [];
            }
            $character = array_merge($character, $userfields);

            // Avatar
            if (!empty($character['avatar'])) {
                $avatarUrl = $character['avatar'];
            } else {
                $avatarUrl = $theme['imgdir']."/".$avatar_default;
            }

            // CHARACTER NAME
            // Nur Gruppenfarbe
            $characternameFormatted = format_name($charactername, $character['usergroup'], $character['displaygroup']);	
            // Nur Link
            $characternameLink = build_profile_link($charactername, $characterUID);
            // mit Gruppenfarbe + Link
            $characternameFormattedLink = build_profile_link(format_name($charactername, $character['usergroup'], $character['displaygroup']), $characterUID);	
            // Name gesplittet
            $fullname = explode(" ", $charactername);
            $characternameFirst = array_shift($fullname);
            $characternameLast = implode(" ", $fullname); 

            // Steckbrieffelder
            if ($db->table_exists("application_ucp_fields")) {
                if (!function_exists('application_ucp_build_view')) {
                    require_once MYBB_ROOT . 'inc/plugins/application_ucp.php';
                    $applicationfields = application_ucp_build_view($characterUID, "profile", "array");
                    if (!is_array($applicationfields)) {
                        $applicationfields = [];
                    }
                    $character = array_merge($character, $applicationfields);
                }
            }

            // Uploadsystem
            if ($db->table_exists("uploadsystem")) {
                if (!function_exists('uploadsystem_build_view')) {
                    require_once MYBB_ROOT . 'inc/plugins/uploadsystem.php';
                    $uploadfields = uploadsystem_build_view($characterUID);
                    if (!is_array($uploadfields)) {
                        $uploadfields = [];
                    }
                    $character = array_merge($character, $uploadfields);
                }
            }

            // Exportieren
            if ($export_setting == 1) {
                $exportLink = "<a href=\"usercp.php?action=character_manager_pdf&amp;uid=".$characterUID."\">".$lang->character_manager_export."</a>";
            } else {
                $exportLink = "";
            }
            
            eval("\$characters_bit .= \"".$templates->get("charactermanager_character")."\";");
        }

        // Charakter-Ideen
        if ($ideas_setting == 1) {

            $allUids = implode(",",array_keys($characters));
            $ideasQuery = $db->simple_select('character_manager', '*', 'uid IN ('.$allUids.')');

            $characteridea = [];
            $ideas_bit = "";
            while ($idea = $db->fetch_array($ideasQuery)) {

                // Leer laufen lassen
                $cid = "";
                $title = "";
                $reminder = "";
                $optionDel = "";  
                $optionEdit = ""; 
    
                // Mit Infos füllen
                $cid = $idea['cid'];
                $title = $idea['title'];
                $reminder = $idea['reminder'];

                $fields_query = $db->query("SELECT identification,title,type FROM " . TABLE_PREFIX . "character_manager_fields ORDER BY disporder ASC, title ASC");
         
                $fields = "";         
                while ($field = $db->fetch_array($fields_query)) {

                    // Leer laufen lassen
                    $identification = "";     
                    $fieldtitle = "";
                    $type = "";
    
                    // Mit Infos füllen
                    $identification = $field['identification'];
                    $fieldtitle = $field['title'];
                    $type = $field['type'];

                    if ($type == "multiselect" || $type == "checkbox") {
                        $valueEx = explode(",", $idea[$identification]);
                        $value = implode(" & ", $valueEx);
                    } else {
                        $value = $idea[$identification];
                    }

                    // Einzelne Variabeln    
                    $characteridea[$identification] = $value;

                    if (!empty($value)) {
                        eval("\$fields .= \"" . $templates->get("charactermanager_ideas_fields") . "\";");
                    }
                }

                // Optionen => löschen & bearbeiten
                $optionDel = "<a href=\"usercp.php?action=character_manager_ideas_delete&cid=".$cid."\" onClick=\"return confirm('".$lang->character_manager_ideas_delete_notice."');\">".$lang->character_manager_ideas_link_delete."</a>";
                $optionEdit = "<a href=\"usercp.php?action=character_manager_ideas_edit&cid=".$cid."\">".$lang->character_manager_ideas_link_edit."</a>";

                eval("\$ideas_bit .= \"".$templates->get("charactermanager_ideas_character")."\";");
            }

            if (empty($ideas_bit)) {
                $ideas_bit = $lang->character_manager_ideas_none;
            }

            eval("\$characters_ideas = \"".$templates->get("charactermanager_ideas")."\";");
        } else {
            $characters_ideas = "";
        }

		eval("\$page = \"".$templates->get("charactermanager")."\";");
		output_page($page);
		die();
    }

    // Charakteridee speichern
    if ($mybb->request_method == "post" && $mybb->input['action'] == "do_charactermanager_ideas") {

        if (empty($mybb->get_input('title'))) {
            $errors[] = $lang->sprintf($lang->character_manager_error_fields, $lang->character_manager_ideas_form_title);      
        }

        // Abfrage der Felder, die als erforderlich markiert sind
        $fields_query = $db->query("SELECT identification, title, type FROM ".TABLE_PREFIX."character_manager_fields WHERE required = 1");
        while ($field = $db->fetch_array($fields_query)) {
        
            if ($field['type'] == "multiselect" || $field['type'] == "checkbox") {
                $field_value = $mybb->get_input($field['identification'], MyBB::INPUT_ARRAY);
            } else {
                $field_value = $mybb->get_input($field['identification']);
            }

            if (empty($field_value)) {
                $errors[] = $lang->sprintf($lang->character_manager_error_fields, $field['title']);
            }
        }

        if (!empty($errors)) {
            $ideaserrors = inline_error($errors);

            $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
            if (!empty($cid)) {
                $mybb->input['action'] = "character_manager_ideas_edit";
            } else {
                $mybb->input['action'] = "character_manager_ideas_add";
            }
        } else {

            // veröffentlichen
            if (isset($mybb->input['ideapublic'])) {

                $forum_fid = $mybb->settings['character_manager_ideas_puplic_forum'];

                // Set up posthandler.
                require_once MYBB_ROOT."inc/datahandlers/post.php";
                $posthandler = new PostDataHandler("insert");	
                $posthandler->action = "thread";
    
                // Create session for this user
                require_once MYBB_ROOT.'inc/class_session.php';
                $session = new session;
                $session->init();
                $mybb->session = &$session;

                $threadmessage = character_manager_idea_post();

                // Set the thread data that came from the input to the $thread array.
                $new_thread = array(
                    "fid" => $forum_fid,
                    "subject" => $mybb->get_input('title'),
                    "prefix" => (int)$mybb->get_input('threadprefix'),
                    "icon" => (int)0,
                    "uid" => $mybb->user['uid'],
                    "username" => $mybb->user['username'],
                    "message" => $threadmessage,
                    "ipaddress" => $session->packedip,
                    "posthash" => $mybb->get_input('posthash'),
                    "savedraft" => (int)0
                );

                // Set up the thread options from the input.
                $new_thread['options'] = array(
                    "signature" => (int)1,
                    "subscriptionmethod" => (int)0,
                    "disablesmilies" => (int)0
                );

                $posthandler->set_data($new_thread);
                // Now let the post handler do all the hard work.
                $valid_thread = $posthandler->validate_thread();

                $post_errors = array();
                // Fetch friendly error messages if this is an invalid thread
                if(!$valid_thread){
                    $post_errors = $posthandler->get_friendly_errors();
                }

                // One or more errors returned, fetch error list and throw to newthread page
                if(count($post_errors) > 0) {
                    $ideaserrors = inline_error($post_errors);
                    $mybb->input['action'] = "character_manager_ideas_add";
                }
                // No errors were found, it is safe to insert the thread.
                else
                {
                    $thread_info = $posthandler->insert_thread();
                    $tid = $thread_info['tid'];
                    $visible = $thread_info['visible'];
                    $force_redirect = false;

                    // Mark thread as read
                    require_once MYBB_ROOT."inc/functions_indicators.php";
                    mark_thread_read($tid, $forum_fid);

                    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
                    if (!empty($cid)) {
                        $db->delete_query("character_manager", "cid = ".$cid);
                    }

                    // Visible thread
                    $url = get_thread_link($tid);
                    redirect($url, $lang->character_manager_redirect_newthread, "", $force_redirect);
                }
            } 
            // privat
            else {

                $idea_array = array(
                    "uid" => $mybb->user['uid'],
                    "title" =>  $db->escape_string($mybb->get_input('title'))            
                );

                $reminder_day = $mybb->settings['character_manager_ideas_reminder'];
                if ($reminder_day != 0) {
                    $today = new DateTime();
                    $today->setTime(0, 0, 0);
                    $today->modify("+{$reminder_day} days");
                    $idea_array['reminder'] = $db->escape_string($today->format("Y-m-d"));            
                }

                // Abfrage der individuellen Felder
                $fields_query = $db->query("SELECT identification, type FROM ".TABLE_PREFIX."character_manager_fields");

                while ($field = $db->fetch_array($fields_query)) {

                    $identification = $field['identification'];        
                    $type = $field['type'];
    
                    if ($type == 'multiselect' || $type == 'checkbox') {
                        $value = $mybb->get_input($identification, MyBB::INPUT_ARRAY);
                        $value = implode(",", array_map('trim', $value));
                    } else {
                        $value = $mybb->get_input($identification);        
                    }
    
                    $idea_array[$identification] = $db->escape_string($value);            
                }

                $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
                if (!empty($cid)) {
                    $db->update_query("character_manager", $idea_array, "cid=  ".$cid);
                    redirect("usercp.php?action=character_manager", $lang->character_manager_redirect_edit);
                } else {
                    $db->insert_query("character_manager", $idea_array);
                    redirect("usercp.php?action=character_manager", $lang->character_manager_redirect_add);
                }
            }
        }
    }

    // Charakteridee hinzufügen
    if ($mybb->input['action'] == "character_manager_ideas_add") {

        add_breadcrumb($lang->nav_usercp, "usercp.php");
        add_breadcrumb($lang->character_manager_overview, "usercp.php?action=character_manager");
        add_breadcrumb($lang->character_manager_ideas_add, "usercp.php?action=character_manager_ideas_add");

        $ideas_puplic = $mybb->settings['character_manager_ideas_puplic'];

        if(!isset($ideaserrors)){
            $ideaserrors = "";
        }

        $cid = 0;
        $title = $mybb->get_input('title');
        $own_fields = character_manager_generate_fields(null, true);

        if ($ideas_puplic == 1) {
            $extrainfos = $mybb->get_input('extrainfos');
            $puplic_button = "<input type=\"submit\" class=\"button\" name=\"ideapublic\" value=\"{$lang->character_manager_ideas_form_button_public}\" />";

            $threadprefix = character_manager_threadprefixes();
            if (!empty($threadprefix)) {
                eval("\$prefix_field = \"".$templates->get("charactermanager_ideas_form_prefix")."\";");
            } else {
                $prefix_field = "";
            }

            eval("\$puplic_field = \"".$templates->get("charactermanager_ideas_form_puplic")."\";");
        } else {
            $puplic_field = "";
            $prefix_field = "";
            $puplic_button = "";
        }

        $lang->charactermanager_ideas_form = $lang->character_manager_ideas_add; 

		eval("\$page = \"".$templates->get("charactermanager_ideas_form")."\";");
		output_page($page);
		die();
    }

    // Charakteridee bearbeiten
    if ($mybb->input['action'] == "character_manager_ideas_edit") {

        add_breadcrumb($lang->nav_usercp, "usercp.php");
        add_breadcrumb($lang->character_manager_overview, "usercp.php?action=character_manager");
        add_breadcrumb($lang->character_manager_ideas_edit, "usercp.php?action=character_manager_ideas_edit");

        $ideas_puplic = $mybb->settings['character_manager_ideas_puplic'];

        if(!isset($ideaserrors)){
            $ideaserrors = "";
        }

        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

        $draft = $db->fetch_array($db->simple_select('character_manager', '*', 'cid = '.$cid));
        $title = $draft['title'];
        $own_fields = character_manager_generate_fields($draft);

        if ($ideas_puplic == 1) {
            $extrainfos = $mybb->get_input('extrainfos');
            $puplic_button = "<input type=\"submit\" class=\"button\" name=\"ideapublic\" value=\"{$lang->character_manager_ideas_form_button_public}\" />";
            
            $threadprefix = character_manager_threadprefixes();
            if (!empty($threadprefix)) {
                eval("\$prefix_field = \"".$templates->get("charactermanager_ideas_form_prefix")."\";");
            } else {
                $prefix_field = "";
            }

            eval("\$puplic_field = \"".$templates->get("charactermanager_ideas_form_puplic")."\";");
        } else {
            $puplic_field = "";
            $puplic_button = "";
            $prefix_field = "";
        }

        $lang->charactermanager_ideas_form = $lang->character_manager_ideas_edit;

		eval("\$page = \"".$templates->get("charactermanager_ideas_form")."\";");
		output_page($page);
		die();
    }

    // Charakteridee löschen
    if ($mybb->input['action'] == "character_manager_ideas_delete") {

        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

        $db->delete_query("character_manager", "cid = ".$cid);
    
        redirect("usercp.php?action=character_manager", $lang->character_manager_redirect_delete);
    }

    // Charakteridee verlängern
    if ($mybb->input['action'] == "character_manager_ideas_extend") {

        $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

        $idea_array = array();
        $reminder_day = $mybb->settings['character_manager_ideas_reminder'];
        if ($reminder_day != 0) {
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $today->modify("+{$reminder_day} days");
            $idea_array['reminder'] = $db->escape_string($today->format("Y-m-d"));            
        }

       $db->update_query("character_manager", $idea_array, "cid=  ".$cid);             
       redirect("usercp.php?action=character_manager", $lang->character_manager_redirect_extend);
    }
    
    // Multiregister speichern
    if ($mybb->request_method == "post" && $mybb->input['action'] == "do_charactermanager_registration") {

        $required_setting = $mybb->settings['character_manager_required'];
        $usergroup = $mybb->settings['character_manager_usergroup'];

        if ($mybb->user['as_uid'] != 0) {
            $mainChara = get_user($mybb->user['as_uid']);
        } else {
            $mainChara = get_user($mybb->user['uid']);
        }

        // Set up user handler.
		require_once MYBB_ROOT."inc/datahandlers/user.php";
		$userhandler = new UserDataHandler('insert');

        // Create session for this user
        require_once MYBB_ROOT.'inc/class_session.php';
        $session = new session;
        $session->init();
        $mybb->session = &$session;

		// Set the data for the new user.
		$new_user = array(
			"username" => $mybb->get_input('username'),
			"password" => $mybb->get_input('password'),
            "password2" => $mybb->get_input('password2'),
            "email" => $mainChara['email'],
            "email2" => $mainChara['email'],
			"usergroup" => $usergroup,
			"referrer" => 0,
			"timezone" => $mainChara['timezone'],
			"language" => $mainChara['language'],
            "regip" => $session->packedip,
            "profile_fields" => $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY),
		);

		// Set the data of the user in the datahandler.
		$userhandler->set_data($new_user);
		$errors = array();

		// Validate the user and get any errors that might have occurred.
		if(!$userhandler->validate_user()) {
			$errors = $userhandler->get_friendly_errors();
		}

        // Eigene Errors
        if ($required_setting == 1) {
            $required_fields = str_replace(", ", ",", $mybb->settings['character_manager_required_fields']);
            $required_fields = explode(",", $required_fields);

            if (!empty($required_fields)) {
                foreach ($required_fields as $requiredfield) {
                    if (is_numeric($requiredfield)) {
                        $inputfield = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY); 
                        $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid = '".$requiredfield."'"), "name");
                    
                        if (empty($inputfield['fid'.$requiredfield])) {
                            $errors[] = $lang->sprintf($lang->character_manager_error_fields, $fieldname);  
                        }
                    } else {
                        $inputfield = $mybb->get_input('application_fields', MyBB::INPUT_ARRAY);
                        $fieldname = $db->fetch_field($db->simple_select("application_ucp_fields", "label", "fieldname = '".$requiredfield."'"), "label");
                    
                        if (empty($inputfield[$requiredfield])) {
                            $errors[] = $lang->sprintf($lang->character_manager_error_fields, $fieldname);   
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            $regerrors = inline_error($errors);
            $mybb->input['action'] = "character_manager_registration";
        }
		else
		{
			$user_info = $userhandler->insert_user();         
            $uid = (int)$user_info['uid'];

            // Accountsswichter
            require_once MYBB_ROOT.'/inc/plugins/accountswitcher/class_accountswitcher.php';
            $eas = new AccountSwitcher($mybb, $db, $cache, $templates);

            $as_update = array(
                "as_uid" => (int)$mainChara['uid']
            );            
            $db->update_query("users", $as_update, "uid = ".$uid);

            $eas->update_accountswitcher_cache();
            $eas->update_userfields_cache();

            // Katjas Steckbriefplugin => Felder hinzufügen
            if ($db->table_exists("application_ucp_userfields")) {
         
                $application_fields = $mybb->get_input('application_fields', MyBB::INPUT_ARRAY);

                foreach ($application_fields as $field => $value) {
                    if (is_array($value)) {
                        $value = implode(',', array_map('trim', $value));         
                    }
         
                    $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$field."'"), "id");

                    $insert_array = [
                        'uid' => (int)$uid,
                        'value' => $db->escape_string($value),
                        'fieldid' => (int)$db->escape_string($fieldid),         
                    ];

                    $db->insert_query('application_ucp_userfields', $insert_array);
                }
            }

            redirect("usercp.php?action=character_manager", $lang->character_manager_redirect_registration);
		}
    }

    // Multiregister
    if ($mybb->input['action'] == "character_manager_registration") {

		add_breadcrumb($lang->nav_usercp, "usercp.php");
        add_breadcrumb($lang->character_manager_overview, "usercp.php?action=character_manager");
        add_breadcrumb($lang->character_manager_registration, "usercp.php?action=character_manager_registration");

        $required_setting = $mybb->settings['character_manager_required'];
        $required_fields = str_replace(", ", ",", $mybb->settings['character_manager_required_fields']);
        $required_fields = explode(",", $required_fields);
        $adopt_setting = $mybb->settings['character_manager_adopt'];
        $adopt_fields = str_replace(", ", ",", $mybb->settings['character_manager_adopt_fields']);
        $adopt_fields = explode(",", $adopt_fields);

        // Hauptcharakter
        if ($mybb->user['as_uid'] != 0) {
            $mainUID = intval($mybb->user['as_uid']);
            $mastername = $db->fetch_field($db->simple_select("users", "username", "uid = ".$mainUID), "username");
        } else {
            $mastername = $mybb->user['username'];
            $mainUID = $mybb->user['uid'];
        }
        $mastercharacter = $lang->sprintf($lang->character_manager_registration_masteraccount, $mastername);

        // MyBB verpflichtend
        $required_profilefields = $db->simple_select("profilefields", "fid", "required = 1");
        $requiredprofilefields = [];
        while($profilefield = $db->fetch_array($required_profilefields)) {
            $requiredprofilefields[] = (string)$profilefield['fid'];
        }
        $required_fields = array_map('trim', $required_fields);
        $adopt_fields = array_map('trim', $adopt_fields);
        $required_fields = array_filter($required_fields, fn($v) => $v !== '');
        $adopt_fields = array_filter($adopt_fields, fn($v) => $v !== '');

        foreach ($requiredprofilefields as $fid) {
            if (!in_array($fid, $required_fields) && !in_array($fid, $adopt_fields)) {
                $required_fields[] = $fid;
            }
        }

        // verpflichtenden Angaben
        if (!empty($required_fields)) {
                
            $requiredfields = "";
            foreach($required_fields as $requiredfield) {
                // Profilfeld
                if (is_numeric($requiredfield)) {
                    $requiredfields .= character_manager_profilefields($requiredfield, $mainUID);
                } 
                // Steckifeld
                else {
                    $requiredfields .= character_manager_applicationfields($requiredfield, $mainUID);
                }   
            }
        } else {
            $requiredfields = "";
            $displayrequiredfields = 'style="display: none;"';
        }

        // übernommene Angaben
        if ($adopt_setting == 1) {

            $adoptfields = "";
            if (empty($adopt_fields)) {
                $displayadoptfields = 'style="display: none;"';
            } else {
                foreach($adopt_fields as $adoptfield) {
                    // Profilfeld
                    if (is_numeric($adoptfield)) {
                        $adoptfields .= character_manager_profilefields($adoptfield, $mainUID);
                    } 
                    // Steckifeld
                    else {
                        $adoptfields .= character_manager_applicationfields($adoptfield, $mainUID);
                    }
                }
            }
        } else {
            $adoptfields = "";
            $displayadoptfields = 'style="display: none;"';
        }

        if(!isset($regerrors)){
            $regerrors = "";
        }

        $username = $mybb->get_input('username');

		eval("\$page = \"".$templates->get("charactermanager_registration")."\";");
		output_page($page);
		die();
    }

    // PDF ERSTELLEN
    if($mybb->input['action'] == "character_manager_pdf") {

        $export_setting = $mybb->settings['character_manager_export'];
        if ($export_setting == 0) return;

        if (!ob_get_level()) {
            ob_start();
        }

        $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
        if (empty($uid)) return;
        $userIDs = character_manager_get_allchars($uid);

        // Unbefugte abfangen!
        if ($mybb->user['uid'] == 0 ||(!array_key_exists($mybb->user['uid'], $userIDs) && $mybb->usergroup['canmodcp'] != '1')) {
            error_no_permission();
        }

        require_once MYBB_ROOT."inc/class_parser.php";          
        $parser = new postParser;
        $parser_array = array(
            "allow_html" => 1,
            "allow_mycode" => 1,
            "allow_smilies" => 1,
            "allow_imgcode" => 0,
            "filter_badwords" => 0,
            "nl2br" => 1,
            "allow_videocode" => 0
        );

        $pdfUser = get_user($uid);
        $ownusername = $pdfUser['username'];

        $export_fields = str_replace(", ", ",", $mybb->settings['character_manager_export_fields']);
        $export_fields = explode(",", $export_fields);

        $inhalt = "<table cellpadding=\"6\" cellspacing=\"0\" border=\"0\" width=\"100%\">";
        foreach ($export_fields as $exportfield) {
            if (is_numeric($exportfield)) {
                $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid = ".$exportfield), "name");
                $exportFID = "fid".$exportfield;
                $fieldvalue = $db->fetch_field($db->simple_select("userfields", $exportFID, "ufid = ".$uid), $exportFID);
                $fieldvalue = $parser->parse_message($fieldvalue, $parser_array);
                $fieldvalue = character_manager_clean_html($fieldvalue);

                if (trim(strip_tags($fieldvalue)) === '') {
                    $fieldvalue = '<em>(Keine Angaben)</em>';
                }

                $inhalt .= "
                <tr>
                <td width=\"30%\" style=\"font-weight: bold;\">".$fieldname.":</td>
                <td width=\"70%\" style=\"text-align: justify;\">".$fieldvalue."</td>
                </tr>";
            }
        }
        $inhalt .= "</table>";

        $illegalChars = ['\\', '/', ':', '*', '?', '"', '<', '>', '|'];
        $pdfname = str_replace($illegalChars, '_', $ownusername);

        $pdfAuthor = $ownusername;
        $pdfName = $pdfname.".pdf";
        $subject = $ownusername;
        
        //////////////////////////// Inhalt des PDFs als HTML-Code \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
        $html = '<div style="text-align: center; font-weight: bold; font-size: 22pt; margin-bottom: 20px;">'.$subject.'<br><span style="font-size: 10pt; font-weight: none; ">'.$pdfUser['usertitle'].'</span></div>
        <div style="font-size: 11pt; line-height: 1.6;">'.$inhalt.'</div>';
 
        //////////////////////////// Erzeugung eures PDF Dokuments \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
 
        // TCPDF Library laden
        require_once('tcpdf/tcpdf.php');

        // Kopfzeile 
        class CustomPDF extends TCPDF {
            protected $regdate;
            protected $postcount;
            protected $threadcount;
            protected $forumname;    
            protected $forumlink;

            public function setHeaderDataCustom($regdate, $postcount, $threadcount, $forumname, $forumlink) {
                $this->regdate = $regdate;
                $this->postcount = $postcount;
                $this->threadcount = $threadcount;
                $this->forumname = $forumname;
                $this->forumlink = $forumlink;    
            }

            public function Header() {
                $html = '
                <table width="100%" cellpadding="4" cellspacing="0" style="font-size: 8pt;">
                <tr>
                <td width="60%">
                    <b>Registriert seit:</b> '.$this->regdate.'<br>
                    <b>Beiträge:</b> '.$this->postcount.'<br>
                    <b>Themen:</b> '.$this->threadcount.'<br>
                </td>
                <td width="40%" align="right">
                    <b>'.$this->forumname.'</b><br>
                    <span style="font-size: 7pt; color: #555;">'.$this->forumlink.'</span>
                </td>
                </tr>
                </table>
                <hr style="margin-top:5px;">';
                $this->writeHTMLCell(0, 0, '', '', $html, 0, 1, false, true, 'T', true);
            }
        }
        
        // Erstellung des PDF Dokuments       
        $pdf = new CustomPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
 
        // Dokumenteninformationen
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($pdfAuthor);
        $pdf->SetTitle($subject);       
        $pdf->SetSubject($subject);

        // Header und Footer Informationen
        $pdf->setPrintHeader(true);     
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $pdf->setFooterData([0,0,0], [255,255,255]);
 
        // Auswahl des Font        
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Auswahl der MArgins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);       
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
 
        // Automatisches Autobreak der Seiten       
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
 
        // Image Scale 
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Schriftart        
        $pdf->SetFont('helvetica', '', 10);

        $regdate = date("d.m.Y", $pdfUser['regdate']);
        $postcount = number_format($pdfUser['postnum'], 0, ',', '.');
        $threadcount = number_format($pdfUser['threadnum'], 0, ',', '.');
        $pdf->setHeaderDataCustom($regdate, $postcount, $threadcount, $mybb->settings['bbname'], $mybb->settings['bburl']);

        // Neue Seite       
        $pdf->AddPage();
 
        // Fügt den HTML Code in das PDF Dokument ein       
        $pdf->writeHTML($html, true, false, true, false, '');
 
        //Ausgabe der PDF
        //Variante 1: PDF direkt an den Benutzer senden:
        $pdf->Output($pdfName, 'I');    
    }
}

// PROFIL => EXPORTLINK
function character_manager_memberprofile() {

    global $db, $mybb, $lang, $templates, $theme, $memprofile, $character_manager_exportLink;

    $export_setting = $mybb->settings['character_manager_export'];

	// Sprachdatei laden
    $lang->load('character_manager');

    // man selbst (alle Accounts) && Teammitglieder (modcp Rechte)
    if ($export_setting == 1 && $mybb->user['uid'] != 0) {
        $userIDs = character_manager_get_allchars($memprofile['uid']);

        if (array_key_exists($mybb->user['uid'], $userIDs) || $mybb->usergroup['canmodcp'] == '1') {
            $character_manager_exportLink = "<a href=\"usercp.php?action=character_manager_pdf&amp;uid=".$memprofile['uid']."\">".$lang->character_manager_export."</a>";
        } else {
            $character_manager_exportLink = "";
        }
    } else {
        $character_manager_exportLink = "";
    }
}

// ERINNERUNGSBANNER
function character_manager_global() {

    global $db, $mybb, $lang, $templates, $character_manager_banner, $bannertext;

    // Sprachdatei laden
    $lang->load('character_manager');

    $reminder_day = $mybb->settings['character_manager_ideas_reminder'];

    $character_manager_banner = "";
    if ($reminder_day == 0) return;
    if ($mybb->user['uid'] == 0) return;

    $allCharas = character_manager_get_allchars($mybb->user['uid']);
    $allUids = implode(",",array_keys($allCharas));

    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $todayFormatted = $today->format("Y-m-d");

    $ideasQuery = $db->simple_select('character_manager', 'cid, title', 'uid IN ('.$allUids.') AND reminder < \''.$todayFormatted.'\'');

    $bannertext = "";
    $count = 0;
    while ($idea = $db->fetch_array($ideasQuery)) {
        $count++;

        // Leer laufen lassen
        $cid = "";
        $title = ""; 
        $optionDel = "";  
        $optionEdit = ""; 
    
        // Mit Infos füllen
        $cid = $idea['cid'];
        $title = $idea['title'];

        // Optionen => löschen & bearbeiten
        $optionDel = "<a href=\"usercp.php?action=character_manager_ideas_delete&cid=".$cid."\" onClick=\"return confirm('Wirklich löschen?');\">Nein</a>";
        $optionExtend = "<a href=\"usercp.php?action=character_manager_ideas_extend&cid=".$cid."\">Ja</a>";

        eval("\$bannertext .= \"".$templates->get("charactermanager_ideas_banner_text")."\";");
    }

    if (!empty($bannertext)) {
        if ($count == 1) {
            $lang->character_manager_ideas_banner = $lang->sprintf($lang->character_manager_ideas_banner, 'dieser', '');
        } else {
            $lang->character_manager_ideas_banner = $lang->sprintf($lang->character_manager_ideas_banner, 'diesen', 'n');
        }
        eval("\$character_manager_banner = \"".$templates->get("charactermanager_ideas_banner")."\";");
    } else {
        $character_manager_banner = "";
    }
}

// ONLINE LOCATION
function character_manager_online_activity($user_activity) {

	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) { 
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch ($filename) {
		case 'usercp':
			if ($parameters['action'] == "character_manager") {
				$user_activity['activity'] = "character_manager";
			}
            if ($parameters['action'] == "character_manager_registration") {
				$user_activity['activity'] = "character_manager_registration";
			}
            if ($parameters['action'] == "character_manager_ideas_add") {
				$user_activity['activity'] = "character_manager_ideas_add";
			}
            if ($parameters['action'] == "character_manager_ideas_edit") {
				$user_activity['activity'] = "character_manager_ideas_edit";
			}
            if ($parameters['action'] == "character_manager_pdf") {
				$user_activity['activity'] = "character_manager_pdf";
			}
            break;
	}

	return $user_activity;
}
function character_manager_online_location($plugin_array) {

	global $lang, $db, $mybb;
    
    // SPRACHDATEI LADEN
    $lang->load("character_manager");

	if ($plugin_array['user_activity']['activity'] == "character_manager") {
		$plugin_array['location_name'] = $lang->character_manager_online_location;
	}
    if ($plugin_array['user_activity']['activity'] == "character_manager_registration") {
		$plugin_array['location_name'] = $lang->character_manager_online_location_registration;
	}
    if ($plugin_array['user_activity']['activity'] == "character_manager_ideas_add") {
		$plugin_array['location_name'] = $lang->character_manager_online_location_ideas_add;
	}
    if ($plugin_array['user_activity']['activity'] == "character_manager_ideas_edit") {
		$plugin_array['location_name'] = $lang->character_manager_online_location_ideas_edit;
	}
    if ($plugin_array['user_activity']['activity'] == "character_manager_ideas_pdf") {
		$plugin_array['location_name'] = $lang->character_manager_online_location_pdf;
	}

	return $plugin_array;
}

// WAS PASSIERT MIT EINEM GELÖSCHTEN USER
function character_manager_user_delete(){

    global $db, $user;

    // UID gelöschter Chara
    $deleteChara = (int)$user['uid'];

    $allUids = character_manager_get_allchars($deleteChara);

    if (count($allUids) == 1) {
        $db->delete_query("character_manager", "uid = ".$deleteChara);
    } else {
        $existing_uids = [];
        foreach ($allUids as $uid => $name) {
            if ($uid != $deleteChara) {
                $existing_uids[] = $uid;
            }
        }

        if (!empty($existing_uids)) {
            $new_uid = $existing_uids[0];
            $db->update_query("character_manager", ["uid" => (int)$new_uid], "uid = ".$deleteChara);
        } else {
            $db->delete_query("character_manager", "uid = ".$deleteChara);
        }
    }
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// ACCOUNTSWITCHER HILFSFUNKTION => Danke, Katja <3
function character_manager_get_allchars($user_id) {

	global $db;

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($user_id)['as_uid'])) {
        $as_uid = intval(get_user($user_id)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$user_id.") OR (uid = ".$user_id.") ORDER BY username");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$as_uid.") OR (uid = ".$user_id.") OR (uid = ".$as_uid.") ORDER BY username");
	}
	while ($users = $db->fetch_array($get_all_users)) {
        $uid = $users['uid'];
        $charas[$uid] = $users['username'];
	}
	return $charas;  
}

// PROFILFELDER AUSLESEN
function character_manager_profilefields($input_fid, $uid) {

    global $db, $mybb, $templates;

    if (!empty($mybb->settings['character_manager_adopt_fields'])) {
        $adoptfields = str_replace(", ", ",", $mybb->settings['character_manager_adopt_fields']);        
        $adoptfields = explode(",", $adoptfields);
    }

    $fieldQuery = $db->query("SELECT fid, name, description, type, length FROM ".TABLE_PREFIX."profilefields 
    WHERE fid = ".$input_fid."
    ");

    $profilefields = "";
    while ($field = $db->fetch_array($fieldQuery)) {

        $fid = $field['fid'];
        $name = $field['name'];
        $description = $field['description'];
        $length = $field['length'];
        $adopt = 0;
        $options = explode("\n", $field['type']); 
        $type = $options[0];  

        if ($type == "select" || $type == "multiselect" || $type == "radio" || $type == "checkbox") {
            unset($options[0]);
        }
                
        if (!empty($mybb->settings['character_manager_adopt_fields'])) {
            if (in_array($fid, $adoptfields)) {
                $fieldFID = "fid".$fid;
                if ($type == "multiselect" || $type == "checkbox") {
                    $value = $db->fetch_field($db->simple_select("userfields", $fieldFID, "ufid = ".$uid.""), $fieldFID);
                    $useropts = explode("\n", $value);
                    $value = implode(",",$useropts);
                } else {
                    $value = $db->fetch_field($db->simple_select("userfields", $fieldFID, "ufid = ".$uid.""), $fieldFID);
                }
                $adopt = 1;
            } else {
                $inputfields = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY);
                if (!empty($inputfields)) {
                    $fieldFID = "fid".$fid;
                    $value = $inputfields[$fieldFID];
                } else {
                    $value = "";
                }
            }
        } else {
            $inputfields = $mybb->get_input('profile_fields', MyBB::INPUT_ARRAY);
            if (!empty($inputfields)) {
                $fieldFID = "fid".$fid;
                $value = $inputfields[$fieldFID];
            } else {
                $value = "";
            }
        }

        // INPUTS generieren
        $code = character_manager_generate_input_field($fid, $type, 'reg', $value, $options, $length, $adopt);

        eval("\$profilefields .= \"".$templates->get("charactermanager_registration_fields")."\";");
    }

    return $profilefields;
}

// STECKBRIEFFELDER AUSLESEN
function character_manager_applicationfields($input_name, $uid) {

    global $db, $mybb, $templates;

    if (!empty($mybb->settings['character_manager_adopt_fields'])) {
        $adoptfields = str_replace(", ", ",", $mybb->settings['character_manager_adopt_fields']);        
        $adoptfields = explode(",", $adoptfields);
    }

    $fieldQuery = $db->query("SELECT id, fieldtyp, fielddescr, label, options FROM ".TABLE_PREFIX."application_ucp_fields
    WHERE fieldname = '".$input_name."'
    ");

    $applicationfields = "";
    while ($field = $db->fetch_array($fieldQuery)) {

        $id = $field['id'];
        $name = $field['label'];
        $description = $field['fielddescr'];
        $options = str_replace(", ", ",", $field['options']); 
        $options = explode(",", $options); 
        $type = $field['fieldtyp'];  
        $length = 0;
        $adopt = 0;

        if (!empty($mybb->settings['character_manager_adopt_fields'])) {
            if (in_array($input_name, $adoptfields)) {
                $value = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = ".$uid." AND fieldid = ".$id.""), "value");
                $adopt = 1;
            } else {
                $inputfields = $mybb->get_input('application_fields', MyBB::INPUT_ARRAY);
                if (!empty($inputfields)) {
                    $value = $inputfields[$input_name];
                } else {
                    $value = "";
                }
            }
        } else {
            $inputfields = $mybb->get_input('application_fields', MyBB::INPUT_ARRAY);
            if (!empty($inputfields)) {
                $value = $inputfields[$input_name];
            } else {
                $value = "";
            }
        }

        // INPUTS generieren
        $code = character_manager_generate_input_field($input_name, $type, 'reg', $value, $options, $length, $adopt);

        eval("\$applicationfields .= \"".$templates->get("charactermanager_registration_fields")."\";");
    }

    return $applicationfields;
}

// CHARAKTERIDEE-FELDER AUSLESEN
function character_manager_generate_fields($draft = null, $input_data = null) {

    global $db, $mybb, $templates;

    $fields_query = $db->query("SELECT * FROM ".TABLE_PREFIX."character_manager_fields
    ORDER BY disporder ASC, title ASC
    ");

    $own_fields = "";
    while ($field = $db->fetch_array($fields_query)) {

        $identification = $field['identification'];
        $title = $field['title'];
        $description = $field['description'];
        $type = $field['type'];
        $options = explode("\n", $field['options']); 

        if ($input_data) {
            if ($type == "multiselect" || $type == "checkbox") {
                $value = $mybb->get_input($identification, MyBB::INPUT_ARRAY);
            } else {
                $value = $mybb->get_input($identification);
            }
        } elseif ($draft) {
            $value = $draft[$identification];
        } else {
            $value = ""; 
        }

        // INPUTS generieren
        $code = character_manager_generate_input_field($identification, $type, 'ideas', $value, $options);

        eval("\$own_fields .= \"".$templates->get("charactermanager_ideas_form_fields")."\";");
    }

    return $own_fields;
}

// INPUT FELDER GENERIEN
function character_manager_generate_input_field($identification, $type, $mode = '', $value = '', $expoptions = [], $length = 0, $adopt = '') {

    $input = '';
    if ($mode == 'reg') {
        // Profilfeld
        if (is_numeric($identification)) {
            $identification = "profile_fields[fid".$identification."]";
        } 
        // Steckifeld
        else {    
            $identification = "application_fields[".$identification."]";
        }

        if (!empty($adopt)) {
            if ($type == "text" || $type == "textarea") {
                $unchangeable = "readonly";
            } else {
                $unchangeable = "disabled";
            }
        } else {
            $unchangeable = "";
        }
    } else {
        $unchangeable = "";
        $identification = $identification;
    }

    switch ($type) {
        case 'text':
            $input = '<input type="text" class="textbox" size="40" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($value) . '" '.$unchangeable.'>';
            break;

        case 'textarea':
            $input = '<textarea name="'.htmlspecialchars($identification).'" rows="6" cols="42" '.$unchangeable.'>' . htmlspecialchars($value) . '</textarea>';
            break;

	case 'url':
            $input = '<input type="url" class="textbox" size="40" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($value) . '">';
            break;

        case 'radio':
            foreach ($expoptions as $option) {
                $checked = ($option == $value) ? ' checked' : '';
                $input .= '<input type="radio" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($option) . '" '.$unchangeable.$checked.'>';
                $input .= '<span class="smalltext">' . htmlspecialchars($option) . '</span><br />';
            }
            if (!empty($adopt) && $unchangeable == "disabled") {
                $input .= '<input type="hidden" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($value) . '">';
            }
            break;

        case 'select':
            $input = '<select name="'.htmlspecialchars($identification).'" size="'.$length.'" '.$unchangeable.'>';
            foreach ($expoptions as $option) {
                $selected = ($option == $value) ? ' selected' : '';
                $input .= '<option value="' . htmlspecialchars($option) . '"'.$selected . '>' . htmlspecialchars($option) . '</option>';
            }
            $input .= '</select>';
            if (!empty($adopt) && $unchangeable == "disabled") {
                $input .= '<input type="hidden" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($value) . '">';
            }
            break;

        case 'multiselect':
            $value = is_array($value) ? $value : explode(',', $value);
            $input = '<select name="'.htmlspecialchars($identification).'[]" multiple="multiple" size="'.$length.'" '.$unchangeable.'>';
            foreach ($expoptions as $option) {
                $selected = in_array($option, $value) ? ' selected' : '';
                $input .= '<option value="' . htmlspecialchars($option) . '"'.$selected . '>' . htmlspecialchars($option) . '</option>';
            }
            $input .= '</select>';

            if (!empty($adopt) && $unchangeable == "disabled") {
                foreach ($value as $val) {
                    $input .= '<input type="hidden" name="'.htmlspecialchars($identification).'[]" value="' . htmlspecialchars($val) . '">';
                }
            }
            break;

        case 'checkbox':
            $value = is_array($value) ? $value : explode(',', $value);
            $input_html = "";
            foreach ($expoptions as $option) {
                $checked = in_array($option, $value) ? ' checked' : '';
                $input_html .= '<input type="checkbox" name="'.htmlspecialchars($identification).'[]" value="' . htmlspecialchars($option) . '"'.$unchangeable.$checked . '>';
                $input_html .= '<span class="smalltext">' . htmlspecialchars($option) . '</span><br />';
            }
            $input .= $input_html;
            if (!empty($adopt) && $unchangeable == "disabled") {
                foreach ($value as $val) {
                    $input .= '<input type="hidden" name="'.htmlspecialchars($identification).'[]" value="' . htmlspecialchars($val) . '">';
                }
            }
            break;

        default:
            $input = '<input type="text" name="'.htmlspecialchars($identification).'" value="' . htmlspecialchars($value) . '">';
            break;
    }

    return $input;
}

// THREADPRÄFIX 
function character_manager_threadprefixes() {

    global $mybb, $db;

    $forum_fid = $mybb->settings['character_manager_ideas_puplic_forum'];

    // Präfix
    $prefixes_query = $db->query("SELECT pid, prefix FROM ".TABLE_PREFIX."threadprefixes
    WHERE (concat(',',forums,',') LIKE '%,".$forum_fid.",%')
    ORDER BY prefix ASC
    ");

    $allprefixes = [];
    while ($prefixes = $db->fetch_array($prefixes_query)) {
        $pid = "";
        $prefix = "";

        $pid = $prefixes['pid'];
        $prefix = $prefixes['prefix'];

        $allprefixes[$pid] = $prefix;
    }

    if (!empty($allprefixes)) {
        $threadprefix = "<select name=\"threadprefix\"><option value=\"0\">Kein Präfix</option>";
        foreach ($allprefixes as $pid => $prefix) {
            $threadprefix .= "<option value=\"".$pid."\">".$prefix."</option>";
        }
        $threadprefix .= "</select>";
    } else {
        $threadprefix = "";
    }

    return $threadprefix;
}

// Profilfelder säubern
function character_manager_clean_html($html) {

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    
    $html = '<?xml encoding="UTF-8"><!DOCTYPE html><html><body>' . $html . '</body></html>';
    
    $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $clean = $doc->saveHTML($doc->getElementsByTagName('body')->item(0));
    
    libxml_clear_errors();
    
    return $clean;
}

// CHARAKTERIDEE POST BAUEN
function character_manager_idea_post(){

    global $mybb, $db, $templates, $lang;
    
    // SPRACHDATEI LADEN
    $lang->load("character_manager");

    $message = "";

    $title = $mybb->get_input('title');
    if ($mybb->get_input('extrainfos')) {
        $extrainfos = $mybb->get_input('extrainfos');
    } else {
        $extrainfos = '';
    }

    $fields_query = $db->query("SELECT identification, title, type FROM " . TABLE_PREFIX . "character_manager_fields ORDER BY disporder ASC, title ASC");

    $fields = "";   
    $characteridea = [];      

    while ($field = $db->fetch_array($fields_query)) {
        $identification = $field['identification'];
        $fieldtitle = $field['title'];
        $type = $field['type'];

        if ($type == "multiselect" || $type == "checkbox") {
            $value = $mybb->get_input($identification, MyBB::INPUT_ARRAY);
            $value = implode(",", array_map('trim', $value));
        } else {
            $value = $mybb->get_input($identification);
        }

        $characteridea[$identification] = $value;

        if (!empty($value)) {
            eval("\$fields .= \"" . $templates->get("charactermanager_ideas_post_fields", 1, 0) . "\";");
        }
    }

    eval("\$message = \"" . $templates->get("charactermanager_ideas_post", 1, 0) . "\";");

    return $message;
}

#######################################
### DATABASE | SETTINGS | TEMPLATES ###
#######################################

// DATENBANKTABELLEN
function character_manager_database() {

    global $db;

    // FELDER
    if (!$db->table_exists("character_manager_fields")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."character_manager_fields (
            `cfid` int(10) unsigned NOT NULL AUTO_INCREMENT, 
            `identification` VARCHAR(250) NOT NULL DEFAULT '',
            `title` VARCHAR(250) NOT NULL DEFAULT '',
            `description` VARCHAR(500) NOT NULL DEFAULT '',
            `type` VARCHAR(250) NOT NULL DEFAULT '',
            `options` VARCHAR(500) NOT NULL DEFAULT '',
            `required` int(1) unsigned NOT NULL DEFAULT '0',
            `disporder` int(5) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY(`cfid`),
            KEY `cfid` (`cfid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1
        ");
    }
    
    // IDEEN
    if (!$db->table_exists("character_manager")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."character_manager (
            `cid` int(10) unsigned NOT NULL AUTO_INCREMENT, 
            `uid` int(11) unsigned NOT NULL ,
            `title` VARCHAR(500) NOT NULL DEFAULT '',
            `reminder` date,
            PRIMARY KEY(`cid`),
            KEY `cid` (`cid`)
            )
            ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1
        ");
    }

}

// EINSTELLUNGEN
function character_manager_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'character_manager_required' => array(
			'title' => 'verpflichtende Angaben',
            'description' => 'Soll bei der Erstellung von einem neuem Charaktere verpflichtende Profilfelder und/oder Steckbrieffelder angezeigt werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 1
		),
		'character_manager_required_fields' => array(
			'title' => 'verpflichtende Felder',
            'description' => 'Welche verpflichtende Profilfelder/Steckbrieffelder müssen bei der Erstellung von einem neuem Charakter ausgefüllt werden? Trage die FIDs/Identifikatoren per Komma getrennt ein.<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 2
		),
		'character_manager_adopt' => array(
			'title' => 'zu übernehmende Angaben',
            'description' => 'Sollen bestimmte Informationen vom Hauptaccount übernommen werden? Damit diese Profilfelder und/oder Steckbrieffelder nicht erneut ausgefüllt werden müssen.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 3
		),
		'character_manager_adopt_fields' => array(
			'title' => 'zu übernehmende Felder',
            'description' => 'Von welchen Profilfelder/Steckbrieffelder werden die Angaben für den neuen Charakter übernommen vom Hauptaccount? Trage die FIDs/Identifikatoren per Komma getrennt ein.<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 4
		),
        'character_manager_usergroup' => array(
			'title' => 'Gruppe',
            'description' => 'In welche Gruppe sollen die neu erstellten Accounts landen?',
            'optionscode' => 'groupselectsingle',
            'value' => '2', // Default
            'disporder' => 5
		),
        'character_manager_export' => array(
			'title' => 'Profilfelder exportieren',
            'description' => 'Dürfen User:in ihre Profilfelder als PDF exportieren?<br><b>Hinweis:</b> Das Steckbrief-Plugin von Risuena liefert für Steckbrieffelder diese Funktion schon.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 6
		),
        'character_manager_export_fields' => array(
			'title' => 'exportierende Profilfelder',
            'description' => 'Welche Profilfelder können exportiert werden? Trage die FIDs mit einem Komma getrennt ein.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 7
		),
        'character_manager_avatar_default' => array(
			'title' => 'Standard-Avatar',
            'description' => 'Wie heißt die Bilddatei, für die Standard-Avatare? Damit der Avatar für jedes Design richtig angezeigt wird, sollte der Namen in allen Designs gleich sein. Sprich in jedem Themen-Pfad muss eine Datei mit diesem Namen vorhanden sein.',
            'optionscode' => 'text',
            'value' => 'default_avatar.png', // Default
            'disporder' => 8
		),
        'character_manager_ideas' => array(
			'title' => 'Charakterideen',
            'description' => 'Dürfen User:innen eine Art Notizbuch für Charakterideen erstellen?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 9
		),
        'character_manager_ideas_reminder' => array(
			'title' => 'Erinnerung an Charakterideen',
            'description' => 'Nach wie vielen Tagen sollen User:innen an ihre Ideen erinnert werden? Sowohl für die erste Erinnerung als auch für Verlängerungen. 0 deaktiviert diese Funktion.',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 10
		),
        'character_manager_ideas_puplic' => array(
			'title' => 'Charakterideen veröffentlichen',
            'description' => 'Solle es die Option geben, das Charakterideen als eigenes öffentliche Thema automatisch gepostet werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 11
		),
        'character_manager_ideas_puplic_forum' => array(
			'title' => 'Forum für Charakterideen',
            'description' => 'In welchem Forum sollen die Themen der Charakterideen erstellt werden?',
            'optionscode' => 'forumselectsingle',
            'value' => '1', // Default
            'disporder' => 12
		),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'character_manager' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }
    }

    rebuild_settings();
}

// TEMPLATES
function character_manager_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'charactermanager',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->character_manager_overview}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<tr>
							<td class="thead" colspan="2">
								<strong>{$lang->character_manager_overview}</strong>
								<span class="float_right">
									<a href="usercp.php?action=character_manager_registration" class="character_manager_button">{$lang->character_manager_registration_link}</a>
								</span>
							</td>
						</tr>
						<tr>
							<td class="trow1">
								<div class="character_manager">
									{$characters_bit}
								</div>
								{$characters_ideas}
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_character',
        'template'	=> $db->escape_string('<div class="character_manager_character">	
        <div class="character_manager_username">
		{$characternameFormattedLink}
        </div>
        <div class="character_manager_avatar">
		<img src="{$avatarUrl}">
        </div>
        {$exportLink}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas',
        'template'	=> $db->escape_string('<div class="character_manager_ideas">
        <div class="character_manager_ideas-headline">
		<b>{$lang->character_manager_ideas}</b>
		<span class="float_right"><a href="usercp.php?action=character_manager_ideas_add" class="character_manager_button">{$lang->character_manager_ideas_link}</a></span>
        </div>
        {$ideas_bit}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_banner',
        'template'	=> $db->escape_string('<div class="pm_alert">{$lang->character_manager_ideas_banner}<br>{$bannertext}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_banner_text',
        'template'	=> $db->escape_string('<strong>{$title}</strong> ({$optionDel} | {$optionExtend})'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_character',
        'template'	=> $db->escape_string('<div class="character_manager_ideas_bit-chara">
        <div class="character_manager_ideas_bit-item">{$title} <span class="float_right">{$optionDel} {$optionEdit}</span></div>
        {$fields}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_fields',
        'template'	=> $db->escape_string('<div class="character_manager_ideas_bit-item"><b>{$fieldtitle}:</b> {$value}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_form',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->charactermanager_ideas_form}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<form action="usercp.php" method="post" id="charactermanager_ideas_form">
						{$ideaserrors}
						<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
							<tr>
								<td class="thead" colspan="2">
									<strong>{$lang->charactermanager_ideas_form}</strong>
								</td>
							</tr>
							
							<tr>
								<td class="trow1" colspan="2" align="center">
									<span class="smalltext">{$lang->character_manager_ideas_form_notice}</span>
								</td>
							</tr>

							<tr>
								<td class="trow1" width="30%"><strong>{$lang->character_manager_ideas_form_title}</strong>
									<div class="smalltext">{$lang->character_manager_ideas_form_title_desc}</div>
								</td>
								<td class="trow1">
									<span class="smalltext">
										<input type="text" class="textbox" name="title" id="title" value="{$title}" />
									</span>		
								</td>
							</tr>
							{$own_fields}
							{$puplic_field}
						</table>
						<br />
						<div align="center">
							<input type="hidden" name="cid" value="{$cid}" />
							<input type="hidden" name="action" value="do_charactermanager_ideas" />
							<input type="submit" class="button" name="ideasubmit" value="{$lang->character_manager_ideas_form_button_privat}" />
							{$puplic_button}
						</div>
					</form>
				</td>
			</tr>
		</table>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_form_fields',
        'template'	=> $db->escape_string('<tr>
        <td class="trow1" width="30%"><strong>{$title}</strong>
		<div class="smalltext">{$description}</div>
        </td>
        <td class="trow1">
		<span class="smalltext">
			{$code}
		</span>		
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_form_prefix',
        'template'	=> $db->escape_string('<tr>
        <td class="trow1" width="30%"><strong>{$lang->character_manager_ideas_form_prefix}</strong>
        <div class="smalltext">{$lang->character_manager_ideas_form_prefix_desc}</div>
        </td>
        <td class="trow1">
        {$threadprefix}	
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_form_puplic',
        'template'	=> $db->escape_string('<tr>
        <td class="trow1" width="30%"><strong>{$lang->character_manager_ideas_form_extrainfos}</strong>
		<div class="smalltext">{$lang->character_manager_ideas_form_extrainfos_desc}</div>
        </td>
        <td class="trow1">
		<span class="smalltext">
			<textarea name="extrainfos" rows="6" cols="42">{$extrainfos}</textarea>
		</span>		
        </td>
        </tr>
        {$prefix_field}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_post',
        'template'	=> $db->escape_string('[u][b]{$title}[/b][/u]<br>{$fields}<br>[b]{$lang->character_manager_ideas_post_extra}[/b]<br>{$extrainfos}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_ideas_post_fields',
        'template'	=> $db->escape_string('[b]{$fieldtitle}:[/b]<br>{$value}<br>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_registration',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->character_manager_registration}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$usercpnav}
				<td valign="top">
					<form action="usercp.php" method="post" id="charactermanager_registration_form">
						{$regerrors}
						<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
							<tr>
								<td class="thead" colspan="2">
									<strong>{$lang->character_manager_registration}</strong>
								</td>
							</tr>
							<tr>
								<td class="trow1" colspan="2">
									<fieldset>
										<legend><strong>{$lang->character_manager_registration_account}</strong></legend>
										<center>{$mastercharacter}</center>
										<table cellspacing="0" cellpadding="{$theme[\'tablespace\']}" width="100%">
											<tr>
												<td colspan="2"><span class="smalltext"><label for="username">{$lang->character_manager_registration_username}</label></span></td>
											</tr>
											<tr>
												<td colspan="2"><input type="text" class="textbox" name="username" id="username" style="width: 100%" value="{$username}" /></td>
											</tr>
											<tr>
												<td width="50%" valign="top"><span class="smalltext">{$lang->character_manager_registration_password}</span></td>
												<td width="50%" valign="top"><span class="smalltext">{$lang->character_manager_registration_password2}</span></td>
											</tr>
											<tr>
												<td width="50%" valign="top"><input type="password" class="textbox" name="password" id="password" style="width: 100%" /></td>
												<td width="50%" valign="top"><input type="password" class="textbox" name="password2" id="password2" style="width: 100%" /></td>
											</tr>
										</table>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td width="50%" class="trow1" valign="top" {$displayrequiredfields}>
									<fieldset>
										<legend><strong>{$lang->character_manager_registration_requiredfields}</strong></legend>
										<table cellspacing="0" cellpadding="{$theme[\'tablespace\']}" width="50">
											{$requiredfields}
										</table>
									</fieldset>
								</td>
								<td width="50%" class="trow1" valign="top" {$displayadoptfields}>
									<fieldset>
										<legend><strong>{$lang->character_manager_registration_adoptfields}</strong></legend>
										<span class="smalltext">{$lang->character_manager_registration_adoptfields_desc}</span>
										<table cellspacing="0" cellpadding="{$theme[\'tablespace\']}" width="50">
											{$adoptfields}
										</table>
									</fieldset>
								</td>
							</tr>
						</table>
						<br />
						<div align="center">
							<input type="hidden" name="action" value="do_charactermanager_registration" />
							<input type="submit" class="button" name="regsubmit" value="{$lang->character_manager_registration_button}" />
						</div>
					</form>
				</td>
			</tr>
		</table>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_registration_fields',
        'template'	=> $db->escape_string('<tr>
        <td>
		<strong>{$name}</strong>
		<br />
		<span class="smalltext">{$description}</span>
        </td>
        </tr>
        <tr>
        <td>{$code}</td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'charactermanager_usercp_nav',
        'template'	=> $db->escape_string('<tr>
        <td class="trow1 smalltext">
        <a href="usercp.php?action=character_manager" class="usercp_nav_item usercp_nav_options">{$lang->character_manager_nav}</a>
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }   
            else {
                $db->insert_query("templates", $template);
            }
        }
        
	
    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function character_manager_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'character_manager.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'.character_manager {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-around;
        gap: 10px;
        }

        a.character_manager_button:link,
        a.character_manager_button:hover,
        a.character_manager_button:visited,
        a.character_manager_button:active {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        display: inline-block;
        padding: 4px 8px;
        margin: 2px 2px 6px 2px;
        border: 1px solid #000;
        font-size: 14px;
        -moz-border-radius: 6px;
        -webkit-border-radius: 6px;
        border-radius: 6px;
        }

        .character_manager_character {
        width: 30%;
        text-align: center;
        }

        .character_manager_username {
        font-size: 16px;
        font-weight: bold;
        }

        .character_manager_avatar img {
        padding: 5px;
        border: 1px solid #ddd;
        background: #fff;
        }

        .character_manager_ideas {
        margin: 10px 0;
        }

        .character_manager_ideas-headline {
        background: #0066a2 url(../../../images/thead.png) top left repeat-x;
        color: #ffffff;
        border-bottom: 1px solid #263c30;
        padding: 8px;
        height: 35px;
        }

        .character_manager_ideas_bit-chara {
        margin: 10px 0 0 0;
        }
        
        .character_manager_ideas_bit-title {
        border-bottom: 2px solid #ddd;
        font-weight: bold;
        }

        .character_manager_ideas_bit-item {
        font-size: 11px;
        }',
		'cachefile' => 'character_manager.css',
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function character_manager_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function character_manager_is_updated(){

    global $db;

    $charset = 'utf8mb4';
    $collation = 'utf8mb4_unicode_ci';

    $collation_string = $db->build_create_table_collation();
    if (preg_match('/CHARACTER SET ([^\s]+)\s+COLLATE ([^\s]+)/i', $collation_string, $matches)) {
        $charset = strtolower($matches[1]);
        $collation = strtolower($matches[2]);
    }

    $databaseTables = [
        "application_manager",
        "application_checklist_groups",
        "application_checklist_fields"
    ];

    foreach ($databaseTables as $table_name) {
        if (!$db->table_exists($table_name)) {
            return false;
        }

        $full_table_name = TABLE_PREFIX . $table_name;

        $query = $db->query("
            SELECT TABLE_COLLATION 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '".$db->escape_string($full_table_name)."'
        ");
        $result = $db->fetch_array($query);
        $actual_collation = strtolower($result['TABLE_COLLATION'] ?? '');

        if ($actual_collation !== $collation) {
            return false;
        }
    }

    return true;
}
