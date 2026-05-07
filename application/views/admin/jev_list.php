<div class="page-wrapper">
<div class="content container-fluid">

<div class="page-header">
<div class="row align-items-center">
<div class="col">
<h3 class="page-title">JEV List</h3>
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
											<h6>Total JEVS</h6>
											<h3 id="tot_jevs"><?=$total_jev;?></h3>
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
											<h6>Total Debit</h6>
											<h3 id="tot_d"><?=$total_debit;?></h3>
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
											<h6>Total Credit</h6>
											<h3 id="tot_c"><?=$total_credit;?></h3>
										</div>
										<div class="db-icon">
											<img  src="assets/img/icons/invoices-icon5.png"  alt="Dashboard Icon">
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- Report Filter -->
					<div class="card ">
						<div class="card-body pb-10">
							<div class="row">
								<div class="col-md-12">
									
                                    <input type="hidden" name="brgy" value="<?=$_SESSION['currbrgyid'];?>">
                                    
												
                                    <hr class="mt-10">
										<div class="row">
                                       
                                        <div class="row col-md-12 col-lg-12 col-sm-12" id="cs_div">
                                                
											
                                                <div class="col-md-4 col-lg-4 col-sm-12">
                                                <label for="">Month</label>
                                                    <select class="form-control"  id="tb_month">
														<option value="13">Whole Year</option>
                                                        <option value="01">January</option>
                                                        <option value="02">February</option>
                                                        <option value="03">March</option>
                                                        <option value="04">April</option>
                                                        <option value="05">May</option>
                                                        <option value="06">June</option>
                                                        <option value="07">July</option>
                                                        <option value="08">August</option>
                                                        <option value="09">September</option>
                                                        <option value="10">October</option>
                                                        <option value="11">November</option>
                                                        <option value="12">December</option>
                                                    </select>
                                                </div>
                                                

                                                <div class="col-md-4 col-lg-4 col-sm-12">
                                                    <label for="">Year</label>
                                                    <select class="form-control"  id="tb_year">
                                                       
                                                       
                                                    </select>
                                                </div>
												<div class="col-md-4 col-lg-4 col-sm-12">
                                                    <button type="submit" class="btn btn-primary" id="filter_jevlist">
                                                         Filter
                                                    </button>
                                            	</div>
                                                

                                               
												
                                                </div>
										
										
											
                                               

												
											 
										
										
										
										
                                              
                                        </div>
                                        
										
									
								    </div>
							</div>
                            <hr>
                            <div class="row pt-10">
                                       
                            			</div>
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
<h3 class="page-title">JEV List</h3>
</div>


<div class="table-responsive">
<table class="table border-0 star-student table-hover table-center mb-0  table-striped" id="tbl_jevlist">
<thead class="student-thread">
<tr>

<th>JEV NO</th>
<th>TYPE</th>
<th>JEV DATE</th>
<th>Particulars</th>
<th>Payee</th>
<th>Status</th>
<th class="text-end">Action</th>
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