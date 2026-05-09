$(document).ready(function(){
    //datepicker
    var currentDate = new Date(); 
    // checkRange();  

    $('#com_div').hide()
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
  var startYear = currentYear - 5;
  var endYear = currentYear;

  
  for (var year = endYear; year >= startYear; year--) {
    $('#tb_year').append($('<option>', {
        value: year,
        text: year
    }));
}

  for (var year = endYear; year >= startYear; year--) {
      $('#fse_year').append($('<option>', {
          value: year,
          text: year
      }));
  }

  
  for (var year = endYear; year >= startYear; year--) {
    $('#fss_year').append($('<option>', {
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


  //radio change

// radio on change
$('input[name="fs_type"]').change(function() {
    // Get the value of the selected radio button
    var selectedValue = $('input[name="fs_type"]:checked').val();
    console.log(selectedValue)
  
    if(selectedValue == "custom"){
      $('#cs_div').show()
      $('#com_div').hide()
      $('#year_container').show()
      
  
    }else{
      $('#cs_div').hide()
      $('#com_div').show()
      $('#year_container').hide()
      
     
    }
  
  
  
  })


  //end


  if (typeof initSpreadsheetPreview === "function") {
    initSpreadsheetPreview({
      formSelector: "#report_form",
      buttonSelector: "#preview_report_btn",
      previewCardSelector: "#preview_card",
      sheetSelector: "#preview_sheet_selector",
      containerSelector: "#preview_grid_container",
      previewUrl: $("#preview_report_btn").data("previewUrl")
    });
  }

})