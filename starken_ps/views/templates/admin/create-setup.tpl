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
            id="swastarkencl-config-form"
            class="defaultForm form-horizontal"
            action="{$swastarkencl_form_action|escape:'html':'UTF-8'}"
            method="post"
            enctype="multipart/form-data"
            >
            <input type="hidden" name="SWASTARKENCL_CREATE_SETUP" value="1" />
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
                        </div>
                    </div>

                    
                    <div class="tab-pane active" id="swastarkencl-settings" role="tabpanel">
                        <div class="form-wrapper">
                            <h1 class="text-center">{l s='Starken API Token' mod='swastarkencl'}</h1>

                            <div class="form-group">
                                <div class="col-lg-6 col-lg-offset-3">
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="icon icon-key"></i>
                                        </span>
                                        <input
                                            type="text"
                                            name="SWASTARKENCL_USER_TOKEN"
                                            id="SWASTARKENCL_USER_TOKEN" />
                                    </div>
                                    <br>
                                    <p class="help-block text-center" style="color: orange;">
                                        {l s='Enter the User token to authenticate in Starken Service' mod='swastarkencl'}
                                    </p>
                                </div>
                            </div>
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
                </div>
            </div>
        </form>
    </div>
</div>
