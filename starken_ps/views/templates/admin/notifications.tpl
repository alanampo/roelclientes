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

<aside id="swastarkencl-notifications">
    {if isset($swastarkencl_errors) && count($swastarkencl_errors) > 0}
        <article class="alert alert-danger" role="alert">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <ul>
                {foreach $swastarkencl_errors as $error}
                    <li>{$error|escape:'html':'UTF-8'}</li>
                {/foreach}
            </ul>
        </article>
    {/if}
</aside>
