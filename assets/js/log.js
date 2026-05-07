
   //accounts table jev

   const tbl_logs = $('#tbl_logs').DataTable({
    "pagingType": "full_numbers",
          "lengthMenu": [
            [-1, 70,100],
            ["All", 70, 100]
          ],
           scrollY:  "300px",
          scrollCollapse: true,
          
          searchable: true,
          responsive: true,
          language: {
            search: "_INPUT_",
            info : "Showing _START_ to _END_ of _TOTAL_ Logs",
            loadingRecords : "Loading Logs.....",
            searchPlaceholder: "Search Logs....",
            infoFiltered:   "(filtered from _MAX_ total Logs)",
            zeroRecords :    "No Logs found",
          },
          
          
          columnDefs: [
                 
            
                  ]
  
  
  
  });



   //end