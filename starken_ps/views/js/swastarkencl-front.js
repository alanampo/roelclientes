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

function swastarkenclShowAgencyDetails(agencyDLSCode) {
    try {
        var agencies = JSON.parse($('#swastarkencl-top-hook-data').attr('data-swastarkencl-commune-agencies'));
        if (agencies != null && agencies.length > 0) {
            for(i in agencies) {
                if (agencies[i].code_dls == agencyDLSCode) {
                    $('#swastarkencl_agency_location').attr(
                        'href',
                        'https://www.google.com/maps/@'+agencies[i].latitude+','+agencies[i].longitude+',18z'
                    )
                    $('#swastarkencl_agency_address_value').html(agencies[i].address);

                    $('#swastarkencl_agency_phone_value').html(agencies[i].phone);

                    $('#swastarkencl_agency_delivery_value').html(
                        agencies[i].delivery ? $('#swastarkencl-top-hook-data').data('swastarkencl-yes-label') : $('#swastarkencl-top-hook-data').data('swastarkencl-no-label')
                    );
                    
                    $('#swastarkencl_agency_weight_restrictions_value').html(agencies[i].weight_restriction);
                }
            }
        }
    } catch (error) {
        console.log(error);
    }
    
}

function swastarkenclSaveAgency() {
    if (
        $('#checkout-delivery-step').hasClass('-current')
        || $('#carrier_area #order_step li.four.step_current').length >= 1
        || $('#carrier_area #opc_delivery_methods').length >= 1
        // If One Page Checkout Module is in use
        || $('#onepagecheckoutps_step_two').length >= 1
    ) {
        $("#js-delivery").find("button[type=submit]").prop('disabled', false);
        $.ajax({
            type: 'POST',
            url: $('#swastarkencl-top-hook-data').data('swastarkencl-starkenendpoints-link'),
            data: {
                agency_dls: $('#swastarkencl_list_of_agencies').children("option:selected").val(),
                state_id: $('#swastarkencl_list_of_communes').children("option:selected").val(),
                customer_id: $('#swastarkencl-top-hook-data').data('swastarkencl-logged-user'),
                payment_on_arrival: $('#swastarkencl_payment_on_arrival').is(':checked') ? 1 : 0,
            },
            success: function(data){
                try {
                    data = JSON.parse(data);
                    if (!data.result) {
                        $("#swastarkencl_agencies_message").remove();
                        if (!$('#swastarkencl-top-hook-data').data('swastarkencl-indirect-destination')) {
                            $('#swastarkencl-top-hook-data').css({'display' : 'block'});
                            $('#swastarkencl_list_of_agencies').parent().append(`
                                <span class="text-danger" id="swastarkencl_agencies_message">` + data.message + `</span>
                            `);
                            $("#js-delivery").find("button[type=submit]").prop('disabled', true);
                        } else {
                            $('#swastarkencl-top-hook-data').css({'display' : 'none'});
                        }
                    } else {
                        $("#js-delivery").find("button[type=submit]").prop('disabled', false);
                    }
                } catch(e) {
                    console.log(e);
                }
            }
        }).done(function(result) {
            $.ajax({
                url: $('#swastarkencl-top-hook-data').data('swastarkencl-starkenendpoints-link'),
                data: {
                    rate: true
                },
                success: function(result) {
                    if (window.swastarkenclRate == false) {
                        window.location.reload();
                    }
                }
            });
        });
    }
}

function swastarkenclAgencies(stateId, onlyAgenciesDetails) {
    $.ajax({
        url: $('#swastarkencl-top-hook-data').data('swastarkencl-starkenendpoints-link'),
        data: {
            state_id: stateId
        },
        success: function(result){
            result = JSON.parse(result);
            $('#swastarkencl_list_of_agencies').empty();

            var newOption = $('<option>', {
                value: null,
                text: $('#swastarkencl-top-hook-data').data('swastarlencl-select-an-agency')
            });

            $('#swastarkencl_list_of_agencies').append(newOption);

            if (result != null && result.agencies != null && result.agencies.length > 0) {
                $('#swastarkencl-top-hook-data').attr('data-swastarkencl-commune-agencies', JSON.stringify(result.agencies));

                $('#swastarkencl_agencies_message').remove();
                for(i in result.agencies) {
                    if (result.agencies[i].status == "ACTIVE" && result.agencies[i].code_dls != null) {
                        var newOption = $('<option>', {
                            value: result.agencies[i].code_dls,
                            text: result.agencies[i].name 
                                + ", " 
                                + $('#swastarkencl_list_of_agencies').data('swastarkencl-location-label').toString().toUpperCase() 
                                + " " 
                                + result.agencies[i].address.toString().toUpperCase() 
                        });
                        if (parseInt($('#swastarkencl_list_of_agencies').data('swastarkencl-destination-agency-dls-code')) == parseInt(result.agencies[i].code_dls)) {
                            newOption.attr('selected', 'selected');
                        }
                        $('#swastarkencl_list_of_agencies').append(newOption);
                    }
                }

                $('#swastarkencl_list_of_agencies, #swastarkencl_list_of_communes').parent().find('span').css('width', '400px');
                $('#swastarkencl_list_of_agencies, #swastarkencl_list_of_communes').parent().css('width', '100%');

                if (window.swastarkenclRate) {
                    if (!$('.swastarkencl-agencies-list-wrapper').hasClass('swastarlencl-hide-agencies-selector')) {
                        $('.swastarkencl-agencies-list-wrapper').show();
                    }
                    swastarkenclShowAgencyDetails(
                        $('#swastarkencl_list_of_agencies').children("option:selected").val()
                    );
                }

                if (onlyAgenciesDetails == null || onlyAgenciesDetails == false || onlyAgenciesDetails == undefined) {
                    swastarkenclSaveAgency();
                }
            } else if(result != null && result.city != null && result.city.destino_indirecto) {
                $('.swastarkencl-agencies-list-wrapper').hide();
                swastarkenclSaveAgency();
            } else {
                if ($('#swastarkencl_agencies_message').length == 0) {
                    $('.swastarkencl-help-message').append(`
                        <div id="swastarkencl_agencies_message">
                            <span class="text-danger" style="padding: 15px; display: block; text-align: justify;">`
                            + $('#swastarkencl-top-hook-data').data('swastarkencl-no-agencies-message') +
                            `</span>
                        </div>
                    `);
                }
            }
            
            if ($('#swastarkencl-top-hook-data').data('swastarlencl-ps16')) {
                $('#swastarkencl_list_of_agencies').click();
            }
        }
    });
}

function swastarkenclCheckCarrierPaymentType(carrier_id, label_selector) {
    $.ajax({
        type: 'POST',
        url: $('#swastarkencl-top-hook-data').data('swastarkencl-starkenendpoints-link'),
        data: {
            check_carrier_id: carrier_id,
        },
        success: function(data) {
            data = JSON.parse(data);
            if (data.carrier_payment_type == 3) {
                $('.swastarkencl-arrival-message').remove();
                if ($('#swastarkencl-top-hook-data').data('swastarlencl-ps16')) {
                    $('#'+label_selector).closest('tr td .swastarkencl-arrival-message').remove();
                    $('#'+label_selector).closest('tr').find('td').eq(2).append(`
                        <small class="swastarkencl-arrival-message" style="display: block !important; color:red;">
                            ` + data.arrival_payment_message + `
                        </small>
                    `);
                } else {
                    $('label[for=' + label_selector + ']').append(`
                        <small class="swastarkencl-arrival-message" style="display: block !important; color:red;">
                            ` + data.arrival_payment_message + `
                        </small>
                    `);
                }
            }

            if (data.carrier_delivery_type == 'DOMICILIO') {
                $('.swastarkencl-agencies-list-wrapper').addClass('swastarlencl-hide-agencies-selector').hide();
            } else {
                $('.swastarkencl-agencies-list-wrapper').removeClass('swastarlencl-hide-agencies-selector').show();
            }
        }
    });
}

function hideStarkenSectionIfItShouldBe() {
    $('.delivery-options .delivery-option input[type=radio], .delivery_options .delivery_option input[type=radio]').on('change', function() {
        if (
            $('#swastarkencl-top-hook-data').data('swastarlencl-carrier-ids').split(', ').indexOf($(this).attr('id').replace('delivery_option_', '')) !== -1
            || $('#swastarkencl-top-hook-data').data('swastarlencl-carrier-ids').split(', ').indexOf($(this).attr('value').replace(',', '')) !== -1
        ) {
            if ($('#swastarkencl-top-hook-data').data('swastarlencl-ps16')) {
                swastarkenclCheckCarrierPaymentType(
                    $(this).attr('value').replace(',', ''),
                    $(this).attr('id')
                );
            } else {
                swastarkenclCheckCarrierPaymentType(
                    $(this).attr('id').replace('delivery_option_', ''),
                    $(this).attr('id')
                );
            }

            if ($('#swastarkencl-top-hook-data').data('swastarlencl-ps16')) {
                $('#swastarkencl_list_of_agencies, #swastarkencl_list_of_communes').parent().find('span').css('width', '400px');
                $('#swastarkencl_list_of_agencies, #swastarkencl_list_of_communes').parent().css('width', '100%');
            }
            $('#swastarkencl-top-hook-data').show();
        } else {
            $('#swastarkencl-top-hook-data').hide();
            // Delete elements with class .swastarkencl-arrival-message 
            // related with swastarkenclCheckCarrierPaymentType()
            $('label .swastarkencl-arrival-message').remove();
        }
    });

    var hideStarkenSection = false;
    var starkenDeliveryOptionCount = 0;
    $('.delivery-options .delivery-option input[type=radio], .delivery_options .delivery_option input[type=radio]').each(function(index, object) {
        var deliveryOption = null;
        if ($('#swastarkencl-top-hook-data').data('swastarlencl-ps16')) {
            deliveryOption = object.value.replace(',', '')
        } else {
            deliveryOption = object.id.replace('delivery_option_', '');
        }

        if ($('#swastarkencl-top-hook-data').data('swastarlencl-carrier-ids').split(', ').indexOf(deliveryOption) !== -1) {
            starkenDeliveryOptionCount++;
        }

        if (
            $(object).prop('checked')
            && $('#swastarkencl-top-hook-data').data('swastarlencl-carrier-ids').split(', ').indexOf(deliveryOption) !== -1
        ) {
            swastarkenclCheckCarrierPaymentType(
                deliveryOption,
                object.id
            );
        }

        if (
            $(object).prop('checked')
            && $('#swastarkencl-top-hook-data').data('swastarlencl-carrier-ids').split(', ').indexOf(deliveryOption) === -1
        ) {
            hideStarkenSection = true;
        }
    });

    if (
        hideStarkenSection
        && $('.delivery-options .delivery-option input[type=radio], .delivery_options .delivery_option input[type=radio]').length > 1
        && starkenDeliveryOptionCount > 0
    ) {
        $('#swastarkencl-top-hook-data').hide();
    }
}

function swastarkenclRunOnReady() {
    if (parseInt($('#swastarkencl-top-hook-data').data('swastarkencl-user-commune')) > 0) {
        swastarkenclAgencies(
            $('#swastarkencl_list_of_communes').children("option:selected").val(),
            true
        );
        $("button[type='submit']").show();
        $('.swastarkencl-getting-rate-message').hide();
        $('.swastarkencl-agencies-list-wrapper').show();
    }

    var swastarkencl_agencies = $('#swastarkencl-top-hook-data').data('swastarkencl-commune-agencies');
    if (swastarkencl_agencies != null && swastarkencl_agencies.length > 0) {
        for(i in swastarkencl_agencies) {
            if (swastarkencl_agencies[i].status == "ACTIVE" && swastarkencl_agencies[i].code_dls != null) {
                var newOption = $('<option>', {
                    value: swastarkencl_agencies[i].code_dls,
                    text: swastarkencl_agencies[i].name
                });
                $('#swastarkencl_list_of_agencies').append(newOption);
            }
        }
    }
    hideStarkenSectionIfItShouldBe();
}

$(document).ready(function() {
    window.swastarkenclRate = true;
    $('.swastarkencl-agencies-list-wrapper').hide();
    $(".delivery-options button[type='submit']").hide();
    $('.swastarkencl-getting-rate-message').show();

    $('body').on('change', '#swastarkencl_list_of_communes', function() {
        window.swastarkenclRate = false;
        $('.swastarkencl-getting-rate-message').show();
        $('.swastarkencl-getting-rate-message').text(
            $('#swastarkencl-top-hook-data').data('swastarkencl-loading-message')
        );
        $('.swastarkencl-agencies-list-wrapper').hide();
        swastarkenclAgencies($('#swastarkencl_list_of_communes').children("option:selected").val());
    });
    
    
    if ($('#onepagecheckoutps_step_two').length >= 1) {
        // Because One Page Checkout Module is asyncronous
        var observer = new MutationObserver(function(mutations) {
            swastarkenclRunOnReady()
        });
        
        observer.observe(
            document.querySelector('.loading_big'),
            {attributes: true}
        );
    } else {
        swastarkenclRunOnReady();
    }

    $('body').on('change', '#swastarkencl_list_of_agencies', function(){
        swastarkenclShowAgencyDetails($(this).children("option:selected").val());
    });

    $('body').on('change', '#swastarkencl_list_of_agencies', function(){
        window.swastarkenclRate = false;
        $('.swastarkencl-getting-rate-message').show();
        $('.swastarkencl-getting-rate-message').text(
            $('#swastarkencl-top-hook-data').data('swastarkencl-loading-message')
        );
        $('.swastarkencl-agencies-list-wrapper').hide();
        swastarkenclSaveAgency();
    });

    $('body').on('change', '#swastarkencl_payment_on_arrival', function(){
        swastarkenclSaveAgency();
    });

    hideStarkenSectionIfItShouldBe();
});