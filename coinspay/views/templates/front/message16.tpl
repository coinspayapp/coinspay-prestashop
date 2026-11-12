{**
 * A message page of the module in the shop layout, PrestaShop 1.6.
 *}
<h1 class="page-heading">{$coinspay_page.heading|escape:'html':'UTF-8'}</h1>
<div class="coinspay-message">
  <p class="alert alert-{if $coinspay_page.tone == 'success'}success{else}warning{/if}">{$coinspay_page.message|escape:'html':'UTF-8'}</p>
  <p class="cart_navigation clearfix">
    {foreach from=$coinspay_page.buttons item=button}
      <a class="button {if $button.primary}button-medium btn btn-default{else}button-exclusive btn btn-default{/if}" href="{$button.url|escape:'html':'UTF-8'}"><span>{$button.label|escape:'html':'UTF-8'}</span></a>
    {/foreach}
  </p>
</div>
