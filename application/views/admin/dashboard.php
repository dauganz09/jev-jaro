
<div class="page-wrapper">
<div class="content container-fluid">

<div class="page-header">
<div class="row">
<div class="col-sm-12">
<div class="page-sub-header">
<h3 class="page-title">Welcome Administrator!</h3>

</div>
</div>
</div>
</div>


<div class="row">
<div class="col-xl-3 col-sm-6 col-12 d-flex">
<div class="card bg-comman w-100">
<div class="card-body">
<div class="db-widgets d-flex justify-content-between align-items-center">
<div class="db-info">
<h6>Current Barangay</h6>
<input type="hidden" id="cbrgy" value="<?php  echo $_SESSION['currbrgyid']; ?>">
<h3><?php  echo $_SESSION['currbrgy']; ?></h3>
<button data-bs-toggle="modal" data-bs-target="#add_resp_modal" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Change</button>

</div>
<div class="db-icon">
<img src="<?php echo base_url('assets'); ?>/img/icons/incident.png" alt="Incident Icon">
</div>
</div>
</div>
</div>
</div>


  
   


</div>
    
</div>

</div>

<!--Add event Modal-->
<div class="modal fade" id="add_event_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Add Meeting/Event</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="ki ki-close"></i>
					</button>
				</div>
				<div class="modal-body">
					<form id="addstudentform" class="form">
						<div class="form-group row">
							<label class="col-lg-3 col-form-label">Title:</label>
							<div class="col-lg-6">
								<input type="text" id="title" class="form-control clear-form" name="title" placeholder="Enter Meeting title">

							</div>
						</div>

                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">Description:</label>
							<div class="col-lg-6">
								<textarea class="form-control clear-form" name="" id="desc" cols="30" rows="10" placeholder="Meeting description"></textarea>

							</div>
						</div>
                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">Color:</label>
							<div class="col-lg-6">
                            <input type="color" id="color" class="form-control clear-form" name="color">

							</div>
						</div>

                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">Start DateTime:</label>
							<div class="col-lg-6">
								<input type="datetime-local" id="start_date" class="form-control clear-form"  placeholder="">

							</div>
						</div>
                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">End DateTime:</label>
							<div class="col-lg-6">
								<input type="datetime-local" id="end_date" class="form-control clear-form"  placeholder="">

							</div>
						</div>

                      

                   	
					</form>
                    


				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light-primary font-weight-bold" data-bs-dismiss="modal">Close</button>
					<button type="button" id="addeventbtn" class="btn btn-primary font-weight-bold">Save Meeting</button>
				</div>
			</div>
		</div>
	</div>

	<!--end::Add event Modal-->


	<!--Add Student Modal-->
	<div class="modal fade" id="add_resp_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" >
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Change Current Barangay</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="ki ki-close"></i>
					</button>
				</div>
				<div class="modal-body">
					<form id="addstudentform" class="form">
						<div class="form-group row">
							<label class="col-lg-3 col-form-label">Barangay:</label>
							<div class="col-lg-6">
							<select type="text" id="curr_brgy" class="form-control" >
									<?php foreach($_SESSION['brgys'] as $brgy): ?>
										<option value="<?=$brgy['brgy_id'];?>"><?=$brgy['name'];?></option>
									<?php endforeach;?>
										
							</select>

							</div>
						</div>

                      

                   	
					</form>
                    


				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light-primary font-weight-bold" data-bs-dismiss="modal">Close</button>
					<button type="button" id="save_curr_brgy" class="btn btn-primary font-weight-bold">Save</button>
				</div>
			</div>
		</div>
	</div>

    
<!--View event Modal-->
<div class="modal fade" id="view_event_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Meeting Details</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="ki ki-close"></i>
					</button>
				</div>
				<div class="modal-body">
					<form id="addstudentform" class="form">
						<div class="form-group row">
							<label class="col-lg-3 col-form-label">Title:</label>
							<div class="col-lg-6">
								<input type="text" id="etitle" class="form-control clear-form" name="title" placeholder="Enter Meeting title" readonly>

							</div>
						</div>

                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">Description:</label>
							<div class="col-lg-6">
								<textarea class="form-control clear-form" name="" id="edesc" cols="30" rows="10" placeholder="Meeting description" readonly></textarea>

							</div>
						</div>
                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">Color:</label>
							<div class="col-lg-6">
                            <input type="color" id="ecolor" class="form-control clear-form" name="color">

							</div>
						</div>

                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">Start DateTime:</label>
							<div class="col-lg-6">
								<input type="datetime-local" id="estart_date" class="form-control clear-form"  placeholder="">

							</div>
						</div>
                        <div class="form-group row">
							<label class="col-lg-3 col-form-label">End DateTime:</label>
							<div class="col-lg-6">
								<input type="datetime-local" id="eend_date" class="form-control clear-form"  placeholder="">

							</div>
						</div>

                      

                   	
					</form>
                    


				</div>
				
			</div>
		</div>
	</div>

	<!--end::View event Modal-->