/**
 *  NOTICE OF LICENSE
 *
 *  This product is licensed for one customer to use on one installation (test stores and multishop included).
 *  Site developer has the right to modify this module to suit their needs, but can not redistribute the module
 *  in whole or in part. Any other use of this module constitues a violation of the user agreement.
 *
 *  DISCLAIMER
 *
 *  NO WARRANTIES OF DATA SAFETY OR MODULE SECURITY ARE EXPRESSED OR IMPLIED. USE THIS MODULE IN ACCORDANCE WITH
 *  YOUR MERCHANT AGREEMENT, KNOWING THAT VIOLATIONS OF PCI COMPLIANCY OR A DATA BREACH CAN COST THOUSANDS OF
 *  DOLLARS IN FINES AND DAMAGE A STORES REPUTATION. USE AT YOUR OWN RISK.
 *
 * @author    Software Agil Ltda
 * @copyright 2022
 * @license   See above
 */


window.addEventListener('load', function () {
    function swastarkenclAgencies(stateId) {
        $.ajax({
            url: $('#swastarkencl-config-form').data('swastarkencl-starkenendpoints-link'),
            data: {
                state_id: stateId
            },
            success: function(result){
                result = JSON.parse(result);
                $('#SWASTARKENCL_ORIGIN_AGENCY').empty();
                if (result != null && result.agencies != null && result.agencies.length > 0) {
                    $('#SWASTARKENCL_ORIGIN_AGENCY_MESSAGE').remove();
                    for(i in result.agencies) {
                        if (result.agencies[i].status == "ACTIVE" && result.agencies[i].code_dls != null) {
                            var newOption = $('<option>', {
                                value: result.agencies[i].code_dls,
                                text: result.agencies[i].name 
                                    + ", " 
                                    + $('#SWASTARKENCL_ORIGIN_AGENCY').data('swastarkencl-location-label') 
                                    + " " 
                                    + result.agencies[i].address 
                            });
                            if (parseInt($('#SWASTARKENCL_ORIGIN_AGENCY').data('swastarkencl-origin-agency')) == parseInt(result.agencies[i].code_dls)) {
                                newOption.attr('selected', 'selected');
                            }
                            $('#SWASTARKENCL_ORIGIN_AGENCY').append(newOption);
                        }
                    }
                } else {
                    if ($('#SWASTARKENCL_ORIGIN_AGENCY_MESSAGE').length == 0) {
                        $('.swastarkencl-help-message').append(`
                            <div id="SWASTARKENCL_ORIGIN_AGENCY_MESSAGE">
                                <span class="text-danger">`
                                + $('#SWASTARKENCL_ORIGIN_AGENCY').data('swastarkencl-no-agencies-message') +
                                `</span>
                            </div>
                        `);
                    }
                }
            }
        });
    }

    function swastarkenclCostCenters(checkingAccount) {
        $.ajax({
            url: $('#swastarkencl-config-form').data('swastarkencl-starkenendpoints-link'),
            data: {
                ctacte: checkingAccount
            },
            success: function(data){
                data = JSON.parse(data);
                $('#SWASTARKENCL_CENTER_COST_SELECTED').empty();
                if (data.length > 0) {
                    for(i in data) {
                        var newOption = $('<option>', {
                            value: data[i].id,
                            text: data[i].descripcion.trim()
                        });
                        $('#SWASTARKENCL_CENTER_COST_SELECTED').append(newOption);
                    }
                }
            }
        });
    }

    $("input[name='SWASTARKENCL_ENABLE_CHECKING_ACCOUNT']").change(function(){
        if ($(this).val() == 0) {
            $("#SWASTARKENCL_CHECKING_ACCOUNT_SELECTED").closest('.form-group').addClass('hidden d-none');
            $("#SWASTARKENCL_CENTER_COST_SELECTED").closest('.form-group').addClass('hidden d-none');
        } else {
            $("#SWASTARKENCL_CHECKING_ACCOUNT_SELECTED").closest('.form-group').removeClass('hidden d-none');
            $("#SWASTARKENCL_CENTER_COST_SELECTED").closest('.form-group').removeClass('hidden d-none');
        }
    });

    $("#PS_SHOP_STATE_ID").change(function(){
        swastarkenclAgencies($(this).children("option:selected").val());
    });
    swastarkenclAgencies($("#PS_SHOP_STATE_ID").children("option:selected").val());

    $("#SWASTARKENCL_CHECKING_ACCOUNT_SELECTED").change(function(){
        let checkingAccount = $(this).children("option:selected").val().split('-');

        $("#SWASTARKENCL_CLIENT_RUT").val($(this).children("option:selected").data('swastarkencl-checking-account-rut'));
        swastarkenclCostCenters(checkingAccount[0]);
    });

    if (!/^\d{8}\-{1}[\w|\d]{1}$/.test($("#SWASTARKENCL_CLIENT_RUT").val())) {
        $("#SWASTARKENCL_CLIENT_RUT").val(
            $("#SWASTARKENCL_CHECKING_ACCOUNT_SELECTED").children("option:selected").data('swastarkencl-checking-account-rut')
        );
    }

    +(($) => {
        let checkingAccount = $("#SWASTARKENCL_CHECKING_ACCOUNT_SELECTED").children("option:selected").val().split('-');
        swastarkenclCostCenters(checkingAccount[0]);
    })($);
});
