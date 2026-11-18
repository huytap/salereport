    <!-- Metis Menu Plugin JavaScript -->
    <script src="<?php echo Yii::app()->baseUrl?>/js/metisMenu.min.js"></script>

    <!-- Morris Charts JavaScript -->
    <!--<script src="<?php //echo Yii::app()->baseUrl?>/js/raphael-min.js"></script>
    <script src="<?php //echo Yii::app()->baseUrl?>/js/morris.min.js"></script>
    <script src="<?php //echo Yii::app()->baseUrl?>/js/morris-data.js"></script>-->

    <!-- Custom Theme JavaScript -->
    <script src="<?php echo Yii::app()->baseUrl?>/js/sb-admin-2.js"></script>
    <script type="text/javascript">
        $(document).click(function(event) {
            if ( !$(event.target).hasClass('dropdown')) {
                $('#navMenu').find('li.dropdown').find('ul').hide();
            }
        });
        $('#navMenu').find('li.dropdown').each(function(i, j){
            $(j).click(function() { 
                $('#navMenu').find('li.dropdown').find('ul').hide();
                $(this).find('ul').toggle(); 
            })
        })
    </script>
    <script src='<?php echo Yii::app()->baseUrl?>/js/jquery-ui-1.10.3.custom.js'></script>
    <script src="<?php echo Yii::app()->request->baseUrl; ?>/chosen/chosen.jquery.js" type="text/javascript"></script>
    <script type="text/javascript" src="<?php echo Yii::app()->baseUrl?>/js/custom.js"></script>

    <script type="text/javascript">
        $.ajax({
            url: '<?php echo Yii::app()->baseUrl?>/ajax/reminderDaily',
            type: 'post',
            data: {ent:'ent'},
            async: false,
            //dataType: 'json',
            success:function(data){
                //$('#reminder').html(data);
                $('#reminder-js').append(data);
            }
        });
    </script>
</body>

</html>