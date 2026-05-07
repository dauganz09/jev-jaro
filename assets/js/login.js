$(document).ready(function(){
const BASE_URL = 'http://localhost/jev-jaro/'

$('#btn_login').click(function(e){
    e.preventDefault();
    let uname = $('#uname').val()
    let pass = $('#pass').val()

    if(uname == '' || pass == ''){
        return toastr.warning("Please fill up empty fields!","Error",{closeButton:!0,tapToDismiss:!1,positionClass:"toast-top-center"})
    }

    //post login creds
    $.post(`${BASE_URL}userlogin`,{uname : uname,pass: pass},function(res){
        if(res){
            toastr.success("Login Success!","Success",{closeButton:!0,tapToDismiss:!1,positionClass:"toast-top-center"})
            window.location.href =  'dashboard/';
        }else{
            toastr.error("Invalid Login Details!","Error",{closeButton:!0,tapToDismiss:!1,positionClass:"toast-top-center"})
        }
    })



})



})