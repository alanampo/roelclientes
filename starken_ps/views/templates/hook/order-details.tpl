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

<div class="box hidden-sm-down">
    <div class="row">
        <div class="col-md-12">
            <div class="panel">
                <div class="panel-heading">
                    <img src="{$module_dir|escape:'html':'UTF-8'}logo.png" width="32" height="32" /> 
                    {l s='Issue info' mod='swastarkencl'}
                </div>
                <div class="container">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="row">
                                <h2><strong>{l s='Freight order' mod='swastarkencl'}:</strong> {$swastarkencl_emision->orden_flete|escape:'html':'UTF-8'}</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <hr >
                
                {if $swastarkencl_tracking != null}
                    <h1>{l s='Tracking info' mod='swastarkencl'}</h1>
                    <div id="swastarkencl-tracking">
                        <div class="row">
                            <div class="col-sm-12">
                                <table style="table-layout: fixed; width: 100%" border="1">
                                    <thead>
                                        <tr>
                                            <th>{l s='Status' mod='swastarkencl'}</th>
                                            <th>{l s='Note' mod='swastarkencl'}</th>
                                            <th>{l s='Created At' mod='swastarkencl'}</th>
                                            <th>{l s='Updated At' mod='swastarkencl'}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach from=$swastarkencl_tracking->history item=tracking_history}
                                            <tr>
                                                <td>{$tracking_history->status|escape:'html':'UTF-8'}</td>
                                                <td>{$tracking_history->note|escape:'html':'UTF-8'}</td>
                                                <td>{$tracking_history->created_at|escape:'html':'UTF-8'}</td>
                                                <td>{$tracking_history->updated_at|escape:'html':'UTF-8'}</td>
                                            </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                          </div>
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
</div>