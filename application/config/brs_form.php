<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| BRS (COA-style) — LGU header defaults, row labels, certification
|--------------------------------------------------------------------------
| Per-barangay name comes from session; set municipality/province/tel for your LGU.
*/

$config['brs_form'] = array(
	'title' => 'BANK RECONCILIATION STATEMENT (BRS)',
	'account_kind_label' => 'LCCA / AGDB (Local Currency Current Account — Authorized Government Depository Bank)',

	'city_municipality' => 'Municipality of Jaro',
	'province' => 'Leyte',
	'barangay_tel' => '',

	'row_unadjusted_book' => 'Unadjusted balance per books (Cash in Bank — LCCA)',
	'row_unadjusted_bank' => 'Unadjusted balance per bank statement',
	'row_adj_book' => 'Adjusted balance per books',
	'row_adj_bank' => 'Adjusted balance per bank statement',
	'row_difference' => 'Difference (should be zero)',

	'row_less_bank_charges' => 'Less: Bank charges (not yet recorded in books)',
	'row_less_bank_debit_memo' => 'Less: Bank debit memos (not yet recorded in books)',
	'row_add_interest' => 'Add: Interest income (not yet recorded in books)',
	'row_add_bank_credit_memo' => 'Add: Bank credit memos (not yet recorded in books)',
	'row_book_errors_other' => 'Add/Less: Book errors and other book-side adjustments',

	'row_add_deposits_transit' => 'Add: Deposits in transit',
	'row_less_outstanding_checks' => 'Less: Outstanding checks',
	'row_bank_errors' => 'Add/Less: Bank errors and other bank-side adjustments',

	'certified_by_name' => 'Judy G. Parado, CPA',
	'certified_by_title' => 'Municipal Accountant',

	'instructions_short' => 'Submit to the COA Auditor on or before the 20th of the following month. '
		. 'Base this statement on paid Disbursement Vouchers and the bank statement, supported by paid/negotiated/returned checks, debit memos, and credit memos. '
		. 'Certified correct by the City/Municipal Accountant. Prepare in three copies: Original — COA Auditor; 2nd copy — City/Municipal Accountant file; 3rd copy — PB/BT.',

	'item_row_labels' => array(
		'outstanding_check' => 'Outstanding check',
		'deposit_in_transit' => 'Deposit in transit',
		'bank_charge' => 'Bank charge',
		'interest_income' => 'Interest income',
		'bank_debit_memo' => 'Bank debit memo',
		'bank_credit_memo' => 'Bank credit memo',
		'book_error' => 'Book error / adjustment',
		'bank_error' => 'Bank error / adjustment',
		'other' => 'Other adjustment',
	),
);
