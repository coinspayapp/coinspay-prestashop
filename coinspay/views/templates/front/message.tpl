{**
 * A message page of the module in the shop layout, PrestaShop 1.7 and newer.
 *}
{extends file='page.tpl'}

{block name='page_title'}
  {$coinspay_page.heading|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
  <div class="coinspay-message">
    <p class="alert alert-{if $coinspay_page.tone == 'success'}success{else}warning{/if}">{$coinspay_page.message|escape:'html':'UTF-8'}</p>
    <p class="coinspay-buttons">
      {foreach from=$coinspay_page.buttons item=button}
        <a class="btn {if $button.primary}btn-primary{else}btn-secondary{/if}" href="{$button.url|escape:'html':'UTF-8'}">{$button.label|escape:'html':'UTF-8'}</a>
      {/foreach}
    </p>
  </div>
{/block}
