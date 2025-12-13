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

<div class="row">
    <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">

        {include file='./notifications.tpl'}

        <div class="panel">
            <div>
                <div class="row">
                    <div class="col-sm-12">
                        <a style="text-align: center; display: inline-block;" href="https://addons.prestashop.com/contact-form.php?id_product=52194">
                            <i class="material-icons"></i>
                            <br>
                            {l s='Get Support' mod='swastarkencl'}
                        </a>
                        <img src="{$swastarkencl_logo|escape:'html':'UTF-8'}" class="pull-right swastarkencl-rounded-image" width="100" height="60" />
                    </div>
                </div>
            </div>
        </div>

        <form
            data-swastarkencl-starkenendpoints-link="{$swastarkencl_starkenendpoints_link|escape:'html':'UTF-8'}"
            id="swastarkencl-config-form"
            class="defaultForm form-horizontal"
            action="{$swastarkencl_form_action|escape:'html':'UTF-8'}"
            method="post"
            enctype="multipart/form-data"
            >
            <input type="hidden" name="SWASTARKENCL_GENERAL_SETTINGS" value="1" />
            <div class="panel">
                <div class="panel-heading">
                    <i class="icon-cogs"></i>
                    <span>{l s='Settings' mod='swastarkencl'}</span>
                </div>

                <div class="panel-body">
                    <div class="swastarkencl-carrier-description">
                        <div class="row">
                            <div class="col-xs-12 col-sm-12 col-md-3 col-lg-3">
                                <!-- <img
                                    src="{$swastarkencl_logo|escape:'html':'UTF-8'}"
                                    class="pull-right swastarkencl-rounded-image" /> -->
                            </div>
                            <div class="col-xs-12 col-sm-12 col-md-9 col-lg-9">
                                <p>
                                    <strong>{l s='Description:' mod='swastarkencl'}</strong>
                                    {l s='Your shipments anywhere in Chile' mod='swastarkencl'}
                                </p>
                                <p>
                                    <strong>{l s='Generated carrier IDs:' mod='swastarkencl'} </strong>
                                    {$swastarkencl_carrier_ids|default:'--'|escape:'html':'UTF-8'}
                                </p>
                                <br />
                            </div>
                        </div>
                    </div>

                    
                    <div class="tab-pane active" id="swastarkencl-settings" role="tabpanel">
                        <div class="form-wrapper">

                            <fieldset>
                                <legend>{l s='Starken API related parameters' mod='swastarkencl'}</legend>

                                <div class="form-group">
                                    <label class="control-label col-lg-3 required">
                                        {l s='API URL' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-6">
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="icon icon-link"></i></span>
                                            <input
                                                type="text"
                                                name="SWASTARKENCL_API_URL"
                                                id="SWASTARKENCL_API_URL"
                                                value="{$swastarkencl_api_url|escape:'html':'UTF-8'}" />
                                        </div>
                                        <p class="help-block">
                                            {l s='Enter the API URL to connect with Starken Service' mod='swastarkencl'}
                                        </p>
                                    </div>
                                    <div class="col-lg-3"></div>
                                </div>

                                <div class="form-group">
                                    <label class="control-label col-lg-3 required">
                                        {l s='User Token' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-6">
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="icon icon-key"></i>
                                            </span>
                                            <input
                                                type="text"
                                                name="SWASTARKENCL_USER_TOKEN"
                                                id="SWASTARKENCL_USER_TOKEN"
                                                value="{$swastarkencl_user_token|escape:'html':'UTF-8'}" />
                                        </div>
                                        <p class="help-block">
                                            {l s='Enter the User token to authenticate in Starken Service' mod='swastarkencl'}
                                        </p>
                                    </div>
                                    <div class="col-lg-3"></div>
                                </div>

                                <div class="form-group">
                                    <label class="control-label col-lg-3 required">
                                        {l s='Origin commune' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-9">
                                        <select name="PS_SHOP_STATE_ID" class="fixed-width-xl" id="PS_SHOP_STATE_ID">
                                            {foreach from=$swastarkencl_communes item=swastarkencl_commune}
                                                {if $swastarkencl_commune_id == $swastarkencl_commune['commune_id']}
                                                    <option
                                                        selected="selected"
                                                        value="{$swastarkencl_commune['commune_id']|escape:'html':'UTF-8'}">
                                                        {$swastarkencl_commune['name']|escape:'html':'UTF-8'}
                                                    </option>
                                                {else}
                                                    <option
                                                        value="{$swastarkencl_commune['commune_id']|escape:'html':'UTF-8'}">
                                                        {$swastarkencl_commune['name']|escape:'html':'UTF-8'}
                                                    </option>
                                                {/if}
                                            {/foreach}
                                        </select>
                                        <p class="help-block">
                                            {l s='Set the Origin commute to rate and generate issues' mod='swastarkencl'}
                                        </p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="control-label col-lg-3 required">
                                        {l s='Origin agency' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-9">
                                        <select
                                            name="SWASTARKENCL_ORIGIN_AGENCY"
                                            class="fixed-width-xl"
                                            id="SWASTARKENCL_ORIGIN_AGENCY"
                                            data-swastarkencl-location-label="{l s='LOCATED AT' mod='swastarkencl'}"
                                            data-swastarkencl-no-agencies-message="{l s='There is no any agency in this commune, please, select another commune' mod='swastarkencl'}"
                                            data-swastarkencl-origin-agency="{$swastarkencl_origin_agency|escape:'html':'UTF-8'}" />
                                        </select>
                                        <p class="help-block swastarkencl-help-message">
                                            {l s='Set the Origin Agency to generate issues' mod='swastarkencl'}
                                        </p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="control-label col-lg-3">
                                        {l s='Enable checking accounts?' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-6">
                                        <span class="switch prestashop-switch fixed-width-lg">
                                            <input
                                                type="radio"
                                                name="SWASTARKENCL_ENABLE_CHECKING_ACCOUNT"
                                                id="SWASTARKENCL_ENABLE_CHECKING_ACCOUNT_ON"
                                                {if $swastarkencl_enable_checking_account == true}
                                                    checked="checked"
                                                {/if}
                                                value="1" />
                                            <label for="SWASTARKENCL_ENABLE_CHECKING_ACCOUNT_ON">
                                                {l s='Yes' mod='swastarkencl'}
                                            </label>

                                            <input
                                                type="radio"
                                                name="SWASTARKENCL_ENABLE_CHECKING_ACCOUNT"
                                                id="SWASTARKENCL_ENABLE_CHECKING_ACCOUNT_OFF"
                                                {if $swastarkencl_enable_checking_account == false}
                                                    checked="checked"
                                                {/if}
                                                value="0" />
                                            <label for="SWASTARKENCL_ENABLE_CHECKING_ACCOUNT_OFF">
                                                {l s='No' mod='swastarkencl'}
                                            </label>
                                            <a class="slide-button btn"></a>
                                        </span>
                                        <p class="help-block">
                                            {l s='Enable checking account to fetch related rates' mod='swastarkencl'}
                                        </p>
                                    </div>
                                    <div class="col-lg-3"></div>
                                </div>

                                {if count($swastarkencl_checking_accounts) > 0}
                                <div
                                    {if $swastarkencl_enable_checking_account == 0}
                                    class="hidden d-none form-group"
                                    {else}
                                    class="form-group"
                                    {/if}>
                                    <label class="control-label col-lg-3">
                                        {l s='Checking Accounts' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-9">
                                        <select
                                            name="SWASTARKENCL_CHECKING_ACCOUNT_SELECTED"
                                            class="fixed-width-xl"
                                            id="SWASTARKENCL_CHECKING_ACCOUNT_SELECTED" >
                                            {foreach from=$swastarkencl_checking_accounts item=swastarkencl_checking_account}
                                                {if $swastarkencl_checking_account_selected == $swastarkencl_checking_account->codigo}
                                                    <option
                                                        selected="selected"
                                                        data-swastarkencl-checking-account-rut="{$swastarkencl_checking_account->rut|starken_add_dv:escape:'html':'UTF-8'}"
                                                        value="{$swastarkencl_checking_account->codigo|escape:'html':'UTF-8'}-{$swastarkencl_checking_account->dv|escape:'html':'UTF-8'}">
                                                        {$swastarkencl_checking_account->codigo|escape:'html':'UTF-8'}-{$swastarkencl_checking_account->dv|escape:'html':'UTF-8'}
                                                    </option>
                                                {else}
                                                    <option
                                                        data-swastarkencl-checking-account-rut="{$swastarkencl_checking_account->rut|starken_add_dv:escape:'html':'UTF-8'}"
                                                        value="{$swastarkencl_checking_account->codigo|escape:'html':'UTF-8'}-{$swastarkencl_checking_account->dv|escape:'html':'UTF-8'}">
                                                        {$swastarkencl_checking_account->codigo|escape:'html':'UTF-8'}-{$swastarkencl_checking_account->dv|escape:'html':'UTF-8'}
                                                    </option>
                                                {/if}
                                            {/foreach}
                                        </select>
                                        <p class="help-block">
                                            {l s='Select the checking account' mod='swastarkencl'}
                                        </p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="control-label col-lg-3">
                                        {l s='RUT' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-6">
                                        <div class="input-group">
                                            <span class="input-group-addon">
                                                <i class="icon icon-edit"></i>
                                            </span>
                                            <input
                                                readonly="readonly"
                                                type="text"
                                                name="SWASTARKENCL_CLIENT_RUT"
                                                id="SWASTARKENCL_CLIENT_RUT"
                                                value="{$swastarkencl_client_rut|escape:'html':'UTF-8'}" />
                                        </div>
                                        <p class="help-block">
                                            {l s='RUT' mod='swastarkencl'}
                                        </p>
                                    </div>
                                    <div class="col-lg-3"></div>
                                </div>

                                <div
                                    {if $swastarkencl_enable_checking_account == 0}
                                    class="hidden d-none form-group"
                                    {else}
                                    class="form-group"
                                    {/if}>
                                    <label class="control-label col-lg-3">
                                        {l s='Cost centers' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-9">
                                        <select
                                            name="SWASTARKENCL_CENTER_COST_SELECTED"
                                            class="fixed-width-xl"
                                            id="SWASTARKENCL_CENTER_COST_SELECTED">
                                        </select>
                                        <p class="help-block">
                                            {l s='Select the cost center' mod='swastarkencl'}
                                        </p>
                                    </div>
                                </div>
                                {/if}

                                <div class="form-group">
                                    <label class="control-label col-lg-3">
                                        {l s='Hide shipping options with cost 0.00?' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-6">
                                        <span class="switch prestashop-switch fixed-width-lg">
                                            <input
                                                type="radio"
                                                name="SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0"
                                                id="SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0_ON"
                                                {if $swastarkencl_hide_shipping_with_cost_0 == true}
                                                    checked="checked"
                                                {/if}
                                                value="1" />
                                            <label for="SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0_ON">
                                                {l s='Yes' mod='swastarkencl'}
                                            </label>

                                            <input
                                                type="radio"
                                                name="SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0"
                                                id="SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0_OFF"
                                                {if $swastarkencl_hide_shipping_with_cost_0 == false}
                                                    checked="checked"
                                                {/if}
                                                value="0" />
                                            <label for="SWASTARKENCL_HIDE_SHIPPING_WITH_COST_0_OFF">
                                                {l s='No' mod='swastarkencl'}
                                            </label>
                                            <a class="slide-button btn"></a>
                                        </span>
                                        <p class="help-block">
                                            {l s='Hide shipping options with no cost (0.00) from API.' mod='swastarkencl'}
                                        </p>
                                    </div>
                                    <div class="col-lg-3"></div>
                                </div>
                            </fieldset>                           
                            
                            <fieldset>
                                <legend>{l s='Prestashop related parameters' mod='swastarkencl'}</legend>

                                <div class="form-group">
                                    <label class="control-label col-lg-3 required">
                                        {l s='Select the Order State' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-9">
                                        <select
                                            name="SWASTARKENCL_ORDER_STATE"
                                            class="fixed-width-xl"
                                            id="SWASTARKENCL_ORDER_STATE" >
                                            {foreach from=$swastarkencl_order_states key=k item=order_state}
                                                {if $swastarkencl_order_state eq $order_state['id_order_state']}
                                                    <option
                                                        selected="selected"
                                                        value="{$order_state['id_order_state']|escape:'html':'UTF-8'}">
                                                        {$order_state['name']|escape:'html':'UTF-8'}
                                                    </option>
                                                {else}
                                                    <option
                                                        value="{$order_state['id_order_state']|escape:'html':'UTF-8'}">
                                                        {$order_state['name']|escape:'html':'UTF-8'}
                                                    </option>
                                                {/if}
                                            {/foreach}
                                        </select>
                                        <p class="help-block">
                                            {l s='Set the Order State from which the issue will be generated' mod='swastarkencl'}
                                        </p>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="control-label col-lg-3">
                                        {l s='Enable logs?' mod='swastarkencl'}
                                    </label>
                                    <div class="col-lg-6">
                                        <span class="switch prestashop-switch fixed-width-lg">
                                            <input
                                                type="radio"
                                                name="SWASTARKENCL_ENABLE_LOGS"
                                                id="SWASTARKENCL_ENABLE_LOGS_ON"
                                                {if $swastarkencl_enable_logs == true}
                                                    checked="checked"
                                                {/if}
                                                value="1" />
                                            <label for="SWASTARKENCL_ENABLE_LOGS_ON">
                                                {l s='Yes' mod='swastarkencl'}
                                            </label>

                                            <input
                                                type="radio"
                                                name="SWASTARKENCL_ENABLE_LOGS"
                                                id="SWASTARKENCL_ENABLE_LOGS_OFF"
                                                {if $swastarkencl_enable_logs == false}
                                                    checked="checked"
                                                {/if}
                                                value="0" />
                                            <label for="SWASTARKENCL_ENABLE_LOGS_OFF">
                                                {l s='No' mod='swastarkencl'}
                                            </label>
                                            <a class="slide-button btn"></a>
                                        </span>
                                        <p class="help-block">
                                            {l s='Enable the usage of Prestashop Logs. This option is useful to fixed problems' mod='swastarkencl'} 

                                            {if count($swastarkencl_logs) > 0}
                                            <a data-toggle="collapse" data-target="#swastarkencl-logs">
                                                {l s='Show logs' mod='swastarkencl'}
                                            </a>
                                            {/if}
                                        </p>
                                    </div>
                                    <div class="col-lg-3"></div>
                                </div>

                                <div class="form-group">
                                    <div class="col-lg-3"></div>
                                    <div class="col-lg-9">
                                        <label class="control-label">
                                            <input
                                                type="checkbox"
                                                {if $swastarkencl_synchronize_carriers_and_zones}
                                                checked="checked"
                                                {/if}
                                                name="SWASTARKENCL_SYNCHRONIZE_CARRIERS_AND_ZONES">
                                            {l s='Synchronize Carriers & Zones' mod='swastarkencl'}
                                        </label>
                                        <p class="help-block">
                                            {l s='Check this box if you want synchronize carriers & zones at update settings' mod='swastarkencl'}
                                        </p>
                                    </div>
                                </div>
                                
                                {if count($swastarkencl_logs) > 0}
                                <div id="swastarkencl-logs" class="collapse row" style="background: #fcfcfc; padding: 5px">
                                    <div class="col-sm-12">
                                        <h1>{l s='Module logs' mod='swastarkencl'}</h1>
                                        <br />
                                        <dl></dl>
                                        {foreach from=$swastarkencl_logs item=swastarkencl_log}
                                            {literal}
                                            <script type="text/javascript">
                                                var swalogsjson = JSON.parse({/literal}'{$swastarkencl_log["message"]}'{literal});
                                                var swalogsdateadd = {/literal}'{$swastarkencl_log["date_add"]}'{literal};
                                                $("#swastarkencl-logs dl").append(`
                                                    <dt><h2> - ` + swalogsjson.message + ` - ` + swalogsdateadd + `</h2></dt>
                                                    <dd style"background: red; ">
                                                        <strong>Request</strong>
                                                        <pre>` + JSON.stringify(swalogsjson.request ? swalogsjson.request : '') + `</pre>
                                                        <strong>Response</strong>
                                                        <pre>` + JSON.stringify(swalogsjson.response ? swalogsjson.response : '') + `</pre>
                                                    </dd>
                                                `);
                                            </script>
                                            {/literal}
                                        {/foreach}
                                    </div>
                                </div>
                                {/if}
                            </fieldset>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <button
                        type="submit"
                        value="1"
                        name="SWASTARKENCL_SUBMITTED_GENERAL_SETTINGS"
                        class="btn btn-default pull-right" />
                        <i class="process-icon-save"></i>
                        {l s='Save' mod='swastarkencl'}
                    </button>
                    <a
                        href="{$swastarkencl_form_back_action|escape:'html':'UTF-8'}"
                        class="btn btn-default pull-right">
                        <i class="process-icon-cancel"></i>
                        {l s='Cancel' mod='swastarkencl'}
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>
