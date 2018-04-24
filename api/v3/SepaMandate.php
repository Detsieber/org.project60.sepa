<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


/**
 * File for the CiviCRM APIv3 sepa_mandate functions
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Add an SepaCreditor for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaCreditorCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_mandate_create}
 * @access public
 */
function civicrm_api3_sepa_mandate_create($params) {
  _civicrm_api3_sepa_mandate_adddefaultcreditor($params);
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_mandate_create_spec(&$params) {
//  $params['reference']['api.required'] = 1; generated by the BAO
  $params['entity_id']['api.required'] = 1;
  $params['entity_table']['api.required'] = 1;
  $params['type']['api.required'] = 1;
  $params['is_enabled']['api.default'] = false;
  $params['status']['api.default'] = "INIT";

}


/**
 * Creates a mandate object along with its "contract",
 * i.e. the payment details as recorded in an
 * associated contribution or recurring contribution
 *
 * @author endres -at- systopia.de
 *
 * @return array API result array
 */
function civicrm_api3_sepa_mandate_createfull($params) {
    // create the "contract" first: a contribution
    // TODO: sanity checks
    _civicrm_api3_sepa_mandate_adddefaultcreditor($params);
    $create_contribution = $params; // copy array
    $create_contribution['version'] = 3;
    if (isset($create_contribution['contribution_contact_id'])) {
    	// in case someone wants another contact for the contribution than for the mandate...
    	$create_contribution['contact_id'] = $create_contribution['contribution_contact_id'];
    }
	if (empty($create_contribution['currency']))
		$create_contribution['currency'] = 'EUR'; // set default currency
	if (empty($create_contribution['contribution_status_id']))
		$create_contribution['contribution_status_id'] = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');

    if ($params['type']=='RCUR') {
    	$contribution_entity = 'ContributionRecur';
	    $contribution_table  = 'civicrm_contribution_recur';
      	$create_contribution['payment_instrument_id'] =
      		(int) CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name');
      	if (empty($create_contribution['status']))
      		$create_contribution['status'] = 'FRST'; // set default status
      	if (empty($create_contribution['is_pay_later']))
      		$create_contribution['is_pay_later'] = 1; // set default pay_later

    } elseif ($params['type']=='OOFF') {
	 	$contribution_entity = 'Contribution';
	    $contribution_table  = 'civicrm_contribution';
      	$create_contribution['payment_instrument_id'] =
      		(int) CRM_Core_OptionGroup::getValue('payment_instrument', 'OOFF', 'name');
      	if (empty($create_contribution['status']))
      		$create_contribution['status'] = 'OOFF'; // set default status
      	if (empty($create_contribution['total_amount']))
      		$create_contribution['total_amount'] = $create_contribution['amount']; // copy from amount

    } else {
    	return civicrm_api3_create_error('Unknown mandata type: '.$params['type']);
    }

    // create the contribution
    $contribution = civicrm_api($contribution_entity, "create", $create_contribution);
    if (!empty($contribution['is_error'])) {
    	return $contribution;
    }

    // create the mandate object itself
    // TODO: sanity checks
    $create_mandate = $create_contribution; // copy array
    $create_mandate['version'] = 3;
    $create_mandate['entity_table'] = $contribution_table;
    $create_mandate['entity_id'] = $contribution['id'];
    $mandate = civicrm_api("SepaMandate", "create", $create_mandate);
    if (!empty($mandate['is_error'])) {
    	// this didn't work, so we also have to roll back the created contribution
    	$delete = civicrm_api($contribution_entity, "delete", array('id'=>$contribution['id'], 'version'=>3));
    	if (!empty($delete['is_error'])) {
    		error_log("org.project60.sepa: createfull couldn't roll back created contribution: ".$delete['error_message']);
    	}
    }
	return $mandate;
}

/**
 * API specs for updating mandates
 */
function _civicrm_api3_sepa_mandate_createfull_spec(&$params) {
  $params['type']['api.required'] = 1;
  $params['amount']['api.required'] = 1;
  $params['reference']['api.required'] = 0;
  $params['status']['api.required'] = 0;
  $params['start_date']['api.required'] = 0;
  $params['date']['api.required'] = 0;
  $params['financial_type_id']['api.required'] = 0;
  $params['campaign_id']['api.required'] = 0;
  $params['creditor_id']['api.required'] = 0;
}


/**
 * Deletes an existing Mandate
 *
 * @param  array  $params
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_mandate_delete}
 * @access public
 */
function civicrm_api3_sepa_mandate_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_mandates
 *
 * @param  array input parameters
 *
 *
 * @example SepaCreditorGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_mandate_get}
 * @access public
 */
function civicrm_api3_sepa_mandate_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}


/**
 * Modify/update mandates
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/413
 */
function civicrm_api3_sepa_mandate_modify($params) {
  if (!CRM_Sepa_Logic_Settings::getSetting('allow_mandate_modification')) {
    return civicrm_api3_create_error("Mandate modification not allowed. Check your settings.");
  }

  // look up mandate ID if only reference is given
  if (empty($params['mandate_id']) && !empty($params['reference'])) {
    $mandate = civicrm_api3('SepaMandate', 'get', array('reference' => $params['reference'], 'return' => 'id'));
    if ($mandate['id']) {
      $params['mandate_id'] = $mandate['id'];
    } else {
      return civicrm_api3_create_error("Couldn't identify mandate with reference '{$params['reference']}'.");
    }
  }

  // no mandate could be identified
  if (empty($params['mandate_id'])) {
    return civicrm_api3_create_error("You need to provide either 'mandate_id' or 'reference'.");
  }

  try {
    $changes = CRM_Sepa_BAO_SEPAMandate::modifyMandate($params['mandate_id'], $params);
    return civicrm_api3_create_success($changes);
  } catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
}

/**
 * API specs for updating mandates
 */
function _civicrm_api3_sepa_mandate_modify_spec(&$params) {
  $params['mandate_id'] = array(
    'name'         => 'mandate_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate ID',
  );
  $params['reference'] = array(
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate Reference',
  );
  $params['amount'] = array(
    'name'         => 'amount',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New Amount',
  );
  $params['iban'] = array(
    'name'         => 'iban',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New IBAN',
  );
  $params['bic'] = array(
    'name'         => 'bic',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'New BIC',
  );
  $params['financial_type_id'] = array(
    'name'         => 'financial_type_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'New Financial Type ID',
  );
  $params['campaign_id'] = array(
    'name'         => 'campaign_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'New Campaign ID',
  );
}


/**
 * Terminate mandates responsibly
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/483
 */
function civicrm_api3_sepa_mandate_terminate($params) {
  // look up mandate ID if only reference is given
  if (empty($params['mandate_id']) && !empty($params['reference'])) {
    $mandate = civicrm_api3('SepaMandate', 'get', array('reference' => $params['reference'], 'return' => 'id'));
    if ($mandate['id']) {
      $params['mandate_id'] = $mandate['id'];
    } else {
      return civicrm_api3_create_error("Couldn't identify mandate with reference '{$params['reference']}'.");
    }
  }

  // no mandate could be identified
  if (empty($params['mandate_id'])) {
    return civicrm_api3_create_error("You need to provide either 'mandate_id' or 'reference'.");
  }

  try {
    CRM_Sepa_BAO_SEPAMandate::terminateMandate(
      $params['mandate_id'],
      CRM_Utils_Array::value('end_date', $params, date('Y-m-d')), // use today rather than now
      CRM_Utils_Array::value('cancel_reason', $params),
      FALSE);
    return civicrm_api3_create_success();

  } catch (Exception $e) {

    return civicrm_api3_create_error($e->getMessage());
  }
}

/**
 * API specs for updating mandates
 */
function _civicrm_api3_sepa_mandate_terminate_spec(&$params) {
  $params['mandate_id'] = array(
    'name'         => 'mandate_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Mandate ID',
  );
  $params['reference'] = array(
    'name'         => 'reference',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Mandate Reference',
  );
  $params['end_date'] = array(
    'name'         => 'end_date',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'End Date',
    'description'  => 'Default is NOW',
  );
  $params['cancel_reason'] = array(
    'name'         => 'cancel_reason',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Cancel Reason',
  );
}



/**
 * will add the default creditor_id if no creditor_id is given, and the default creditor is valid
 */
function _civicrm_api3_sepa_mandate_adddefaultcreditor(&$params) {
  if (empty($params['creditor_id'])) {
    $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    if ($default_creditor != NULL) {
      $params['creditor_id'] = $default_creditor->id;
    }
  }
}

