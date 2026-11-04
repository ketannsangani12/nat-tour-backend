class AllPermissions {

    /**
     * This is called to select all permissions for selected main permission/category
     * @param {*} props 
     * @returns 
     */

    constructor(){
        //add events
        this.eventListeners();
    }

    /**
     * attach events to form builder dom elements 
     */
    eventListeners() {
        $(document).off("change", ".header-permission").on("change", ".header-permission", this.initPermission.bind(this));
    }

    initPermission(event) {
        var permission = $(event.currentTarget).parent().parent().siblings().attr('id');

        if($(event.currentTarget).prop('checked')) {
            $("#" + permission).find('input[type=checkbox]').prop('checked', true);
            setTimeout(function() {
                $(event.currentTarget).parent().removeClass("collapsed");
                $(event.currentTarget).parent().parent().siblings().removeClass("collapse");
            }, 1000);
        } else {
            $("#" + permission).find('input[type=checkbox]').prop('checked', false);
        }
    }

    editModuleInitPermission(disable = false) {
        $(".accordion-item").each(function () {
            var id = $(this).children()[1].id;
            var totalCheckbox = $("#" + id).find('input[type="checkbox"]').length;
            var totalChecked = $("#" + id).find('input[type="checkbox"]:checked').length;
            if (disable == true) {
                $(this).children().children().children().attr('disabled', true);
            }

            if(totalCheckbox === totalChecked) {
                $(this).children().children().children().prop('checked', true);
            }
        });
    }

    disableInitPermission() {
        $('input[type="checkbox"]').each(function () {
            $("input.permission-checkbox").prop("disabled", true);
        });
    }

    callAccessLevel() {
         $.ajax({
          type: "GET",
          url: urlAccessLevel,
          data: {userId: userId},
          success: function(html)
          {
            if(html.length > 0){
                $('.label-permissions').attr('hidden', false);
                $('.permission-role-wise').html(html);
            }else{
                $('.label-permissions').attr('hidden', true);
                $('.permission-role-wise').html('');
            }
           
            allPermissions.editModuleInitPermission(true);
            allPermissions.disableInitPermission();
          }
        });   
    }
}

const allPermissions = new AllPermissions();