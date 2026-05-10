<!-- Page Wrapper -->
<div class="page-wrapper">
	<div class="content container-fluid">
		<div class="page-header">
			<div class="row align-items-center"></div>
		</div>

		<div class="card">
			<div class="card-body">
				<div class="row mb-3">
					<div class="col-md-6">
						<h5>Bank Reconciliation (BRS)</h5>
					</div>
					<div class="col-md-6 text-end">
						<a class="btn btn-outline-secondary" href="<?=base_url('bank_accounts');?>">Bank Accounts</a>
					</div>
				</div>

				<div class="row g-2 mb-3">
					<div class="col-md-4">
						<label>Bank account</label>
						<select class="form-control" id="br_bank_account"></select>
					</div>
					<div class="col-md-2">
						<label>Year</label>
						<select class="form-control" id="br_year"></select>
					</div>
					<div class="col-md-2">
						<label>Month</label>
						<select class="form-control" id="br_month">
							<?php for($m=1;$m<=12;$m++): ?>
								<option value="<?=$m;?>"><?=date('F', mktime(0,0,0,$m,1));?></option>
							<?php endfor; ?>
						</select>
					</div>
					<div class="col-md-4">
						<label>Statement ending balance</label>
						<input type="text" class="form-control" id="br_stmt_ending" placeholder="0.00">
					</div>
				</div>

				<div class="row g-2 mb-3">
					<div class="col-md-4">
						<label>Statement as of (date)</label>
						<input type="date" class="form-control" id="br_stmt_as_of">
					</div>
					<div class="col-md-8">
						<label>Explanatory comment (printed on BRS)</label>
						<textarea class="form-control" id="br_explanatory" rows="2" placeholder="Brief notes on reconciling items"></textarea>
					</div>
				</div>

				<div class="row mb-3">
					<div class="col-md-12">
						<button class="btn btn-primary" id="br_load_btn">Load / Save Period</button>
						<button class="btn btn-outline-primary" id="br_preview_btn" disabled>Preview BRS</button>
						<button class="btn btn-outline-success" id="br_export_btn" disabled>Download BRS</button>
					</div>
				</div>

				<hr>

				<div class="row">
					<div class="col-md-6">
						<h6>Bank Statement Lines</h6>
						<div class="row g-2 mb-2">
							<div class="col-md-3"><input type="date" class="form-control" id="br_line_date"></div>
							<div class="col-md-4"><input type="text" class="form-control" id="br_line_desc" placeholder="Description"></div>
							<div class="col-md-3"><input type="text" class="form-control" id="br_line_ref" placeholder="Ref"></div>
							<div class="col-md-2"><input type="text" class="form-control" id="br_line_amt" placeholder="+/-0.00"></div>
						</div>
						<button class="btn btn-sm btn-outline-primary mb-2" id="br_add_line_btn" disabled>Add line</button>
						<table class="table table-sm table-striped" id="tbl_stmt_lines">
							<thead>
								<tr>
									<th>Date</th><th>Description</th><th>Ref</th><th class="text-end">Amount</th><th></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>

					<div class="col-md-6">
						<h6>Book Lines (Cash in Bank)</h6>
						<table class="table table-sm table-striped" id="tbl_book_lines">
							<thead>
								<tr>
									<th>Date</th><th>JEV</th><th>Ref</th><th class="text-end">Net</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
						<div class="alert alert-info py-2">
							<div><strong>Book ending balance (computed):</strong> <span id="br_book_balance">0.00</span></div>
						</div>
					</div>
				</div>

				<hr>

				<div class="row">
					<div class="col-md-12">
						<h6>Assisted Matching</h6>
						<p class="text-muted mb-2">Click “Suggest” on a statement line to see candidate book lines (amount/date/reference), then confirm match.</p>
						<table class="table table-sm" id="tbl_matches">
							<thead>
								<tr>
									<th>Statement</th><th>Book</th><th class="text-end">Matched</th><th></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<hr>

				<div class="row">
					<div class="col-md-12">
						<h6>Reconciling Items</h6>
						<div class="row g-2 mb-2">
							<div class="col-md-3">
								<select class="form-control" id="br_item_type">
									<option value="outstanding_check">Outstanding Check</option>
									<option value="deposit_in_transit">Deposit in Transit</option>
									<option value="bank_charge">Bank Charge</option>
									<option value="interest_income">Interest Income</option>
									<option value="bank_debit_memo">Bank Debit Memo</option>
									<option value="bank_credit_memo">Bank Credit Memo</option>
									<option value="book_error">Book Error</option>
									<option value="bank_error">Bank Error</option>
									<option value="other">Other</option>
								</select>
							</div>
							<div class="col-md-2"><input type="text" class="form-control" id="br_item_amt" placeholder="0.00"></div>
							<div class="col-md-3"><input type="text" class="form-control" id="br_item_ref" placeholder="Reference"></div>
							<div class="col-md-4"><input type="text" class="form-control" id="br_item_notes" placeholder="Notes"></div>
						</div>
						<button class="btn btn-sm btn-outline-primary mb-2" id="br_add_item_btn" disabled>Add item</button>
						<table class="table table-sm table-striped" id="tbl_items">
							<thead><tr><th>Type</th><th>Ref</th><th>Notes</th><th class="text-end">Amount</th><th></th></tr></thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<div class="card" id="preview_card" style="display:none;">
					<div class="card-body">
						<div class="row mb-3">
							<div class="col-md-4 col-lg-4 col-sm-12">
								<label for="preview_sheet_selector">Sheet</label>
								<select class="form-control" id="preview_sheet_selector"></select>
							</div>
						</div>
						<div id="preview_grid_container" style="height: 650px; width: 100%; overflow-x: auto; overflow-y: auto;"></div>
					</div>
				</div>

			</div>
		</div>
	</div>
</div>
<!-- /Page Wrapper -->

