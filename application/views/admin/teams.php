
<div class="page-wrapper">
<div class="content container-fluid">

<div class="page-header">
<div class="row align-items-center">
<div class="col">
<h3 class="page-title">Teams</h3>

</div>
</div>
</div>


<div class="row">
<div class="col-sm-12">
<div class="card card-table">
<div class="card-body">

<div class="page-header">
<div class="row align-items-center">
<div class="col">
<h3 class="page-title">Teams List</h3>
</div>
<div class="col-auto text-end float-end ms-auto">
<button data-bs-toggle="modal" data-bs-target="#add_team_modal" class="btn btn-primary btn-lg"><i class="fas fa-plus"></i> Add Team</button>

</div>
</div>
</div>

<div class="table-responsive">
<table class="table border-0  table-hover table-center mb-0 table-striped" id="tbl_teams">
<thead class="student-thread">
<tr>
<th>
<div class="form-check check-tables">
<input class="form-check-input" type="checkbox" value="something">
</div>
</th>
<th>Team ID</th>
<th>Team name</th>
<th>Team Leader</th>
<th>Member Count</th>
<th>Date Created</th>
</tr>
</thead>
<tbody>



</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>


	<!--Add Student Modal-->
	<div class="modal fade" id="add_team_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Add New Team</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="ki ki-close"></i>
					</button>
				</div>
				<div class="modal-body">
					<form id="addstudentform" class="form">
						<div class="form-group row">
							<label class="col-lg-3 col-form-label">Team name:</label>
							<div class="col-lg-6">
								<input type="text" id="tname" class="form-control" name="tname" placeholder="Enter Team Name">

							</div>
						</div>

                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">Team Leader:</label>
							<div class="col-lg-6">
								<select  id="tleader" class="form-control" name="tleader">
									
									<option selected disabled>Select Leader</option>
									<?php foreach($leader as $l): ?>
										<option value="<?=$l['resp_id'];?>"><?=$l['fullname']; ?></option>
									<?php endforeach; ?>
								</select>

							</div>
						</div>

                   	
					</form>
                    <h5>Select Team Members</h5>
                    <div class="table-responsive">
                    <table class="table border-0  table-hover table-center mb-0 table-striped" id="tbl_members">
<thead class="student-thread">
<tr>
<th>
<div class="form-check check-tables">
<input class="form-check-input" type="checkbox" id="select_all_members">
</div>
</th>
<th>Respondent ID</th>
<th>Name</th>

</tr>
</thead>
<tbody>


</tbody>
</table>
</div>


				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light-primary font-weight-bold" data-bs-dismiss="modal">Close</button>
					<button type="button" id="addteambtn" class="btn btn-primary font-weight-bold">Save Team</button>
				</div>
			</div>
		</div>
	</div>

	<!--end::Add Student Modal-->