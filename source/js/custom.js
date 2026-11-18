$(function(){
    $( ".datepicker").datepicker({
        defaultDate: "",
        yearRange : "-100:+30",
        changeMonth: true,
        changeYear: true,
        dateFormat: 'dd-M-yy',
        onClose: function( selectedDate ) {
        }
    });
	var config = {
    '.chosen-select'           : {width:"150px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    var config = {
    '.chosen-select-multiple'           : {width:"291.5px"},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
    var config = {
    '.chosen-select-addnew'           : {width:"250px", is_enternew:true},
    }
    for (var selector in config) {
       $(selector).chosen(config[selector]);
    }
});