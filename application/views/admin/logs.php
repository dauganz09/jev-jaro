<div class="page-wrapper">
<div class="content container-fluid">

<div class="page-header">
<div class="row align-items-center">
<div class="col">
<h3 class="page-title">System Logs</h3>
<input type="hidden" id="brgy" value="<?php echo $_SESSION['currbrgyid']; ?>">

</div>
</div>
</div>

<!-- Overview Section -->
<div class="row">
                    <div class="col-xl-3 col-sm-6 col-12 d-flex">
							<div class="card bg-comman w-100">
								<div class="card-body">
									<div class="db-widgets d-flex justify-content-between align-items-center">
										<div class="db-info">
											<h6>Barangay</h6>
											<h3><?=$_SESSION['currbrgy'];?></h3>
										</div>	
										<div class="db-icon">
											<img  src="assets/img/icons/invoices-icon5.png"  alt="Dashboard Icon">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-xl-3 col-sm-6 col-12 d-flex">
							<div class="card bg-comman w-100">
								<div class="card-body">
									<div class="db-widgets d-flex justify-content-between align-items-center">
										<div class="db-info">
											<h6>Total Logs</h6>
											<h3><?=$log_count;?></h3>
										</div>	
										<div class="db-icon">
											<img  src="assets/img/icons/invoices-icon5.png"  alt="Dashboard Icon">
										</div>
									</div>
								</div>
							</div>
						</div>

						


						
					</div>
					<!-- /Overview Section -->	


<div class="row">
<div class="col-sm-12">
<div class="card card-table">
<div class="card-body">

<div class="page-header">
<div class="row align-items-center">
<div class="col">
<h3 class="page-title">System Logs</h3>
</div>


<div class="table-responsive">
<table class="table border-0 star-student table-hover table-center mb-0  table-striped" id="tbl_logs">
<thead class="student-thread">
<tr>

<th>LOG NO</th>
<th>Log Transaction</th>
<th>User</th>
<th>Date</th>

</tr>
</thead>
<tbody>
    <?php foreach($logs as $log):?>
            <tr>
                <td><?=$log['log_id'];?></td>
                <td><?=$log['action'];?></td>
                <td><?=$log['user'];?></td>
                <td><?=$log['date'];?></td>
            </tr>
        <?php endforeach;?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>