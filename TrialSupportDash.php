<?php

namespace Vanderbilt\TrialSupportDash;

require_once(__DIR__ . "/RAAS_NECTAR.php");

class TrialSupportDash extends \Vanderbilt\TrialSupportDash\RAAS_NECTAR
{
	

	public function getEDCData($projectId = false)
	{
		if (!$this->screening_data) {
			if (!$projectId) {
				$projectId = $_GET['pid'];
			}
			//we are getting instrument name
			$instrument_name = "inclusionexclusion";
			//getting all fields in that instrument
			$inclusionexclusionFields = \REDCap::getFieldNames($instrument_name);

			$OnlyExclusion = [];
			//make array that have name exclusion_ 
			foreach($inclusionexclusionFields as $field){
				if (strpos($field, "exclusion_") !== false ) {
					$OnlyExclusion[] = $field;
				}
			}
			//remove fist element do not need
			 array_shift($OnlyExclusion);
			 //remove last element do not need
			 array_pop($OnlyExclusion);  
			//get data just from that instrument
			$this->data = json_decode(\REDCap::getData([
				"project_id" => $projectId,
				"return_format" => "json",
				"fields" => $OnlyExclusion,
				"exportDataAccessGroups" => true,				
			]));

			
		}
		
		
		return $this->data;
	}



	//new function to get the key 
	public function getProjectSettingExclusion()
	{
		$exclusions = $this->getProjectSetting('exclusion_reason_field');

		$exclusion_field_key = [];

		foreach ($exclusions as $i => $exclusionArray) {


			foreach ($exclusionArray as $exclusion_field) {
				$exclusion_field_key[$exclusion_field] = 0;
			}
		}

		return $exclusion_field_key;

	}
	
	public function getExclusionReportData()
	{
		if (!isset($this->exclusion_data)) {
			// create data object
			$exclusion_data = new \stdClass();
			$exclusion_data->rows = [];

			$exclusionCount = array();

			$data = $this->getEDCData();
			$exclusionSetting = $this->getProjectSettingExclusion();
			foreach($exclusionSetting as $i => $field_name){
				$exclusionCount[$i] = 0;
			}


			foreach($data as $record){
				//change to array to compare
				$obj = get_object_vars($record);
				//looping through exclusion settings
				foreach($exclusionSetting as $key => $value){
					//if data equal 1 get all items and increment 
					if($obj[$key] === '1'){
						$exclusionCount[$key]++;
					}
				}
			}	
			

			foreach($exclusionSetting as $field_name => $value){
				$exclusion_data->rows[] = [
					"$field_name",
					"",
					$exclusionCount[$field_name]
				];
			}

			$this->exclusion_data = $exclusion_data;

						
		}
		return $this->exclusion_data;

	}

	public function getCustomColors()
	{
		$color_settings = $this->getSubSettings('custom_accent_colors');


		foreach ($color_settings as $i => $customColor) {
			$color = new \stdClass();


			$color->site_name = $customColor['site_name'];
			$color->custom_logout = $customColor['custom_logout'];

			$color->header = $customColor['custom_header_color'];
			$color->bar = $customColor['custom_bar_color'];
			$color->secondaryBar = $customColor['custom_secondary_bar_color'];
			$color->text = $customColor['custom_text_color'];
			if ($color->text == "dark") {
				$color->text = "#000000";
			} elseif ($color->text == "light") {
				$color->text = "#ffffff";
			}
			$css_hex_color_pattern = "/#([[:xdigit:]]{3}){1,2}\b/";
			$input = "{$color->header}{$color->bar}{$color->secondaryBar}{$color->text}";
			if (!preg_match($css_hex_color_pattern, $input)) {
				// Defaults to these colors
				$color->header = "#eeeeee";
				$color->bar = "#055877";
				$color->secondaryBar = "#138085";
				$color->text = "#ffffff";
			}
			define("CUSTOM_SITE_NAME", $color->site_name);
			define("CUSTOM_LOGOUT", $color->custom_logout);
			define("LOGO_BACKGROUND_COLOR", $color->header);
			define("BAR_BACKGROUND_COLOR", $color->bar);
			define("SECONDARY_BAR_BACKGROUND_COLOR", $color->secondaryBar);
			define("TEXT_COLOR", $color->text);
		}
	}

	public function getCustomLogo()
	{
		$color_settings = $this->getSubSettings('custom_accent_colors');

		$stored_name = [];
		foreach ($color_settings as $i => $customLogo) {
			$logo = new \stdClass();

			//gets doc_id that is stored in redcap_edocs_metadata
			$logo->image = $customLogo['logo_upload'];

			//start query to pull latest doc_id
			$query = $this->createQuery();

			$query->add('
			select *
			from redcap_edocs_metadata 
			WHERE doc_id = ?', $logo->image);

			$result = $query->execute();

			while ($row = $result->fetch_assoc()) {
				//get latest image with base64_encode
				$imageData = base64_encode(file_get_contents(EDOC_PATH . $row['stored_name']));
				//use mime data to get src 
				$src = 'data: ' . $row['mime_type'] . ';base64,' . $imageData;
				//define constant called LOGO that is used on base.twig 
				define("LOGO", $src);
			}
		}
	}
}
