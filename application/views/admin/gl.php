<!-- Page Wrapper -->
<div class="page-wrapper">
				<div class="content container-fluid">
			
					<!-- Page Header -->
					<div class="page-header">
						<div class="row align-items-center">
							
						</div>
					</div>
					<!-- /Page Header -->
					
					<!-- Invoice Header -->
					
					<!-- /Invoice Header -->
                    
                    <?php
                    if ($this->session->flashdata('success_message')) {
                        echo '<div class="alert  alert-success">' . $this->session->flashdata('success_message') . '</div>';
                        }?>
    <?php
    if ($this->session->flashdata('error')) {
                            echo '<div class="alert alert-danger alert-dismissable fade show">' . $this->session->flashdata('error') . '</div>';
                    }?>
					<!-- Report Filter -->
					<div class="card ">
						<div class="card-body pb-10">
							<div class="row">
								<div class="col-md-12">
									<form method="post" action="<?=base_url('ledger');?>" id="report_form">
                                    <input type="hidden" name="brgy" value="<?=$_SESSION['currbrgyid'];?>">
                                    <div class="row">
												<div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
													
													<div class="form-group">
														
														<input class="form-check-input" name="l_type" value="g" type="radio" checked>
                                                        <label class="form-check-label" for="gender_male">
															General Ledger
															</label>
													</div>
												</div>
												<div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
                                                    <div class="form-group">
														
														<input class="form-check-input" name="l_type" value="s"  type="radio">
                                                        <label class="form-check-label" for="gender_male">
															Subsidiary Ledger
															</label>
													</div>
												</div>

                                                <div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
                                                <div class="form-group">
														
														<input class="form-check-input" name="l_type" value="ss"  type="radio">
                                                        <label class="form-check-label" for="gender_male">
															Subsidiary Schedule
															</label>
													</div>
												</div>

                                                

                                                <!-- <div class="col-xl-3 col-md-3 col-sm-12 col-12">
                                                <div class="form-group">
														
														<input class="form-check-input" name="jev_type" value="GJ" type="radio">
                                                        <label class="form-check-label" for="gender_male">
															General Journal
															</label>
													</div>
												</div> -->
												
                                            
												
											</div>
											
												
                                                    
                                               
										</div>
										<div class="row">
                                            <div class="row col-md-6 col-lg-6 col-sm-12">
                                            <div class="col-md-4 col-lg-4 col-sm-12">
                                                <label for="">Type</label>
                                                    <select class="form-control"  id="tb_range">
                                                        <option value="" disabled selected>Select..</option>
                                                        <option value="M">Monthly</option>
                                                        <option value="Q">Quarterly</option>
                                                        <option value="A">Anually</option>
                                                        <option value="C">Custom</option>
                                                       
                                                    </select>
                                                </div>
											
                                                <div class="col-md-4 col-lg-4 col-sm-12" id="mbox">
                                                <label for="">Month</label>
                                                    <select class="form-control"  id="tb_month">
                                                       
                                                        <option value="1">January</option>
                                                        <option value="2">February</option>
                                                        <option value="3">March</option>
                                                        <option value="4">April</option>
                                                        <option value="5">May</option>
                                                        <option value="6">June</option>
                                                        <option value="7">July</option>
                                                        <option value="8">August</option>
                                                        <option value="9">September</option>
                                                        <option value="10">October</option>
                                                        <option value="11">November</option>
                                                        <option value="12">December</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 col-lg-4 col-sm-12" id="qbox">
                                                    <label for="">Quarter</label>
                                                    <select class="form-control"  id="tb_quarter">
                                                    <option value="1">1st</option>
                                                        <option value="2">2nd</option>
                                                        <option value="3">3rd</option>
                                                        <option value="4">4th</option>
                                                       
                                                    </select>
                                                </div>

                                                <div class="col-md-4 col-lg-4 col-sm-12" id="ybox">
                                                    <label for="">Year</label>
                                                    <select class="form-control"  id="tb_year">
                                                       
                                                       
                                                    </select>
                                                </div>

                                               
												
                                                </div>
										
										
											
                                                <div class="row col-md-6 col-lg-6 col-sm-12">
                                                    <div class="col-md-6 col-lg-6 col-sm-12">
                                                        <label for="">Start Date</label>
                                                        <input type="text" class="form-control" name="sdate" id="sdate" readonly>
                                                    </div>
                                                    <div class="col-md-6 col-lg-6 col-sm-12">
                                                    <label for="">End Date</label>
                                                        <input type="text" class="form-control" name="edate" id="edate" readonly>
                                                    </div>
</div>

												
											 
										
										
										
										
                                              
                                        </div>
                                        
										
									
								</div>
							</div>
                            <hr>
                            <div class="row pt-10">
                                                <div class="col-md-2 col-lg-2 col-sm-12">
                                                    <button type="submit" class="btn btn-primary"  name="generate_ledger">
                                                         Generate
                                                    </button>


                                                </div>
                                                <div class="col-md-2 col-lg-2 col-sm-12">
                                                    <button type="button" class="btn btn-outline-primary" id="preview_report_btn" data-preview-url="<?=base_url('ledger_preview');?>">
                                                         Preview
                                                    </button>
                                                </div>
                                                <div class="col-md-2 col-lg-2 col-sm-12">
                                                    <button type="button" class="btn btn-outline-secondary d-none report-preview-pdf-btn" id="preview_pdf_btn" data-preview-url="<?=base_url('ledger_preview');?>">
                                                         Preview PDF
                                                    </button>
                                                </div>
                                                <div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
													
													<div class="form-group">
														
														<input class="form-check-input" name="g_type" value="all" type="radio" checked>
                                                        <label class="form-check-label" for="gender_male">
															All
															</label>
													</div>
												</div>
												<div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
                                                    <div class="form-group">
														
														<input class="form-check-input" name="g_type" value="sp"  type="radio">
                                                        <label class="form-check-label" for="gender_male">
															Specific
															</label>
													</div>
												</div>

                                              
                                              
                                                
                                               
                                                <!-- <div class="col-md-2 col-lg-2 col-sm-12">
                                                    
                                                    <button type="button" class="btn btn-primary" id="set_bal">
                                                         Set Beginning Balance
                                                    </button>
                                                </div>  -->

                            </div>
						</div>
					</div>
					<!-- /Report Filter -->

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

                    <!-- Report Filter -->
					<div class="card" id="accounts_box">
						<div class="card-body pb-10">
                            <div class="row">
                                               
                                                <div class="col-md-3 col-lg-3 col-sm-12">
                                                <label for="">Account Code:</label>
                                                        <input type="text" class="form-control" name="acc_code" id="acc_code" readonly>
                                                </div> 
                                                
                                                <div class="col-md-3 col-lg-3 col-sm-12">
                                                <label for="">Account Title:</label>
                                                        <input type="text" class="form-control" name="acc_name" id="acc_name" readonly>
                                                </div>  
                                               
                                               
                            </div>
                                                <hr>                                    
                            <div class="table-responsive">
                                <table class="table border-0  table-hover table-center mb-0  table-striped" id="tbl_acclist">
                                    <thead class="student-thread">
                                    <tr>
                                 
                                    <th>Account Code</th>
                                    <th>Account Title</th>
                                    
                                    </tr>
                                    </thead>
                                    <tbody>

                                    </tbody>
                                </table>
                            </div>
                            
                           
                            
						</div>
					</div>
					<!-- /Report Filter -->

                    <!-- Report Filter -->
					<!-- <div class="card " id="gl_card">
						<div class="card-body pb-10">
                            <div class="row">
                                               
                            <div class="col-md-4 col-lg-4 col-sm-12">
                                                <label for="">Group</label>
                                                    <select class="form-control" name="acc_group"  id="acc_group">
                                                    <option selected disabled>Select ---</option>
                                                        <option value="1">Assets</option>
                                                        <option value="2">Liabilites</option>
                                                        <option value="3">Equity</option>
                                                        <option value="4">Income</option>
                                                        <option value="5">Expenses</option>
                                                        
                                                    </select>
                                                </div>
                                                
                                                
                                              
                                               
                            </div>
                                                <hr>                                    
                            
                            
                           
                            
						</div>
					</div> -->
					<!-- /Report Filter -->
                    

					
					

				
				</div>
                </form>
			</div>
			<!-- /Page Wrapper -->


            		
	<!--balance Modal-->
	<div class="modal fade" id="balance_modal" data-backdrop="static" tabindex="-1" role="dialog" aria-labelledby="staticBackdrop" >
		<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="exampleModalLabel">Set Beginning Balance</h5>
					<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
						<i aria-hidden="true" class="fa fa-times"></i>
					</button>
				</div>
				<div class="modal-body">
				
					<table class="table border-0  table-hover  mb-0 table-striped" id="tbl_accounts">
							<thead>
								
								
								<th>Account Title</th>
								<th>Account Code</th>
                                <th>Debit</th>
                                <th>Credit</th>

								
							</thead>
							<tbody>
                                <tr><input type="hidden" id="acc_code_hidden">
                                    <td id="acc_name_box"></td>
                                    <td id="acc_code_box"></td>
                                    <td><input type="text" class="form-control" id="bal_debit"></td>
                                    <td><input type="text" class="form-control" id="bal_credit"></td>
                                </tr>

							</tbody>
					</table>
				
                    


				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light-primary font-weight-bold" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary font-weight-bold" id="set_balance">Set Balance</button>
					
				</div>
			</div>
		</div>
	</div>