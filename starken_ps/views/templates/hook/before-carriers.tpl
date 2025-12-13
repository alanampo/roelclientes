{**
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
 *}

<div
    {if $swastarkencl_ps16} style="width: 500px" {/if}
    class="row delivery-option"
    id="swastarkencl-top-hook-data"
    data-swastarkencl-logged-user="{$swastarkencl_logged_user|escape:'html':'UTF-8'}"
    data-swastarkencl-commune-agencies=""
    data-swastarkencl-user-commune="{$swastarkencl_user_commune|escape:'html':'UTF-8'}"
    data-swastarkencl-yes-label="{l s='Yes' mod='swastarkencl'}"
    data-swastarkencl-no-label="{l s='No' mod='swastarkencl'}"
    data-swastarkencl-starkenendpoints-link="{$swastarkencl_starkenendpoints_link|escape:'html':'UTF-8'}"
    data-swastarkencl-no-agencies-message="{l s='There is no any agency in this commune, please, select another commune' mod='swastarkencl'}"
    data-swastarkencl-loading-message="{l s='Please, wait while we get the Starken rates...' mod='swastarkencl'}"
    data-swastarlencl-ps16="{$swastarkencl_ps16|escape:'html':'UTF-8'}"
    data-swastarlencl-carrier-ids="{$swastarkencl_carrier_ids|escape:'html':'UTF-8'}"
    data-swastarlencl-select-an-agency="{l s='-- PLEASE, SELECT AN AGENCY --' mod='swastarkencl'}">

    <div class="col-sm-12">
        <h5>{l s='Starken Communes' mod='swastarkencl'}</h5>
        <div class="row">
            <div class="col-sm-12">
                <div class="form-group row ">
                    <div class="col-md-12">
                        <select id="swastarkencl_list_of_communes" class="form-control">
                            <option>{l s='-- PLEASE, SELECT A COMMUNE --' mod='swastarkencl'}</option>
                            {foreach from=$swastarkencl_communes item=swastarkencl_commune}
                                <option
                                    {if $swastarkencl_user_commune == $swastarkencl_commune['commune_id']}
                                    selected="selected"
                                    {/if}
                                    value="{$swastarkencl_commune['commune_id']|escape:'html':'UTF-8'}">
                                    {$swastarkencl_commune['name']|escape:'html':'UTF-8'}
                                </option>
                            {/foreach}
                        </select>
                        <p class="text-danger swastarkencl-getting-rate-message" style="color:red;">
                            {if $swastarkencl_user_commune <= 0}
                                {l s='Please, select a commune to get Starken Rates' mod='swastarkencl'}
                            {/if}
                        </p>                      
                    </div>
                </div>
            </div>
        </div>

        <div class="swastarkencl-agencies-list-wrapper">
            <h5>{l s='Starken Agencies' mod='swastarkencl'}</h5>
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group row ">
                        <div class="col-md-12">
                            <select
                                id="swastarkencl_list_of_agencies"
                                data-swastarkencl-location-label="{l s='Located at ' mod='swastarkencl'}"
                                data-swastarkencl-destination-agency-dls-code="{$swastarkencl_destination_agency_dls_code|escape:'html':'UTF-8'}"
                                class="form-control">
                                <option value="">{l s='Loading...' mod='swastarkencl'}</option>
                            </select>
                        </div>
                        <div class="swastarkencl-help-message"></div>
                    </div>

                    <div class="row">
                        <div class="col-sm-6">
                            <dl>
                                <dt>{l s='Address' mod='swastarkencl'}</dt>
                                <dd id="swastarkencl_agency_address_value">--</dd>

                                <dt>{l s='Phone' mod='swastarkencl'}</dt>
                                <dd id="swastarkencl_agency_phone_value">--</dd>

                                <dt>{l s='Delivery' mod='swastarkencl'}</dt>
                                <dd id="swastarkencl_agency_delivery_value">--</dd>
                            </dl>
                        </div>

                        <div class="col-sm-6">
                            <dl>
                                <dt>{l s='Weight Restrinction' mod='swastarkencl'}</dt>
                                <dd id="swastarkencl_agency_weight_restrictions_value">--</dd>

                                <dt></dt>
                                <dd>
                                    <a href="#" id="swastarkencl_agency_location" target="_blank">
                                        {l s='See Location on Google Map' mod='swastarkencl'}
                                    </a>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>