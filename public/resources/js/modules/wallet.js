function Wallet() {

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

    },

    /**
    * events delaration
    */
    this.set_events = function()
    {
        $('.modalForm').submit(function(e) {
            e.preventDefault();
            Utils.save_form(this);
        });

        var ajaxReq = null;
        $('#encashForm #Amount').on('keyup change',function() {
            if (ajaxReq != null) ajaxReq.abort();
            ajaxReq =  $.ajax({
                url: window.public_url('get/n2w/' + $(this).val()),
                success: function(response){
                    $('#encashForm').find('.number_in_words').text(response);
                 }
            });
        });
    }

    /**
    * usage of different libraries and plugins
    */
    this.set_configs = function()
    {

    },


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


    /**
    * add
    */
    this.addDeposit = function()
    {   

        var form  = '#depositForm';
        var modal = '#depositModal';
        Utils.show_form_modal(modal, form, false, function(){

        });

    }

    /**
    * encash
    */
    this.encashRequest = function()
    {   

        var form  = '#encashForm';
        var modal = '#encashModal';
        Utils.show_form_modal(modal, form, false, function(){

        });

    }

    /**
    * padala
    */
    this.moneyPadalaRequest = function()
    {   

        var form  = '#moneyPadalaForm';
        var modal = '#moneyPadalaModal';
        Utils.show_form_modal(modal, form, false, function(){

        });

    }

    /**
    * eload
    */
    this.eloadRequest = function()
    {   

        var form  = '#eloadForm';
        var modal = '#eloadModal';
        Utils.show_form_modal(modal, form, false, function(){

        });

    }


    this.payBills = function(id)
    {

        var data  = self.getData(id);
        if (data) {
            var form  = '#paymentForm';
            var modal = '#paymentModal';
            Utils.show_form_modal(modal, form, false, function(){
                $(form).find('#Biller').val(data.Code);
                $(form).find('#AccountNo').prop('placeholder', data.FirstField).prop('maxlength', data.FirstFieldWidth);
                $(form).find('#AccountNoLabel').text(data.FirstField);
                $(form).find('#Identifier').prop('placeholder', data.SecondField).prop('maxlength', data.SecondFieldWidth);
                $(form).find('#IdentifierLabel').text(data.SecondField);
            });
        }
    }

    this.sendELoad = function()
    {
        
    }

}


var Wallet = new Wallet();
$(document).ready(function(){
    Wallet._init();
});