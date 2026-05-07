$(document).ready(function(){
const BASE_URL = 'http://localhost/jev-jaro/';


const tbl_resp = $('#tbl_resp').DataTable({
	"pagingType": "full_numbers",
        "lengthMenu": [
          [50, 70,100, -1],
          [50, 70, 100, "All"]
        ],
         scrollY:  "500px",
        scrollCollapse: true,
        
        searchable: true,
        responsive: true,
        language: {
          search: "_INPUT_",
          info : "Showing _START_ to _END_ of _TOTAL_ Respondents",
          loadingRecords : "Loading Respondents.....",
          searchPlaceholder: "Search Respondents....",
          infoFiltered:   "(filtered from _MAX_ total Respondents)",
          zeroRecords :    "No Respondents found",
        },
        
        
        columnDefs: [
                {  // set default column settings
                    searchable : false,
                    orderable: false,
                    targets: 0
                }
          
                ]



});

// //teams table

const tbl_teams = $('#tbl_teams').DataTable({
	"pagingType": "full_numbers",
        "lengthMenu": [
          [50, 70,100, -1],
          [50, 70, 100, "All"]
        ],
         scrollY:  "500px",
        scrollCollapse: true,
        
        searchable: true,
        responsive: true,
        language: {
          search: "_INPUT_",
          info : "Showing _START_ to _END_ of _TOTAL_ teams",
          loadingRecords : "Loading teams.....",
          searchPlaceholder: "Search teams....",
          infoFiltered:   "(filtered from _MAX_ total teams)",
          zeroRecords :    "No teams found",
        },
        
        
        columnDefs: [
                {  // set default column settings
                    searchable : false,
                    orderable: false,
                    targets: 0
                }
          
                ]



});



// end teams table


//teams table
const tbl_members = $('#add_team_modal #tbl_members').DataTable({
	"pagingType": "full_numbers",
        "lengthMenu": [
          [50, 70,100, -1],
          [50, 70, 100, "All"]
        ],
         scrollY:  "500px",
        scrollCollapse: true,
        
        searchable: true,
        responsive: true,
        language: {
          search: "_INPUT_",
          info : "Showing _START_ to _END_ of _TOTAL_ teams",
          loadingRecords : "Loading teams.....",
          searchPlaceholder: "Search teams....",
          infoFiltered:   "(filtered from _MAX_ total teams)",
          zeroRecords :    "No teams found",
        },
        
        
        columnDefs: [
                {  // set default column settings
                    searchable : false,
                    orderable: false,
                    targets: 0
                }
          
                ]



});



//end teams table



// load respondents
const loadRespondents= function() {
  
  //load
  
  
  
  var a = 1;
  
  $.get(`${BASE_URL}getrespondents`,function(response){
   console.log(response)
  
   tbl_resp.clear();
  
  $.each(response,function(i,key){
          
      let a = 10000 + parseInt(key.resp_id);  
          
      
  
      tbl_resp.row.add(['<input class="form-check-input" type="checkbox" name="resp[]">',a.toString().slice(1),key.fullname,`<img class="avatar avatar-md me-2 avatar-img rounded-circle" src="${BASE_URL}assets/uploads/${key.pic}" alt="User Image">`,key.date_added]);
    
      
    
     
           });
    tbl_resp.draw();
  
  
  
  });
  
  
  
  
  
   //end lod
  };

 
  





// end load respondents


// // load respondents
const loadMembers= function(id=0) {
  
  //load
  
  
  
 
  
  $.get(`${BASE_URL}getmembers/${id}`,function(response){
   console.log(response)
  
   tbl_members.clear();
  
  $.each(response,function(i,key){
          
      let a = 10000 + parseInt(key.resp_id);  
          
      
  
      tbl_members.row.add([`<input class="form-check-input" type="checkbox" name="members[]" value="${key.resp_id}">`,a.toString().slice(1),key.fullname]);
    
      
    
     
           });
    tbl_members.draw();
  
  
  
  });
}
  
  
  
  
  
//    //end lod
//   };

  // loadMembers();


    
// add respondents
$('#addrespbtn').click(function(){
    let fname = $('#fname').val()
    let rpic = $('#rpic').prop('files')[0];

    let formData = new FormData();
    formData.append('fname',fname);
    formData.append('rpic',rpic);

    
    try {
        fetch(
            `${BASE_URL}addrespondent`,
            {
              method: 'POST',
              body: formData,
            },
          ).then(res=> res.json())
          .then(data=>{
            console.log(data);
            if(data){
                Swal.fire({
                    title: `Respondent Saved!`,
                    icon: "success",
                    timer: 2000
              
                    }).then(function() {
                        $('#fname').val('');
                        $('#add_resp_modal').modal('hide');
                        loadRespondents();
                       
                        
                    });
                
            }
          })
        
          
        
    } catch (error) {
        console.log(error)
    }
  })
  
  
  
  // end add respondents


  // load members
$('#tleader').change(function(){
  let resp_id = $(this).val();

  loadMembers(resp_id);

})



  // end load members on select


  // select all
  $('#select_all_members').click(function(){
    if(this.checked) { // check select status
              $('input[name="members[]"]').each(function() { //loop through each checkbox
                  this.checked = true;  //select all checkboxes with class "checkbox1"               
              });
          }else{
              $('input[name="members[]"]').each(function() { //loop through each checkbox
                  this.checked = false; //deselect all checkboxes with class "checkbox1"                       
              });         
          }
  
           var check = $('input[name="members[]"]:checked').length
            console.log(check);
  
  });



  //end select all


  // add team
$('#addteambtn').click(function(){
  let tname = $('#tname').val();
  let tleader = $('#tleader').val();
  var members = $('input[name="members[]"]:checked').map(function()
            {
                return $(this).val();
            }).get();

  $.post(`${BASE_URL}addteam`,{
    tname :tname,
    tleader : tleader,
    members : members
  },function(res){
    if(res){
      Swal.fire({
        title: `Team Saved!`,
        icon: "success",
        timer: 2000
  
        }).then(function(){
         $('#add_team_modal').modal('hide');
         $('#tname').val('');
  
        })
    }
  })
});


  //end add team


  // load teams
  const loadTeams= function() {
  
    //load
    
    
    
   
    
    $.get(`${BASE_URL}getteams`,function(response){
     console.log(response)
    
     tbl_teams.clear();
    
    $.each(response,function(i,key){
            
      let a = 10000 + parseInt(key.resp_id);  
            
        
    
        tbl_teams.row.add([`<input class="form-check-input" type="checkbox" name="teams[]" value="${key.resp_id}">`,a.toString().slice(1),key.team_name,key.team_leader,key.member_count,key.d_created]);
      
        
      
       
             });
      tbl_teams.draw();
    
    
    
    });
  }
    
    
    
    
    
  //    //end lod
  //   };
  
    // loadTeams();


  // end load teams
  


});