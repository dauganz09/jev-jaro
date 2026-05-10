<div class="page-wrapper">
	<div class="content container-fluid">
		<div class="page-header">
			<div class="row align-items-center">
				<div class="col">
					<h3 class="page-title">Chart of Accounts</h3>
				</div>
			</div>
		</div>

		<div class="row">
			<div class="col-sm-12">
				<div class="card card-table">
					<div class="card-body">
						<div class="row mb-3 align-items-center">
							<div class="col-md-8">
								<p class="text-muted small mb-0">Philippine government / PPSAS-style UACS codes. Only <strong>active</strong> accounts appear in the JEV account picker. Run <code>database/coa_crud_migration.sql</code> once if classification / notes / active columns are missing.</p>
							</div>
							<div class="col-md-4 text-end">
								<button type="button" class="btn btn-primary" id="coa_add_btn">Add account</button>
							</div>
						</div>

						<div class="table-responsive">
							<table class="table border-0 star-student table-hover table-center mb-0 table-striped" id="tbl_coa" style="width:100%">
								<thead class="student-thread">
									<tr>
										<th>ID</th>
										<th>Account code</th>
										<th>Account title</th>
										<th>Classification</th>
										<th>Notes</th>
										<th>Active</th>
										<th class="text-end">Action</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	window.COA_CLASSES = <?= json_encode(isset($coa_classes) ? $coa_classes : array()); ?>;
	window.COA_CODE_HINT = <?= json_encode(isset($coa_code_hint) ? $coa_code_hint : ''); ?>;
	window.COA_BASE_URL = "<?= base_url(); ?>";
</script>
