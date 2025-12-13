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
 * @copyright 2021
 * @license   See above
 *}

<div class="row">
    {if isset($swastarkencl_issue_problem) && $swastarkencl_issue_problem}
        <div class="col-sm-12">
            <p class="alert alert-danger">
                {l s='Starken issue could not be generated.' mod='swastarkencl'} 
                <a href="{$swastarkencl_module_config_link|escape:'html':'UTF-8'}">
                    {l s='Click here to see Starken Module Logs' mod='swastarkencl'}
                </a>
            </p>
        </div>
    {elseif isset($swastarkencl_emision)}
        <div class="col-md-12">
            <div class="panel card">
                <div class="panel-heading card-header">
                    <img src="{$module_dir|escape:'html':'UTF-8'}logo.png" width="32" height="32" /> 
                    {l s='Issue' mod='swastarkencl'}
                </div>

                <div class="panel-body card-body">
                    <img src="{$swastarkencl_ps_logo|escape:'html':'UTF-8'}" width="350" height="99">
                    <br />

                    {if !empty($swastarkencl_emision->etiqueta)}
                    <h1 style="text-transform: uppercase;">
                        {l s='Print your tag' mod='swastarkencl'} 
                        <a href="{$swastarkencl_emision->etiqueta|escape:'html':'UTF-8'}" target="_blank">
                            {l s='here' mod='swastarkencl'}
                        </a>
                    </h1>
                    {/if}

                    <h2 style="font-size: 20pt;  font-weight: bolder;text-transform: uppercase;">{l s='Shipping info' mod='swastarkencl'} </h2>

                    <h4 style="text-transform: uppercase;" title="{l s='Issue ID' mod='swastarkencl'}: {$swastarkencl_emision->id_emision|escape:'html':'UTF-8'}">
                        {l s='Freight order' mod='swastarkencl'} 
                        <strong>{$swastarkencl_emision->orden_flete|escape:'html':'UTF-8'}</strong>
                        <button id="send_mail" class="btn btn-primary btn-sm" style="position: relative; left:30px;">Renviar Email</button>
                        <small id="sended_mail" style="position: relative; left:30px; display: none;">(Renviado)</small>
                        <script type="text/javascript">
                            $('#send_mail').click( ev => {
                                $.ajax({
                                    url: "{$swastarkencl_email_send}",
                                    success: function(response){
                                        if( response  == 1){
                                            $('#send_mail').hide()
                                            $('#sended_mail').show()
                                        }
                                    }
                                })
                            })
                        </script>
                    </h4>

                    <div class="row">
                        <div class="col-sm-6">
                            <div class="row">
                                <div class="col-sm-6">
                                    <ul style="list-style: none; padding: 0; margin: 0">
                                        <li>
                                            <strong>{l s='Origin' mod='swastarkencl'}</strong>
                                            <br />
                                            {strtoupper($swastarkencl_tracking->origin)|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Destination' mod='swastarkencl'}</strong>
                                            <br />
                                            {strtoupper($swastarkencl_tracking->destination)|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Issuer RUT' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->issuer_rut|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Issuer name' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->issuer_name|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Issuer email' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->issuer_email|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Issuer phone' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->issuer_phone|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Issuer mobile' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->issuer_mobile|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Receiver RUT' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->receiver_rut|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Receiver name' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->receiver_name|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Receiver email' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->receiver_email|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Receiver phone' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->receiver_phone|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Receiver mobile' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->receiver_mobile|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Receiver address' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_tracking->receiver_address|escape:'html':'UTF-8'}
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-sm-6">
                                    <ul style="list-style: none; padding: 0; margin: 0">
                                        <li title="{$swastarkencl_emision->tipo_servicio['descripcion']|escape:'html':'UTF-8'}">
                                            <strong>{l s='Service type' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_emision->tipo_servicio['nombre']|escape:'html':'UTF-8'}
                                        </li>

                                        <li title="{$swastarkencl_emision->tipo_pago['descripcion']|escape:'html':'UTF-8'}">
                                            <strong>{l s='Payment Type' mod='swastarkencl'}</strong>
                                            <br />
                                            <span
                                                {if strtolower($swastarkencl_emision->tipo_pago['nombre']) == 'por pagar'}
                                                style="color:red"
                                                {/if}>
                                                {$swastarkencl_emision->tipo_pago['nombre']|escape:'html':'UTF-8'}
                                            </span>
                                        </li>

                                        <li>
                                            <strong>{l s='Checking account' mod='swastarkencl'}</strong>
                                            <br/>
                                            {$swastarkencl_emision->cuenta_corriente|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Center cost' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_emision->centro_costo|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Origin Agency' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_emision->codigo_agencia_origen|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Origin Agency Address' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_emision->direccion_agencia_origen|escape:'html':'UTF-8'}
                                        </li>

                                        {if $swastarkencl_emision->codigo_agencia_destino != 0}
                                        <li>
                                            {l s='Destination Agency' mod='swastarkencl'}: {$swastarkencl_emision->codigo_agencia_destino|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Destination Agency Address' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_emision->direccion_agencia_destino|escape:'html':'UTF-8'}
                                        </li>
                                        {/if}

                                        <li>
                                            <strong>{l s='Shipping total cost' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_total_shipping|escape:'html':'UTF-8'}
                                        </li>

                                        <li>
                                            <strong>{l s='Declared Value' mod='swastarkencl'}</strong>
                                            <br />
                                            {$swastarkencl_emision->valor_declarado|escape:'html':'UTF-8'}
                                        </li>

                                        {if $swastarkencl_tracking != null}
                                            <li>
                                                <strong>{l s='Status' mod='swastarkencl'}</strong>
                                                <br />
                                                {$swastarkencl_tracking->status|escape:'html':'UTF-8'}
                                            </li>

                                            <li>
                                                <strong>{l s='Commitment date' mod='swastarkencl'}</strong>
                                                <br />
                                                {$swastarkencl_tracking->commitmen_date|escape:'html':'UTF-8'}
                                            </li>

                                            <li>
                                                <strong>{l s='Created at' mod='swastarkencl'}</strong>
                                                <br />
                                                {$swastarkencl_tracking->created_at|escape:'html':'UTF-8'}
                                            </li>

                                            <li>
                                                <strong>{l s='Updated at' mod='swastarkencl'}</strong>
                                                <br />
                                                {$swastarkencl_tracking->updated_at|escape:'html':'UTF-8'}
                                            </li>
                                        {/if}
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            {if !empty($swastarkencl_emision->observacion) && strtolower($swastarkencl_emision->status) == 'error'}
                            <p class="alert alert-danger">
                                {l s='Observation:' mod='swastarkencl'} <br />
                                <strong>{$swastarkencl_emision->observacion|escape:'html':'UTF-8'}</strong>
                            </p>
                            {/if}
                            {if $swastarkencl_tracking != null && isset($swastarkencl_tracking->history)}
                                <strong style="text-transform: uppercase;">{l s='History' mod='swastarkencl'}</strong>
                                <table style="table-layout: fixed; width: 90%" class="table">
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
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</div>
