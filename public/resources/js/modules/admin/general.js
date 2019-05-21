function General() {

    // because this is overwritten on jquery events
    var self = this;

    this.itemData = {};

    /**
     * Initialize events
     */
    this._init = function() 
    {

        self.set_events();
        self.set_configs();

    }

    /**
    * events delaration
    */
    this.set_events = function()
    {

        $('.modalForm').submit(function(e) {
            e.preventDefault();
            Utils.save_form(this, function() {
                location.reload();
            });
        });

    }

    /**
    * usage of different libraries and plugins
    */
    this.set_configs = function()
    {
        
    }

    this.getData = function(id)
    {   
        var match = false;
        $.each(self.itemData, function(i,e){
            if (e.id == id) {
                match = e;
                return false;
            }
        });

        return match;
    }

    this.updateBillerLogo = function(biller_code)
    {   

        var form  = '#billerLogoForm';
        var modal = '#billerLogoModal';
        Utils.show_form_modal(modal, form, 'Update Biller Logo', function(){
            $(form).find('.image-preview').prop('src', $('#biller_' + biller_code).find('.logo-small').prop('src'));
            $(form).find('#Code').val(biller_code);
            $(form).find('#biller_name').val($('#biller_' + biller_code).find('td:nth(1)').text());
            $(form).find('#biller_type').val($('#biller_' + biller_code).find('td:nth(2)').data('val'));
        });

    }


    this.updateEncashService = function(code)
    {
        var form  = '#encashServiceForm';
        var modal = '#encashServiceModal';
        Utils.show_form_modal(modal, form, 'Update Encash Service', function(){
            $(form).find('.image-preview').prop('src', $('#item_' + code).find('.logo-small').prop('src'));
            $(form).find('#Code').val(code);
            $(form).find('#service_name').val($('#item_' + code).find('td:nth(1)').text());
            $(form).find('#service_description').val($('#item_' + code).find('td:nth(3)').text());
        });
    }


    this.viewOrderInvoice = function(code)
    {   

        $.LoadingOverlay("show", {zIndex: 999});
        $.ajax({
            url: window.base_url('orders/invoice/' + code),
            type: 'GET',
            success: function (response) {
                if (response.length > 0) {
                    $('#invoiceModal').find('.modal-body').html(response);
                    $('#invoiceModal').modal('show');
                }
            },
            complete: function() {
                $.LoadingOverlay("hide");
            }
        });

    }

    this.editOutlet = function(id)
    {   
        var data = this.getData(id);

        console.log(data);
        if (data) {
            var form  = '#partnerOutletForm';
            var modal = '#partnerOutletModal';
            Utils.show_form_modal(modal, form, false, function(){
                Utils.set_form_input_value(form, data);
                self.loadCityOptions('#City', '#Province', '#Barangay', data.City, function(){
                    self.loadBarangayOptions('#Barangay', '#City', data.Barangay);
                });
            });
        }
    }






    this.loadCityOptions = function(target, e, baragay_target, selected = false, callback = false)
    {
        $(target).html(window.emptySelectOption).prop('disabled', true);
        $(baragay_target).html(window.emptySelectOption).prop('disabled', true);

        $.LoadingOverlay("show");

        $.get(window.public_url('get/city'), {'provCode' : $(e).val()}).done(function(response) {
            if (response.status) {
                var options = window.emptySelectOption;
                $.each(response.data, function(i, e){
                    options += '<option value="' + e.citymunCode + '" ' + (selected && selected == e.citymunCode ? 'selected' : '') + '>' + e.citymunDesc + '</option> \n';
                });
                $(target).html(options).prop('disabled', false);
            } else {
                $(target).html(window.emptySelectOption);
            }

            if (callback) {
                callback();
            }

            $.LoadingOverlay("hide");
        });
    }

    this.loadBarangayOptions = function(target, e, selected = false, callback = false)
    {
        $(target).html(window.emptySelectOption).prop('disabled', true);

        $.LoadingOverlay("show");

        $.get(window.public_url('get/barangay'), {'citymunCode' : $(e).val()}).done(function(response) {
            if (response.status) {
                var options = window.emptySelectOption;
                $.each(response.data, function(i, e){
                    options += '<option value="' + e.brgyCode + '" ' + (selected && selected == e.brgyCode ? 'selected' : '') + '>' + e.brgyDesc + '</option> \n';
                });
                $(target).html(options).prop('disabled', false);
            } else {
                $(target).html(window.emptySelectOption);
            }

            if (callback) {
                callback();
            }

            $.LoadingOverlay("hide");
        });
    }

}


var General = new General();
$(document).ready(function(){
    General._init();
});