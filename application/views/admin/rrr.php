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
									<form method="post" action="<?=base_url('rrr');?>">
                                    <input type="hidden" name="brgy" value="<?=$_SESSION['currbrgyid'];?>">
                                    <div class="row ">
                                        <div class="col-md-4 col-lg-4 col-sm-12">
                                        <!-- <label for="">FS Type:</label>
                                                    <select class="form-control" name="f_type"  id="f_type">
                                                        <option value="" disabled selected>Select..</option>
                                                        <option value="FPE">Statement of Financial Performance</option>
                                                        <option value="FPO">Statement of Financial Position</option>
                                                        <option value="CAE">Statement of Changes in Assets/Equity</option>
                                                        <option value="CF">Statement of Consensed Cash Flows</option>

                                                        
                                                       
                                                    </select>
                                                </div>
                                        </div> -->
                                        <h2>Report on Revenue and Receipts</h2>
                                    </div>

                                    <div class="col-xl-2 col-md-2 col-lg-2 col-sm-12 col-12">
													
													
									</div>
												</div>

                                    <hr class="mt-10">
										<div class="row">
                                        <div class="row col-md-6 col-lg-6 col-sm-12" id="com_div">
                                        <div class="col-md-3 col-lg-4 col-sm-12">
                                                    <label for="">Current year:</label>
                                                    <select class="form-control" name="fse_year"  id="fse_year">
                                                       
                                                       
                                                    </select>
                                                </div>
                                                
                                            <div class="col-md-3 col-lg-4 col-sm-12">
                                                    <label for="">Start year:</label>
                                                    <select class="form-control" name="fss_year"  id="fss_year">
                                                       
                                                       
                                                    </select>
                                                </div>

                                        </div>
                                        <div class="row col-md-6 col-lg-6 col-sm-12" id="cs_div">
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
										
										
											
                                                <div class="row col-md-6 col-lg-6 col-sm-12" id="year_container">
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
                                        <div class="col-md-3 col-lg-3 col-sm-12">
                                                    <button type="submit" class="btn btn-primary" id="generate_rrr">
                                                         Generate report
                                                    </button>
                                                </div>
                                                </form>
                            </div>
						</div>
					</div>
					<!-- /Report Filter -->

					
					

				
				</div>
			</div>
			<!-- /Page Wrapper -->