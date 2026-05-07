<div class="page-wrapper">
	<div class="content container-fluid">
		<div class="page-header">
			<div class="row align-items-center">
				<div class="col">
					<h3 class="page-title">View Beginning Balances</h3>
					<input
						type="hidden"
						id="brgy"
						value="<?php echo $_SESSION['currbrgyid']; ?>"
					/>
				</div>
			</div>
		</div>

		<!-- Overview Section -->
		<div class="row">
			<div class="col-xl-3 col-sm-6 col-12 d-flex">
				<div class="card bg-comman w-100">
					<div class="card-body">
						<div
							class="db-widgets d-flex justify-content-between align-items-center"
						>
							<div class="db-info">
								<h6>Barangay</h6>
								<h3><?=$_SESSION['currbrgy'];?></h3>
							</div>
							<div class="db-icon">
								<img
									src="assets/img/icons/invoices-icon5.png"
									alt="Dashboard Icon"
								/>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Report Filter -->
			<div class="card">
				<div class="card-body pb-10">
					<div class="row">
						<input
							type="hidden"
							name="brgy"
							value="<?=$_SESSION['currbrgyid'];?>"
						/>

						<hr class="mt-10" />
						<div class="row">
							<div class="row col-md-12 col-lg-12 col-sm-12" id="cs_div">
								<div class="col-md-4 col-lg-4 col-sm-12">
									<label for="">Year</label>
									<select class="form-control" id="bb_year"></select>
								</div>
								<div class="col-md-4 col-lg-4 col-sm-12">
									<button type="button" class="btn btn-primary" id="filter_bb">
										Filter
									</button>
								</div>
							</div>
						</div>
					</div>
					<hr />
					
				</div>
			</div>
			<!-- /Report Filter -->
		</div>
		<!-- /Overview Section -->

		<div class="row">
			<div class="col-sm-12">
				<div class="card card-table">
					<div class="card-body">
						<div class="page-header">
							<div class="row align-items-center">
								<div class="col">
									<h3 class="page-title">Beginning Balances</h3>
								</div>

								<div class="table-responsive">
									<table
										class="table border-0 star-student table-hover table-center mb-0 table-striped"
										id="tbl_bblist"
									>
										<thead class="student-thread">
											<tr>
												<th>NO</th>
												<th>Year</th>
												<th>Total</th>
												
												<th>Action</th>
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
	</div>
</div>
