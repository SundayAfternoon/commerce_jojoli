<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Thomas Hempel <thomas@work.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Plugin 'addresses' for the 'commerce' extension.
 * This class handles all the address stuff, like creating, editing and deleting.
 *
 * @package		TYPO3
 * @subpackage	tx_commerce
 * @author		Thomas Hempel <thomas@work.de>
 * @maintainer	Thomas Hempel <thomas@work.de>
 *
 * $Id: class.tx_commerce_pi4.php 567 2007-03-05 07:47:13Z thomas $
 */

require_once(t3lib_extmgm::extPath('commerce') . 'lib/class.tx_commerce_pibase.php');
require_once (t3lib_extMgm::extPath('static_info_tables') . 'pi1/class.tx_staticinfotables_pi1.php');

class tx_commerce_pi4 extends tx_commerce_pibase {
	var $prefixId = 'tx_commerce_pi4'; // Same as class name
	var $scriptRelPath = 'pi4/class.tx_commerce_pi4.php'; // Path to this script relative to the extension dir.
	var $extKey = 'commerce'; // The extension key.
	var $imgFolder = '';
	var $user = NULL;
	var $addresses = array();
	var $formError = array();
	var $fieldList = array();
	var $sysMessage = '';

	/**
	 * Holding the Static_info Object
	 *
	 * @var Object
	 */
	var $staticInfo;

	/**
	 * If set to TRUE some debug message will be printed.
	 */
	var $debug = FALSE;

	/**
	 * Main method. Starts the magic...
	 *
	 * @param string $content: Content of this plugin
	 * @param array $conf: TS configuration for this plugin
	 * @return string Compiled content
	 */
	function main($content, $conf) {
		$this->init($conf);

		if (!$GLOBALS['TSFE']->loginUser) {
			return $this->noUser();
		}

		if (isset($this->piVars['check'])) {
			$formValid = $this->checkAddressForm();
		} else {
			$formValid = FALSE;
		}

		if ($formValid && isset($this->piVars['check']) && (int)$this->piVars['backpid'] != $GLOBALS['TSFE']->id) {
			unset($this->piVars['check']);
			header('Location: ' .
				t3lib_div::locationHeaderUrl(
					$this->pi_getPageLink((int)$this->piVars['backpid'],
					'',
					array(
						'tx_commerce_pi3[addressType]' => (int)$this->piVars['addressType'],
						$this->prefixId . '[addressid]' => (int)$this->piVars['addressid'])
					)
				)
			);
		}

		switch (strtolower($this->piVars['action'])) {
			case 'new':
				if ($formValid) {
					$this->sysMessage = $this->pi_getLL('message_address_new');
					$this->saveAddressData(TRUE, intval($this->piVars['addressType']));
					$content = $this->getListing();
					break;
				}
				$content = $this->getAddressForm('new', intval($this->piVars['addressid']), $this->conf);
			break;
			case 'delete':
				$addresses = $this->getAddresses($this->user['uid'], intval($this->addresses[$this->piVars['addressid']]['tx_commerce_address_type_id']));
				if (count($addresses) <= $this->conf['minAddressCount']) {
					$this->sysMessage = $this->pi_getLL('message_cant_delete');
					$content = $this->getListing();
					break;
				}
				if ($this->piVars['confirmed'] == 'yes') {
					$this->deleteAddress();
					$content = $this->getListing();
					break;
				}
				$content = $this->deleteAddressQuestion();
			break;
			case 'edit':
				if ($formValid) {
					$this->sysMessage = $this->pi_getLL('message_address_changed');
					$content = $this->getListing();
					break;
				}
				$content = $this->getAddressForm('edit', intval($this->piVars['addressid']), $this->conf);
			break;
			case 'listing':
			default:
				if ($formValid) {
					$this->saveAddressData(FALSE, intval($this->piVars['addressType']));
				}
				$content = $this->getListing();
			break;
		}

		return $this->pi_wrapInBaseClass($content);
	}


	/**
	 * Initialization. This method has to be called before the magic can start... ;-)
	 *
	 * @param array $conf TS configuration for this template
	 * @param boolean $getAddresses If this is set to TRUE, this method will fetch all addresses into $this->addresses (Default is TRUE)
	 * @return void
	 */
	function init($conf, $getAddresses = TRUE) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

		$this->staticInfo = t3lib_div::makeInstance('tx_staticinfotables_pi1');
		$this->staticInfo->init();

		if (!empty($this->piVars['addressType'])) {
			$addressType = intval($this->piVars['addressType']);
		}

		switch ($addressType) {
			case '2':
				$addressType = 'delivery';
			break;
			default:
				$addressType = 'billing';
			break;
		}

		if (!is_array($this->conf['formFields.'])) {
			if (is_array($this->conf[$addressType . '.']['formFields.'])) {
				$this->conf['formFields.'] = $this->conf[$addressType . '.']['formFields.'];
			}
		}

		$this->fieldList = $this->parseFieldList($this->conf['formFields.']);

		// Get the template
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

		// Clear form errors
		$this->formError = array();

		// Check for logged in user
		if (empty($GLOBALS['TSFE']->fe_user->user)) {
			return $this->pi_getLL('not_logged_in');
		} else {
			$this->user = $GLOBALS['TSFE']->fe_user->user;
		}

		if (isset($this->piVars['check']) && $this->piVars['action'] == 'edit' && $this->checkAddressForm()) {
			$this->saveAddressData(FALSE, intval($this->piVars['addressType']));
		}

		// Get addresses of this user
		if ($getAddresses) {
			$this->addresses = $this->getAddresses($this->user['uid']);
		}
	}


	/**
	 * Is called whenever the address handling is called without a logged in fe_user.
	 * Currently this is just a dummy with no function.
	 *
	 * TODO: Here we could return a template and / or call a hook
	 *
	 * @return void
	 */
	function noUser() {
	}


	/**
	 * Returns the listing HTML of addresses.
	 *
	 * @param integer $addressType Type of addresses that should be returned. If this is 0 all types will be returned
	 * @param boolean $createHiddenFields Create hidden fields
	 * @param string $hiddenPrefix Prefix for field names
	 * @param integer $selectAdressId Adress ID which should be selected by default
	 * @return string HTML with addresses
	 */
	function getListing($addressType = 0, $createHiddenFields = FALSE, $hiddenFieldPrefix = '', $selectAddressId = FALSE) {
		$hookObjectsArr = array();
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['getListing']))	{
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['getListing'] as $classRef)	{
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}

		if ($this->conf[$addressType . '.']['subpartMarker.']['listWrap']) {
			$tplBase = $this->cObj->getSubpart($this->templateCode, strtoupper($this->conf[$addressType . '.']['subpartMarker.']['listWrap']));
		} else {
			$tplBase = $this->cObj->getSubpart($this->templateCode, '###ADDRESS_LISTING###');
		}

		if ($this->conf[$addressType . '.']['subpartMarker.']['listItem']) {
			$tplItem = $this->cObj->getSubpart($this->templateCode, strtoupper($this->conf[$addressType . '.']['subpartMarker.']['listItem']));
		} else {
			$tplItem = $this->cObj->getSubpart($this->templateCode, '###ADDRESS_ITEM###');
		}

		if (!is_array($this->conf['formFields.'])) {
			if (is_array($this->conf[$addressType . '.']['formFields.'])) {
				$this->conf['formFields.'] = $this->conf[$addressType . '.']['formFields.'];
			}
		}

		// Set prefix if not set
		if (empty($hiddenFieldPrefix)) {
			$hiddenFieldPrefix = $this->prefixId;
		}
		if ($this->piVars['addressid']) {
			// Set var editAddressId for checked
			$editAddressId = (int)$this->piVars['addressid'];
		} elseif ($selectAddressId) {
			$editAddressId = (int)$selectAddressId;
		}

		// Unset some piVars we don't need here
		unset($this->piVars['check']);
		unset($this->piVars['addressid']);
		unset($this->piVars['ismainaddress']);

		foreach($this->fieldList as $name) {
			unset($this->piVars[$name]);
		}

		// Get all addresses for the desired address types
		$addressTypes = t3lib_div::trimExplode(',', $this->conf['selectAddressTypes']);

		// Count different address types
		$addressTypeCounter = array();
		foreach($this->addresses as $address) {
			$addressTypeCounter[$address['tx_commerce_address_type_id']]++;
		}

		// @TODO FIXME Initializad as string, but used as array
		$addressItems = '';

		foreach($this->addresses as $address) {
			if ($addressType > 0 && $address['tx_commerce_address_type_id'] != $addressType) {
				continue;
			}

			$itemMA = array();
			$linkMA = array();

			// Fill marker array
			$address = tx_commerce_div::removeXSSStripTagsArray($address);
			foreach($address as $key => $value) {
				$valueHidden = '';
				$upperKey = strtoupper($key);

				if ($this->conf['hideEmptyFields'] && empty($value)) {
					continue;
				}

				if ($value === "") {
					$value = $this->conf['emptyFieldSign'];
				}

				// Get value from database if the field is a select box
				if ($this->conf['formFields.'][$key . '.']['type'] == 'select') {
					$fieldConfig = $this->conf['formFields.'][$key . '.'];
					$table = $fieldConfig['table'];
					$select = $fieldConfig['value'] . '=\'' . $value . '\'' . $this->cObj->enableFields($fieldConfig['table']);
					$fields = $fieldConfig['label'] . ' AS label,';
					$fields.= $fieldConfig['value'] . ' AS value';
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						$fields,
						$table,
						$select
					);
					$value = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
					$valueHidden = $value['value'];
					$value = $value['label'];
				}

				if ($this->conf['formFields.'][$key . '.']['type'] == 'static_info_tables') {
					$fieldConfig = $this->conf['formFields.'][$key . '.'];
					$field = $fieldConfig['field'];
					$valueHidden = $value;
					$value = $this->staticInfo->getStaticInfoName($field, $value);
				}

				$hidden = '';
				if ($createHiddenFields) {
					$hidden = '<input type="hidden" name="' . $hiddenFieldPrefix . '[' . $address['uid'] . '][' . $key . ']" value="' . ($valueHidden ? $valueHidden : $value) . '" />';
				}

				$itemMA['###LABEL_' . $upperKey . '###'] = $this->pi_getLL('label_' . $key);
				$itemMA['###' . $upperKey . '###'] = $value . $hidden;
			}

			// Create a pivars array for merging with link to edit page
			if ($this->conf['editAddressPid'] > 0) {
				$piArray = array('backpid' => $GLOBALS['TSFE']->id);
				$linkTarget = $this->conf['editAddressPid'];
			} else {
				$piArray = array('backpid' => $GLOBALS['TSFE']->id);
				$linkTarget = $this->conf['addressMgmPid'];
			}

			if ($this->debug) {
				debug($this->conf, 'conf', __LINE__, __FILE__);
			}

			// Set delete link only if addresses may be deleted, otherwise set it empty
			if ((int)$addressTypeCounter[$address['tx_commerce_address_type_id']] > (int)$this->conf['minAddressCount']) {
				$linkMA['###LINK_DELETE###'] = explode('|', $this->pi_linkTP_keepPIvars('|', array('action' => 'delete', 'addressid' => $address['uid'])));
				$itemMA['###LABEL_LINK_DELETE###'] = $this->cObj->stdWrap($this->pi_getLL('label_link_delete'), $this->conf['deleteLinkWrap.']);
			} else {
				$linkMA['###LINK_DELETE###'][0] = '';
				$linkMA['###LINK_DELETE###'][1] = '';
				$itemMA['###LABEL_LINK_DELETE###'] = '';
			}

			$linkMA['###LINK_EDIT###'] = explode('|', $this->pi_linkTP_keepPIvars('|', array_merge($piArray, array('action' => 'edit', 'addressid' => $address['uid'], 'addressType' => $address['tx_commerce_address_type_id'])), FALSE, FALSE, $linkTarget));
			$itemMA['###LABEL_LINK_EDIT###'] = $this->cObj->stdWrap($this->pi_getLL('label_link_edit'), $this->conf['editLinkWrap.']);
			// add an edit radio button, checked selected previously
			$itemMA['###SELECT###'] = '<input type="radio" ';

			if (($editAddressId == $address['uid']) || (empty($editAddressId) && $address['tx_commerce_is_main_address'])) {
				$itemMA['###SELECT###'].= 'checked="checked" ';
			}

			$itemMA['###SELECT###'].= 'name="' . $hiddenFieldPrefix . '[address_uid]" value="' . $address['uid'] . '" />';

			foreach($hookObjectsArr as $hookObj)	{
				if (method_exists($hookObj, 'processAddressMarker'))	{
					$itemMA = $hookObj->processAddressMarker($itemMA, $address, $piArray, $this);
				}
			}

			$addressFound = TRUE;

			$addressItems[$address['tx_commerce_address_type_id']].= $this->substituteMarkerArrayNoCached($tplItem, $itemMA, array(), $linkMA);
		}

		$linkMA = array();

		// Create a pivars array for merging with link to edit page
		if ($this->conf['editAddressPid'] > 0) {
			$piArray = array('backpid' => $GLOBALS['TSFE']->id);
			$linkTarget = $this->conf['editAddressPid'];
		} else {
			$piArray = array();
			$linkTarget = $this->conf['addressMgmPid'];
		}

		// Create links and labels for every address type
		if ($addressType == 0) {
			foreach($addressTypes as $addressType) {
				$baseMA['###ADDRESS_ITEMS_OF_TYPE_' . $addressType . '###'] = $addressItems[$addressType];
				$baseMA['###LABEL_ADDRESSES_OF_TYPE_' . $addressType . '###'] = $this->pi_getLL('label_addresses_of_type_' . $addressType);
				$linkMA['###LINK_NEW_TYPE_' . $addressType . '###'] = explode('|', $this->pi_linkTP_keepPIvars('|', array_merge($piArray, array('action' => 'new', 'addressType' => $addressType)), FALSE, FALSE, $linkTarget));
				$baseMA['###LABEL_LINK_NEW_TYPE_' . $addressType . '###'] = $this->cObj->stdWrap($this->pi_getLL('label_link_new_type_' . $addressType), $this->conf['newLinkWrap.']);
			}
		} else {
			$baseMA['###ADDRESS_ITEMS###'] = $addressItems[$addressType];
			$linkMA['###LINK_NEW###'] = explode('|', $this->pi_linkTP_keepPIvars('|', array_merge($piArray, array('action' => 'new', 'addressType' => $addressType)), FALSE, FALSE, $linkTarget));
			$baseMA['###LABEL_LINK_NEW###'] = $this->cObj->stdWrap($this->pi_getLL('label_link_new'), $this->conf['newLinkWrap.']);
		}

		if (!$addressFound) {
			$baseMA['###NO_ADDRESS###'] = $this->cObj->stdWrap($this->pi_getLL('label_no_address'), $this->conf['noAddressWrap.']);
		} else {
			$baseMA['###NO_ADDRESS###'] = '';
		}

		// Fill sysMessage marker if set
		if (!empty($this->sysMessage)) {
			$baseMA['###SYS_MESSAGE###'] = $this->cObj->stdWrap($this->sysMessage, $this->conf['sysMessageWrap.']);
		} else {
			$baseMA['###SYS_MESSAGE###'] = '';
		}

		foreach($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'processListingMarker'))	{
				$hookObj->processListingMarker($baseMA, $linkMA, $addressItems, $addressType, $piArray, $this);
			}
		}

		// Replace markers and return content
		return $this->substituteMarkerArrayNoCached($tplBase, $baseMA, array(), $linkMA);
	}


	/**
	 * Returns HTML form for a single address. The fields are fetched from
	 * tt_address and configured in TS.
	 *
	 * @param string $action Action that should be performed (can be "new" or "edit")
	 * @param integer $addressUid UID of the page where the addresses are stored
	 * @param array $config Configuration array for all fields
	 * @return string HTML code with the form for editing an address
	 */
	function getAddressForm($action = 'new', $addressUid = NULL, $config) {
		$hookObjectsArr = array();
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['getAddressFormItem']))	{
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['getAddressFormItem'] as $classRef)	{
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}

		if (!empty($this->piVars['addressType'])) {
			$addressType = intval($this->piVars['addressType']);
		}

		switch ($addressType) {
			case '2':
				$addressType = 'delivery';
			break;
			default:
				$addressType = 'billing';
			break;
		}

		// Build query to select an address from the database if we have a logged in user
		$addressData = ($addressUid != NULL) ? $this->addresses[$addressUid] : array();

		if ($this->debug) {
			debug($config, 'PI4 config');
		}

		if (count($this->formError) > 0) {
			$addressData = $this->piVars;
		}
		$addressData = tx_commerce_div::removeXSSStripTagsArray($addressData);
		if ($addressData['tx_commerce_address_type_id'] == NULL) {
			$addressData['tx_commerce_address_type_id'] = (int)$this->piVars['addressType'];
		}

		// Get the templates
		if ($this->conf[$addressType . '.']['subpartMarker.']['editWrap']) {
			$tplBase = $this->cObj->getSubpart($this->templateCode, strtoupper($this->conf[$addressType . '.']['subpartMarker.']['editWrap']));
		} else {
			$tplBase = $this->cObj->getSubpart($this->templateCode, '###ADDRESS_EDIT###');
		}
		if ($this->conf[$addressType . '.']['subpartMarker.']['editItem']) {
			$tplForm = $this->cObj->getSubpart($this->templateCode, strtoupper($this->conf[$addressType . '.']['subpartMarker.']['editItem']));
		} else {
			$tplForm = $this->cObj->getSubpart($this->templateCode, '###ADDRESS_EDIT_FORM###');
		}
		if ($this->conf[$addressType . '.']['subpartMarker.']['editField']) {
			$tplField = $this->cObj->getSubpart($this->templateCode, strtoupper($this->conf[$addressType . '.']['subpartMarker.']['editField']));
		} else {
			$tplField = $this->cObj->getSubpart($this->templateCode, '###SINGLE_INPUT###');
		}

		// Create form fields
		$fieldsMarkerArray = array();
		foreach($this->fieldList as $fieldName) {
			$fieldMA = array();
			$lowerName = strtolower($fieldName);
			$upperName = strtoupper($fieldName);

			// Get field label
			$fieldLabel = $this->pi_getLL('label_' . $lowerName, $fieldName);

			// Check if the field is manadatory and append the mandatory sign to the label
			if ($config['formFields.'][$fieldName . '.']['mandatory'] == '1') {
				$fieldLabel.= ' ' . $config['mandatorySign'];
			}

			// Insert error message for this specific field
			if (strlen($this->formError[$fieldName]) > 0) {
				$fieldMA['###FIELD_ERROR###'] = $this->formError[$fieldName];
			} else {
				$fieldMA['###FIELD_ERROR###'] = '';
			}

			// Create input field
			// In this version we only create some simple text fields.
			$fieldMA['###FIELD_INPUT###'] = $this->getInputField($fieldName, $config['formFields.'][$fieldName . '.'], $addressData[$fieldName]);

			// Get field item
			$fieldsMarkerArray['###FIELD_' . strtoupper($fieldName) . '###'] = $this->cObj->substituteMarkerArray($tplField, $fieldMA);
			$fieldsMarkerArray['###LABEL_' . strtoupper($fieldName) . '###'] = $fieldLabel;
		}

		foreach($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'processAddressfieldsMarkerArray'))	{
				$fieldsMarkerArray = $hookObj->processAddressfieldsMarkerArray($fieldsMarkerArray, $tplField, $addressData, $action, $addressUid, $config , $this);
			}
		}

		// Merge fields with form template
		$formCode = $this->cObj->substituteMarkerArray($tplForm, $fieldsMarkerArray);

		// Create submit button and some hidden fields
		$submitCode = '<input type="hidden" name="' . $this->prefixId . '[action]" value="' . $action . '" />';
		$submitCode.= '<input type="hidden" name="' . $this->prefixId . '[addressid]" value="' . $addressUid . '" />';
		$submitCode.= '<input type="hidden" name="' . $this->prefixId . '[addressType]" value="' . $addressData['tx_commerce_address_type_id'] . '" />';
		$submitCode.= '<input type="submit" name="' . $this->prefixId . '[check]" value="' . $this->pi_getLL('label_submit_edit') . '" />';

		// Create a checkbox where the user can select if the address is his main address / Changed to label and field
		$isMainAddressCodeField = '<input type="checkbox" name="' . $this->prefixId . '[ismainaddress]"';
		if ($addressData['tx_commerce_is_main_address'] || $addressData['ismainaddress']) {
			$isMainAddressCodeField.= ' checked="checked"';
		}
		$isMainAddressCodeField.= ' />';
		$isMainAddressCodeLabel.= $this->pi_getLL('label_is_main_address');

		//Fill additional information
		if ($addressData['tx_commerce_address_type_id'] == 1) {
			$baseMA['###MESSAGE_EDIT###'] = $this->pi_getLL('message_edit_billing');
		} elseif ($addressData['tx_commerce_address_type_id'] == 2) {
			$baseMA['###MESSAGE_EDIT###'] = $this->pi_getLL('message_edit_delivery');
		} else {
			$baseMA['###MESSAGE_EDIT###'] = $this->pi_getLL('message_edit_unknown');
		}

		// Fill the marker
		$baseMA['###ADDRESS_FORM_FIELDS###'] = $formCode;
		$baseMA['###ADDRESS_FORM_SUBMIT###'] = $submitCode;
		$baseMA['###ADDRESS_FORM_IS_MAIN_ADDRESS_FIELD###'] = $isMainAddressCodeField;
		$baseMA['###ADDRESS_FORM_IS_MAIN_ADDRESS_LABEL###'] = $isMainAddressCodeLabel;

		// @Deprecated Obsolete Marker, use Field and label instead
		$baseMA['###ADDRESS_FORM_IS_MAIN_ADDRESS###'] = $isMainAddressCodeField . ' ' . $isMainAddressCodeLabel;
		$baseMA['###ADDRESS_TYPE###'] = $this->pi_getLL('label_address_of_type_' . $this->piVars['addressType']);

		// Get action link
		if ((int)$this->piVars['backpid'] > 0) {
			$link = $this->pi_linkTP_keepPIvars_url();
		} else {
			$link = '';
		}

		$baseMA['###ADDRESS_FORM_BACK###'] = $this->pi_linkToPage(
			$this->pi_getLL('label_form_back', 'back'),
			$this->piVars['backpid'],
			'',
			array(
				'tx_commerce_pi3' => array(
					'step' => $GLOBALS['TSFE']->fe_user->getKey('ses', tx_commerce_div::generateSessionKey('currentStep'))
				)
			)
		);

		foreach($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'processAddressFormMarker')) {
				$hookObj->processAddressFormMarker($baseMA, $action, $addressUid, $addressData, $config, $this);
			}
		}

		return '<form method="post" action="' . $link . '" ' . $this->conf[$addressType . '.']['formParams'] . '>' . $this->cObj->substituteMarkerArray($tplBase, $baseMA) . '</form>';
	}


	/**
	 * Returns HTML code for a confirmation if the user wants to delete one of his addresses.
	 *
	 * @return string The HTML source with the delete confirmation form
	 */
	function deleteAddressQuestion() {
		$tplBase = $this->cObj->getSubpart($this->templateCode, '###ADDRESS_DELETE###');

		// Fill address data to marker
		foreach($this->fieldList as $name) {
			$baseMA['label_' . $name] = $this->pi_getLL('label_' . $name);
			$baseMA[$name] = $this->addresses[intval($this->piVars['addressid']) ][$name];
		}

		$baseMA['QUESTION'] = $this->pi_getLL('question_delete');
		$baseMA['YES'] = $this->cObj->stdWrap($this->pi_getLL('label_submit_yes'), $this->conf['yesLinkWrap.']);
		$baseMA['NO'] = $this->cObj->stdWrap($this->pi_getLL('label_submit_no'), $this->conf['noLinkWrap.']);
		$linkMA['###LINK_YES###'] = explode('|', $this->pi_linkTP_keepPIvars('|', array('action' => 'delete', 'confirmed' => 'yes')));
		$linkMA['###LINK_NO###'] = explode('|', $this->pi_linkTP_keepPIvars('|', array('action' => 'listing')));

		$content = $this->cObj->substituteMarkerArray($tplBase, $baseMA, '###|###', 1);

		return $this->substituteMarkerArrayNoCached($content, array(), array(), $linkMA);
	}


	/**
	 * Deletes an address from the database. It doesn't delete the dataset in real, but it sets the deleted flag like it's
	 * done inside TYPO3.
	 * This method has no params, because it currently gets the data from piVars.
	 *
	 * @return unknown
	 */
	function deleteAddress() {
		if (!in_array(intval($this->piVars['addressid']), array_keys($this->addresses))) {
			return TRUE;
		}

		// Hook to delete an address
		$hookObjectsArr = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['deleteAddress'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['deleteAddress'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}
		foreach($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'deleteAddress')) {
				$message = $hookObj->deleteAddress((int)$this->piVars['addressid'], $this);
			}
		}

		if ($message) {
			$this->sysMessage = $message;
			return TRUE;
		}

		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'tt_address',
			'uid=' . intval($this->piVars['addressid']),
			array(
				'deleted' => 1
			)
		);

		unset($this->addresses[intval($this->piVars['addressid']) ]);
		unset($this->piVars['confirmed']);
	}


	/**
	 * Returns a single input form field.
	 * This is just a switch between the specific methods.
	 *
	 * @param string $fieldname Name of the field
	 * @param array $fieldConf Configuration for this field (usually from TypoScript)
	 * @param string $fieldValue Current value of this field (usually fetched from piVars)
	 * @return string Result of the specific field methods (usually a html string)
	 */
	function getInputField($fieldName, $fieldConfig, $fieldValue = '') {
		$content = '';
		switch (strtolower($fieldConfig['type'])) {
			case 'select':
				$content = $this->getSelectInputField($fieldName, $fieldConfig, $fieldValue);
			break;
			case 'static_info_tables':
				$selected = $fieldValue != '' ? $fieldValue : $fieldConfig['default'];
				$content = $this->staticInfo->buildStaticInfoSelector(
					$fieldConfig['field'],
					$this->prefixId . '[' . $fieldName . ']',
					$fieldConfig['cssClass'],
					$selected,
					'',
					'',
					'',
					'',
					$fieldConfig['select'],
					$GLOBALS['TSFE']->tmpl->setup['config.']['language']
				);
			break;
			case 'check':
				$content = $this->getCheckboxInputField($fieldName, $fieldConfig, $fieldValue);
			break;
			case 'single':
			default:
				$content = $this->getSingleInputField($fieldName, $fieldConfig, $fieldValue);
			break;
		}

		/** * Hook for processing the content
		*/
		$hookObjectsArr = array();
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi2/class.tx_commerce_pi2.php']['getInputField'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi2/class.tx_commerce_pi2.php']['getInputField'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}
		foreach($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'postGetInputField')) {
				$content = $hookObj->postGetInputField($content, $fieldName, $fieldConfig, $fieldValue, $this);
			}
		}

		return $content;
	}


	/**
	 * Returns a single textfield
	 *
	 * @param string $fieldname Name of the field
	 * @param array $fieldConf Configuration for this field (normally from TypoScript)
	 * @param string $fieldValue Current value of this field (Normally fetched from piVars)
	 * @return string A single field with type = text
	 */
	function getSingleInputField($fieldName, $fieldConfig, $fieldValue = '') {
		if (($fieldConfig['default']) && empty($fieldValue)) {
			$value = $fieldConfig['default'];
		} else {
			$value = t3lib_div::removeXSS(strip_tags($fieldValue));
		}

		$result = '<input type="text" name="' . $this->prefixId . '[' . $fieldName . ']" value="' . $value . '" ';

		if ($fieldConfig['readonly'] == 1) {
			$result.= 'readonly="readonly" disabled="disabled" ';
		}
		if (isset($fieldConfig['class'])) {
			$result.= 'class="' . $fieldConfig['class'] . '" ';
		}

		$result.= '/>';

		return $result;
	}


	/**
	 * Create a selectbox
	 *
	 * @param string $fieldname Name of the field
	 * @param array $fieldConf Configuration for this field (normally from TypoScript)
	 * @param string $fieldValue Current value of this field (Normally fetched from piVars)
	 * @return string HTML code for a select box with a set of options
	 */
	function getSelectInputField($fieldName, $fieldConfig, $fieldValue = '') {
		$result = '<select name="' . $this->prefixId . '[' . $fieldName . ']">';

		if ($fieldValue != '') {
			$fieldConfig['default'] = $fieldValue;
		}

		// If static items are set
		if (is_array($fieldConfig['values.'])) {
			foreach($fieldConfig['values.'] as $key => $option) {
				$result.= '<option name="' . $key . '" value="' . $key . '"';
				if ($fieldValue === $key) $result.= ' selected="selected"';
				$result.= '>' . $option . '</option>' . "\n";
			}
		} else {
			// Fetch data from database
			$table = $fieldConfig['table'];
			$select = $fieldConfig['select'] . $this->cObj->enableFields($fieldConfig['table']);
			$fields = $fieldConfig['label'] . ' AS label,' . $fieldConfig['value'] . ' AS value';
			$orderby = $fieldConfig['orderby'];
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				$fields,
				$table,
				$select,
				'',
				$orderby
			);
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$result.= '<option name="' . $row['value'] . '" value="' . $row['value'] . '"';
				if ($row['value'] === $fieldConfig['default']) {
					$result.= ' selected="selected"';
				}
				$result.= '>' . $row['label'] . '</option>' . "\n";
			}
		}
		$result.= '</select>';

		return $result;
	}


	/**
	 * Returns a checkbox
	 *
	 * @param string $fieldname Name of the field
	 * @param array $fieldConf Configuration for this field (normally from TypoScript)
	 * @param string $fieldValue Current value of this field (Normally fetched from piVars)
	 * @return string A single checkbox
	 */
	function getCheckboxInputField($fieldName, $fieldConfig, $fieldValue = '') {
		$result = '<input type="checkbox" name="' . $this->prefixId . '[' . $fieldName . ']" id="' . $this->prefixId . '[' . $step . '][' . $fieldName . ']" value="1" ';

		if (($fieldConfig['default'] == '1' && $fieldValue != 0) || $fieldValue == 1) {
			$result.= 'checked="checked" ';
		}

		$result.= ' /> ';

		if ($fieldConfig['additionalinfo'] != '') {
			$result.= $fieldConfig['additionalinfo'];
		}

		return $result;
	}


	/**
	 *
	 * @TODO Fix denglish
	 *
	 * Reads in the complete configuration for a form, and parses the data that come from the piVars
	 * and checks if this values fit the configuration for the field.
	 * If errors occur, it writes it into a class var called formError. The key will be the name of the
	 * field and the value will be the error message.
	 *
	 * @return void
	 */
	function checkAddressForm() {
		$hookObjectsArr = array();
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['checkAddressForm'])){
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['checkAddressForm'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}

		$this->formError = array();
		$config = $this->conf['formFields.'];
		$result = TRUE;

		// If the address doesn't exsist in session it's valid. In case no delivery address was set.
		foreach($this->fieldList as $name) {
			$value = $this->piVars[$name];
			$options = $this->conf['formFields.'][$name . '.'];

			if ($options['mandatory'] == 1 && strlen($value) == 0) {
				$this->formError[$name] = $this->pi_getLL('error_field_mandatory');
				$result = FALSE;
			}

			$eval = explode(',', $config[$name . '.']['eval']);
			foreach($eval as $method) {
				$method = explode('_', $method);
				switch (strtolower($method[0])) {
					case 'email':
						if (!empty($value) && !t3lib_div::validEmail($value)) {
							$this->formError[$name] = $this->pi_getLL('error_field_email');
							$result = FALSE;
						}
					break;
					case 'string':
						if (!is_string($value)) {
							$this->formError[$name] = $this->pi_getLL('error_field_string');
							$result = FALSE;
						}
					break;
					case 'int':
						if (!is_integer($value)) {
							$this->formError[$name] = $this->pi_getLL('error_field_int');
							$result = FALSE;
						}
					break;
					case 'min':
						if (strlen((string)$value) < intval($method[1])) {
							$this->formError[$name] = sprintf($this->pi_getLL('error_field_min'), $method[1]);
							$result = FALSE;
						}
					break;
					case 'max':
						if (strlen((string)$value) > intval($method[1])) {
							$this->formError[$name] = sprintf($this->pi_getLL('error_field_max'), $method[1]);
							$result = FALSE;
						}
					break;
					case 'alpha':
						if (preg_match('/[0-9]/', $value) === 1) {
							$this->formError[$name] = $this->pi_getLL('error_field_alpha');
							$result = FALSE;
						}
					break;
					default:
						if (!empty($method[0])) {
							$act_method = 'validationMethod_' . strtolower($method[0]);
							foreach ($hookObjectsArr as $hookObj) {
								if (method_exists($hookObj, $act_method)) {
									if (!$hookObj->$act_method($this,$name,$value)) {
										$result = false;
									}
								}
							}
						}
				}
			}
		}

		return $result;
	}


	/**
	 * Save some data from piVars as address into database.
	 *
	 * @param boolean $new If this is TRUE, a new address will be created, otherwise it searches for an existing dataset and updates it
	 * @param integer $addressType Type of address delivered by piVars
	 * @return void
	 */
	function saveAddressData($new = FALSE, $addressType = 0) {
		$newData = array();

		// Set basic data
		if (empty($addressType)) $addressType = 0;
		if ($this->piVars['ismainaddress'] == 'on') {
			$newData['tx_commerce_is_main_address'] = 1;
			// Remove all "is main address" flags from addresses that are assigned to this user
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tt_address',
				'pid=' . $this->conf['addressPid'] . ' AND tx_commerce_fe_user_id=' . $this->user['uid'] . ' AND tx_commerce_address_type_id=' . $addressType,
				array('tx_commerce_is_main_address' => 0)
			);
		} else {
			$newData['tx_commerce_is_main_address'] = 0;
		}

		$newData['tstamp'] = time();

		if ($this->debug) {
			debug($newData,'newdata');
		}

		foreach($this->fieldList as $name) {
			$newData[$name] = t3lib_div::removeXSS(strip_tags($this->piVars[$name]));
			if (!$new) {
				$this->addresses[intval($this->piVars['addressid']) ][$name] = t3lib_div::removeXSS(strip_tags($this->piVars[$name]));
			}
		}


		// Hook to process new/changed address
		$hookObjectsArr = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['saveAddress'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['saveAddress'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}

		if ($new) {
			$newData['tx_commerce_fe_user_id'] = $this->user['uid'];
			$newData['tx_commerce_address_type_id'] = $addressType;
			$newData['pid'] = $this->conf['addressPid'];

			foreach($hookObjectsArr as $hookObj) {
				if (method_exists($hookObj, 'beforeAddressSave')) {
					$hookObj->beforeAddressSave($newData, $this);
				}
			}

			$GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'tt_address',
				$newData
			);
			$newUid = $GLOBALS['TYPO3_DB']->sql_insert_id();

			foreach($hookObjectsArr as $hookObj) {
				if (method_exists($hookObj, 'afterAddressSave')) {
					$hookObj->afterAddressSave($newUid, $newData, $this);
				}
			}

			$this->addresses = $this->getAddresses($this->user['uid']);
		} else {
			foreach($hookObjectsArr as $hookObj) {
				if (method_exists($hookObj, 'beforeAddressEdit')) {
					$hookObj->beforeAddressEdit((int)$this->piVars['addressid'], $newData, $this);
				}
			}

			$sWhere = 'uid=' . intval($this->piVars['addressid']) . " AND  tx_commerce_fe_user_id = " . $GLOBALS["TSFE"]->fe_user->user["uid"] . ' ';

			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'tt_address',
				$sWhere,
				$newData
			);

			foreach($hookObjectsArr as $hookObj) {
				if (method_exists($hookObj, 'afterAddressEdit')) {
					$hookObj->afterAddressEdit((int)$this->piVars['addressid'], $newData, $this);
				}
			}
		}
	}


	/**
	 * Create a list of array keys where the last character is removed from it.
	 *
	 * @param array $dataArray Array where the keys should be cleaned
	 * @return array An array with the cleaned arraykeys or the orginal data if it was no array
	 */
	function parseFieldList($dataArray) {
		$result = array();

		if (!is_array($dataArray)) {
			return $result;
		}

		foreach($dataArray as $key => $data) {
			// remove the trailing '.'
			$result[] = substr($key, 0, -1);
		}

		return $result;
	}


	/**
	 * Get all addresses from the database that are assigned to the current user.
	 *
	 * @param integer $userId UID of the user
	 * @param integer $addressType Type of addresses to retrieve (0 (default) means get all types)
	 * @return array Keys with UIDs and values with complete addresses data
	 */
	function getAddresses($userId, $addressType = 0) {
		$select = 'tx_commerce_fe_user_id=' . intval($userId) . t3lib_Befunc::BEenableFields('tt_address');

		if ($addressType > 0) {
			$select.= ' AND tx_commerce_address_type_id=' . intval($addressType);
		} elseif (isset($this->conf['selectAddressTypes'])) {
			$select.= ' AND tx_commerce_address_type_id IN (' . $this->conf['selectAddressTypes'] . ')';
		} else {
			$this->addresses = array();
			return;
		}

		$select.= ' AND deleted=0 AND pid=' . $this->conf['addressPid'];

		/** * Hook for adding select statement
		*/
		$hookObjectsArr = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['getAddresses'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi4/class.tx_commerce_pi4.php']['getAddresses'] as $classRef) {
 	 			$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
 	 		}
 	 	}
 	 	foreach($hookObjectsArr as $hookObj) {
 	 		if (method_exists($hookObj, 'editSelectStatement')) {
 	 			$select = $hookObj->editSelectStatement($select, $userId, $addressType, $this);
 	 		}
 	 	}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tt_address',
			$select,
			'',
			'tx_commerce_is_main_address desc'
		);

		$result = array();
		while ($address = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$result[$address['uid']] = tx_commerce_div::removeXSSStripTagsArray($address);
		}

		return $result;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/commerce/pi4/class.tx_commerce_pi4.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/commerce/pi4/class.tx_commerce_pi4.php']);
}
?>
