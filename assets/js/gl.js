$(document).ready(function(){
const   BASE_URL = "http://localhost/jev-jaro/";

$('#sl_card').hide();
$('#set_bal').hide();
$('#accounts_box').hide();


// radio on change
$('input[name="l_type"]').change(function() {
    // Get the value of the selected radio button
    var selectedValue = $('input[name="l_type"]:checked').val();
    console.log(selectedValue)
  
    if(selectedValue == "g"){
      $('#sl_card').hide()
      $('#gl_card').show()
      $('#set_bal').hide();
  
    }else{
        $('#sl_card').show()
        $('#gl_card').hide()
        $('#set_bal').show();
    }
  
  
  
  })
  
  
  //end

  
// ledger type on change
$('input[name="g_type"]').change(function() {
    // Get the value of the selected radio button
    var selectedValue = $('input[name="g_type"]:checked').val();
    console.log(selectedValue)
  
    if(selectedValue == "all"){
      $('#accounts_box').hide()
      
  
    }else{
        $('#accounts_box').show()
    }
  
  
  
  })
  
  
  //end



//click bal button
$('#set_bal').click(function(){
    let acc_code = $('#acc_code').val();
    let acc_name = $('#acc_name').val();

    if(acc_code ==''){
        alert('Please select an account code/title first!!!')
    }else{
        
    $('#balance_modal').modal('show');
    $('#acc_name_box').text(acc_name);
    $('#acc_code_box').text(acc_code);
    $('#acc_code_hidden').val(acc_code);
    }

    

})


$('#set_balance').click(function(){
    let acc_code = $('#acc_code_hidden').val()
    let debit = $('#bal_debit').val();
    let credit = $('#bal_credit').val();
    let year = $('#tb_year').val()
    

    if(debit == 0 && credit == 0 ){
        alert('Please input a amount of beginning balance on either debit or credit!!!')
    }else{
        $.post(`${BASE_URL}setbalance`,{acc_code : acc_code,debit:debit,credit:credit,year:year},function(res){
            if(res){
                Swal.fire({
                    title: `Set Beginning balance for account code ${acc_code}`,
                    icon: "success",
                    timer: 3000
              
                    })
                    setTimeout(function(){
                        $('#balance_modal').modal('hide');
                        location.reload(true);
                    },3000)
                   
            }else{
                Swal.fire({
                    title: `Failed to set beggining balance!!`,
                    icon: "error",
                    timer: 5000
              
                    })
            }
        })
    }
})


//end bal button


//select row function

var selectedRow = null;

    function highlightRow(row) {
      if (selectedRow) {
        selectedRow.removeClass("selected");
      }
      selectedRow = row;
      row.addClass("selected");
    }

    $("#tbl_acclist tbody").on('click','tr', function() {
       let id = $(this).find("td:eq(0)").text();
       let name = $(this).find("td:eq(1)").text();
       
        highlightRow($(this));
        $('#acc_code').val(id);
        $('#acc_name').val(name);

      });  


//end



   //accounts table gl

   const tbl_accounts = $('#tbl_acclist').DataTable({
    "pagingType": "full_numbers",
          "lengthMenu": [
            [-1, 70,100],
            ["All", 70, 100]
          ],
           scrollY:  "500px",
          scrollCollapse: true,
          
          searchable: true,
          responsive: true,
          language: {
            search: "_INPUT_",
            info : "Showing _START_ to _END_ of _TOTAL_ Accounts",
            loadingRecords : "Loading Accounts.....",
            searchPlaceholder: "Search Accounts....",
            infoFiltered:   "(filtered from _MAX_ total Accounts)",
            zeroRecords :    "No Accounts found",
          },
          
          
          columnDefs: [
                 
            
                  ]
  
  
  
  });



   //end

   
//populate accounts table
  function getAccounts(){
    $.get(`${BASE_URL}getaccounts`,function(res){
     

      
   tbl_accounts.clear();
  
   $.each(res,function(i,key){
           
     
           
    
   
       tbl_accounts.row.add([key.code,key.name]);
     
       
     
      
            });
     tbl_accounts.draw();


    })
  }

getAccounts();

//end


    //datepicker
    var currentDate = new Date(); 
    // checkRange();  

$('#qbox').hide();
let f = $('#tb_range').val()
changeDate(f);
$('#sdate').datetimepicker({
    format: 'MM/D/YYYY',
    defaultDate : currentDate,
    showTodayButton : true,
    icons: {
      up: "fas fa-angle-up",
      down: "fas fa-angle-down",
      next: 'fas fa-angle-right',
      previous: 'fas fa-angle-left'
    }
  });


  
$('#edate').datetimepicker({
    format: 'MM/D/YYYY',
    defaultDate : currentDate,
    showTodayButton : true,
    icons: {
      up: "fas fa-angle-up",
      down: "fas fa-angle-down",
      next: 'fas fa-angle-right',
      previous: 'fas fa-angle-left'
    }
  });

    //end


//generate tb

// $('#generate_tb').click(function(e){
   
//     let sdate = $('#sdate').val()
//     let edate = $('#edate').val()
//     console.log(typeof sdate,typeof edate)

// })

//end

  //reports logic
  var currentYear = new Date().getFullYear();
  var startYear = currentYear - 4;
  var endYear = currentYear;

  for (var year = endYear; year >= startYear; year--) {
      $('#tb_year').append($('<option>', {
          value: year,
          text: year
      }));
  }


  $('#tb_range').change(function () {
    var filterType = $(this).val();

    if (filterType === 'C') {
        $('#sdate, #edate').prop('readonly', false);
        $('#mbox, #ybox, #qbox').hide();
        
    } else {
        $('#sdate, #edate').prop('readonly', true);

        if (filterType === 'M') {
            $('#mbox').show();
            $('#ybox').show();
            $('#qbox').hide();

            changeDate(filterType)
        }else if(filterType === 'Q'){
            $('#mbox').hide();
            $('#qbox,#ybox').show();
            changeDate(filterType)

        } else if (filterType === 'A') {
            $('#mbox,#qbox').hide();
            $('#ybox').show();
            changeDate(filterType)
        }
    }

   
});


//month change
$('#tb_month').change(function(){
        console.log('change')
        var selectedMonth = $('#tb_month').val();
        var selectedYear = $('#tb_year').val();

        var startDate = moment(`${selectedYear}-${selectedMonth}-01`, 'YYYY-MM-DD');
        var endDate = moment(startDate).endOf('month');

        console.log(startDate,endDate)

        $('#sdate').datetimepicker('date', startDate);
        $('#edate').datetimepicker('date', endDate);
})

//end


//year change
$('#tb_year').change(function(){
    console.log('change')
    if($('#tb_range').val() === 'A'){

        var selectedYear = $('#tb_year').val();
        // Set the DateTimePicker range to cover the entire year
       
        var startDate = moment(`${selectedYear}-01-01`, 'YYYY-MM-DD');
        var endDate = moment(`${selectedYear}-12-31`, 'YYYY-MM-DD');

        console.log(startDate,endDate)

        $('#sdate').datetimepicker('date', startDate);
        $('#edate').datetimepicker('date', endDate);
    }else if($('#tb_range').val() === 'Q'){
        var selectedQuarter = $('#tb_quarter').val();
    var selectedYear = $('#tb_year').val();
    var startDate, endDate;

    switch (selectedQuarter) {
        case '1':
            startDate = moment(`${selectedYear}-01-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-03-31`, 'YYYY-MM-DD');
            break;
        case '2':
            startDate = moment(`${selectedYear}-04-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-06-30`, 'YYYY-MM-DD');
            break;
        case '3':
            startDate = moment(`${selectedYear}-07-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-09-30`, 'YYYY-MM-DD');
            break;
        case '4':
            startDate = moment(`${selectedYear}-10-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-12-31`, 'YYYY-MM-DD');
            break;
    }

    $('#sdate').datetimepicker('date', startDate);
    $('#edate').datetimepicker('date', endDate);
    }else{
        var selectedMonth = $('#tb_month').val();
        var selectedYear = $('#tb_year').val();

        var startDate = moment(`${selectedYear}-${selectedMonth}-01`, 'YYYY-MM-DD');
        var endDate = moment(startDate).endOf('month');

        console.log(startDate,endDate)

        $('#sdate').datetimepicker('date', startDate);
        $('#edate').datetimepicker('date', endDate);
    }
})


//end

//quarter change

$('#tb_quarter').change(function(){
    var selectedQuarter = $('#tb_quarter').val();
    var selectedYear = $('#tb_year').val();
    var startDate, endDate;

    switch (selectedQuarter) {
        case '1':
            startDate = moment(`${selectedYear}-01-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-03-31`, 'YYYY-MM-DD');
            break;
        case '2':
            startDate = moment(`${selectedYear}-04-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-06-30`, 'YYYY-MM-DD');
            break;
        case '3':
            startDate = moment(`${selectedYear}-07-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-09-30`, 'YYYY-MM-DD');
            break;
        case '4':
            startDate = moment(`${selectedYear}-10-01`, 'YYYY-MM-DD');
            endDate = moment(`${selectedYear}-12-31`, 'YYYY-MM-DD');
            break;
    }

    $('#sdate').datetimepicker('date', startDate);
    $('#edate').datetimepicker('date', endDate);
})





  //end

  //functions

  function changeDate(filterType){
    if (filterType === 'M'){
        var selectedMonth = $('#tb_month').val();
        var selectedYear = $('#tb_year').val();

        var startDate = moment(`${selectedYear}-${selectedMonth}-01`, 'YYYY-MM-DD');
        var endDate = moment(startDate).endOf('month');

        // $('#sdate').data("DateTimePicker").date(startDate);
        // $('#edate').data("DateTimePicker").date(endDate);

        $('#sdate').datetimepicker('date', startDate);
        $('#edate').datetimepicker('date', endDate);
    }else if(filterType === 'Q'){
        var selectedQuarter = $('#tb_quarter').val();
        var selectedYear = $('#tb_year').val();
        var startDate, endDate;

        switch (selectedQuarter) {
            case '1':
                startDate = moment(`${selectedYear}-01-01`, 'YYYY-MM-DD');
                endDate = moment(`${selectedYear}-03-31`, 'YYYY-MM-DD');
                break;
            case '2':
                startDate = moment(`${selectedYear}-04-01`, 'YYYY-MM-DD');
                endDate = moment(`${selectedYear}-06-30`, 'YYYY-MM-DD');
                break;
            case '3':
                startDate = moment(`${selectedYear}-07-01`, 'YYYY-MM-DD');
                endDate = moment(`${selectedYear}-09-30`, 'YYYY-MM-DD');
                break;
            case '4':
                startDate = moment(`${selectedYear}-10-01`, 'YYYY-MM-DD');
                endDate = moment(`${selectedYear}-12-31`, 'YYYY-MM-DD');
                break;
        }

        $('#sdate').datetimepicker('date', startDate);
        $('#edate').datetimepicker('date', endDate);

    }else if(filterType === 'A'){
        var selectedYear = $('#tb_year').val();
        // Set the DateTimePicker range to cover the entire year
       
        var startDate = moment(`${selectedYear}-01-01`, 'YYYY-MM-DD');
        var endDate = moment(`${selectedYear}-12-31`, 'YYYY-MM-DD');

        // $('#sdate').data("DateTimePicker").date(startDate);
        // $('#edate').data("DateTimePicker").date(endDate);

        $('#sdate').datetimepicker('date', startDate);
        $('#edate').datetimepicker('date', endDate);
    }
    
  }

  //check range value
  function checkRange(){
    var filterType = $(this).val();

    if (filterType === 'C') {
        $('#sdate, #edate').prop('disabled', false);
        $('#mbox, #ybox, #qbox').hide();
        
    } else {
        $('#sdate, #edate').prop('disabled', true);

        if (filterType === 'M') {
            $('#mbox').show();
            $('#ybox').show();
            $('#qbox').hide();

            changeDate(filterType)
        }else if(filterType === 'Q'){
            $('#mbox').hide();
            $('#qbox,#ybox').show();
            changeDate(filterType)

        } else if (filterType === 'A') {
            $('#mbox,#qbox').hide();
            $('#ybox').show();
            changeDate(filterType)
        }
    }
  }


  //


})