{**
 * The CoinsPay settings screen (Modules → CoinsPay → Configure). Bootstrap 3 markup of the
 * PrestaShop back office, the same from 1.6 to 9.x.
 *}
{assign var=t value=$coinspay.texts}
{if $coinspay.pick_shop}
<div class="alert alert-info">{$coinspay.pick_shop|escape:'html':'UTF-8'}</div>
{else}
<div id="coinspay-settings" class="coinspay-settings"
     data-check-url="{$coinspay.check_url|escape:'html':'UTF-8'}" data-refresh-url="{$coinspay.refresh_url|escape:'html':'UTF-8'}"
     data-checking="{$t.checking|escape:'html':'UTF-8'}" data-check-failed="{$t.check_failed|escape:'html':'UTF-8'}"
     data-copied="{$t.copied|escape:'html':'UTF-8'}" data-refresh-failed="{$t.refresh_failed|escape:'html':'UTF-8'}">
  {if isset($coinspay.errors.warning)}
  <div class="alert alert-danger">{$coinspay.errors.warning|escape:'html':'UTF-8'}</div>
  {/if}
  {if $coinspay.success}
  <div class="alert alert-success">{$coinspay.success|escape:'html':'UTF-8'}</div>
  {/if}
  {foreach from=$coinspay.warnings item=warning}
  <div class="alert alert-warning">{$warning|escape:'html':'UTF-8'}</div>
  {/foreach}
  {if $coinspay.test_mode}
  <div class="alert alert-info">{$t.test_mode_notice|escape:'html':'UTF-8'}</div>
  {/if}

  <form action="{$coinspay.action|escape:'html':'UTF-8'}" method="post" id="coinspay-form" class="form-horizontal" autocomplete="off">
    <div class="panel">
      <div class="panel-heading">
        <i class="icon-cogs"></i> {$t.edit|escape:'html':'UTF-8'}{if $coinspay.shop_name} — {$t.shop|replace:'%s':$coinspay.shop_name|escape:'html':'UTF-8'}{/if}
      </div>
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item active"><a class="nav-link active" href="#coinspay-tab-general" data-toggle="tab" role="tab">{$t.tab_general|escape:'html':'UTF-8'}</a></li>
        <li class="nav-item"><a class="nav-link" href="#coinspay-tab-statuses" data-toggle="tab" role="tab">{$t.tab_statuses|escape:'html':'UTF-8'}</a></li>
        <li class="nav-item"><a class="nav-link" href="#coinspay-tab-invoices" data-toggle="tab" role="tab">{$t.tab_invoices|escape:'html':'UTF-8'}</a></li>
      </ul>
      <div class="tab-content" style="padding-top: 15px;">
        <div class="tab-pane active" id="coinspay-tab-general" role="tabpanel">
          <h4>{$t.connection|escape:'html':'UTF-8'}</h4>
          <p class="help-block">{$t.connection_help|escape:'html':'UTF-8'}</p>
          <div class="form-group{if isset($coinspay.errors.api_url)} has-error{/if}">
            <label class="control-label col-lg-3" for="coinspay-api-url">{$t.api_url|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <input type="text" name="coinspay_api_url" value="{$coinspay.values.api_url|escape:'html':'UTF-8'}" id="coinspay-api-url" placeholder="https://coinspay.app/api/v1" class="form-control" />
              {if isset($coinspay.errors.api_url)}<p class="text-danger">{$coinspay.errors.api_url|escape:'html':'UTF-8'}</p>{/if}
              <p class="help-block">{$t.api_url_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>
          <div class="form-group{if isset($coinspay.errors.api_key)} has-error{/if}">
            <label class="control-label col-lg-3 required" for="coinspay-api-key">{$t.api_key|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <input type="password" name="coinspay_api_key" value="" placeholder="{if $coinspay.saved.api_key}{$t.saved_secret|escape:'html':'UTF-8'}{/if}" id="coinspay-api-key" class="form-control" autocomplete="new-password" />
              {if isset($coinspay.errors.api_key)}<p class="text-danger">{$coinspay.errors.api_key|escape:'html':'UTF-8'}</p>{/if}
              <p class="help-block">{$t.api_key_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-lg-3" for="coinspay-webhook-url">{$t.webhook_url|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <div class="input-group">
                <input type="text" value="{$coinspay.webhook_url|escape:'html':'UTF-8'}" id="coinspay-webhook-url" class="form-control" readonly="readonly" />
                <span class="input-group-btn"><button type="button" class="btn btn-default coinspay-copy" data-target="coinspay-webhook-url">{$t.copy|escape:'html':'UTF-8'}</button></span>
              </div>
              <p class="help-block" id="coinspay-webhook-last">{if $coinspay.webhook_last}{$coinspay.webhook_last|escape:'html':'UTF-8'}{else}{$t.webhook_help|escape:'html':'UTF-8'}{/if}</p>
            </div>
          </div>
          <div class="form-group{if isset($coinspay.errors.webhook_secret)} has-error{/if}">
            <label class="control-label col-lg-3 required" for="coinspay-webhook-secret">{$t.webhook_secret|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <input type="password" name="coinspay_webhook_secret" value="" placeholder="{if $coinspay.saved.webhook_secret}{$t.saved_secret|escape:'html':'UTF-8'}{/if}" id="coinspay-webhook-secret" class="form-control" autocomplete="new-password" />
              {if isset($coinspay.errors.webhook_secret)}<p class="text-danger">{$coinspay.errors.webhook_secret|escape:'html':'UTF-8'}</p>{/if}
              <p class="help-block">{$t.webhook_secret_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>
          <div class="form-group">
            <div class="col-lg-9 col-lg-offset-3">
              <button type="button" id="coinspay-check" class="btn btn-default">{$t.check|escape:'html':'UTF-8'}</button>
              <p class="help-block">{$t.check_help|escape:'html':'UTF-8'}</p>
              <div id="coinspay-check-result"></div>
            </div>
          </div>

          <h4>{$t.checkout|escape:'html':'UTF-8'}</h4>
          <p class="help-block">{$t.checkout_help|escape:'html':'UTF-8'}</p>
          <div class="form-group{if isset($coinspay.errors.total)} has-error{/if}">
            <label class="control-label col-lg-3" for="coinspay-total">{$t.total|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <input type="text" name="coinspay_total" value="{$coinspay.values.total|escape:'html':'UTF-8'}" id="coinspay-total" class="form-control fixed-width-md" />
              {if isset($coinspay.errors.total)}<p class="text-danger">{$coinspay.errors.total|escape:'html':'UTF-8'}</p>{/if}
              <p class="help-block">{$t.total_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>

          <h4>{$t.invoice|escape:'html':'UTF-8'}</h4>
          <div class="form-group{if isset($coinspay.errors.lifetime)} has-error{/if}">
            <label class="control-label col-lg-3" for="coinspay-lifetime">{$t.lifetime|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <input type="text" name="coinspay_lifetime" value="{$coinspay.values.lifetime|escape:'html':'UTF-8'}" id="coinspay-lifetime" class="form-control fixed-width-md" />
              {if isset($coinspay.errors.lifetime)}<p class="text-danger">{$coinspay.errors.lifetime|escape:'html':'UTF-8'}</p>{/if}
              <p class="help-block">{$t.lifetime_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>
          {foreach from=[['auto_return', $t.auto_return, $t.auto_return_help], ['stablecoins_only', $t.stablecoins, ''], ['buyer_pays_fee', $t.buyer_fee, $t.buyer_fee_help], ['buyer_pays_network', $t.buyer_network, '']] item=flag}
          <div class="form-group">
            <div class="col-lg-9 col-lg-offset-3">
              <div class="checkbox">
                <label><input type="checkbox" name="coinspay_{$flag[0]|escape:'html':'UTF-8'}" value="1"{if $coinspay.values[$flag[0]]} checked="checked"{/if} /> {$flag[1]|escape:'html':'UTF-8'}</label>
              </div>
              {if $flag[2]}<p class="help-block">{$flag[2]|escape:'html':'UTF-8'}</p>{/if}
            </div>
          </div>
          {/foreach}

          <h4>{$t.test|escape:'html':'UTF-8'}</h4>
          <div class="form-group">
            <div class="col-lg-9 col-lg-offset-3">
              <div class="checkbox">
                <label><input type="checkbox" name="coinspay_test_buyer_only" value="1"{if $coinspay.values.test_buyer_only} checked="checked"{/if} /> {$t.test_buyer_only|escape:'html':'UTF-8'}</label>
              </div>
              <p class="help-block">{$t.test_buyer_help|escape:'html':'UTF-8'}</p>
              {if $coinspay.test_url}<a href="{$coinspay.test_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener" id="coinspay-test-link" class="btn btn-default">{$t.test_link|escape:'html':'UTF-8'}</a>{/if}
            </div>
          </div>

          <h4>{$t.advanced|escape:'html':'UTF-8'}</h4>
          <div class="form-group">
            <div class="col-lg-9 col-lg-offset-3">
              <div class="checkbox">
                <label><input type="checkbox" name="coinspay_debug" value="1"{if $coinspay.values.debug} checked="checked"{/if} /> {$t.debug|escape:'html':'UTF-8'}</label>
              </div>
              <p class="help-block">{$t.debug_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>
          {if $coinspay.cron_url}
          <div class="form-group">
            <label class="control-label col-lg-3" for="coinspay-cron-url">{$t.cron|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <div class="input-group">
                <input type="text" value="{$coinspay.cron_url|escape:'html':'UTF-8'}" id="coinspay-cron-url" class="form-control" readonly="readonly" />
                <span class="input-group-btn"><button type="button" class="btn btn-default coinspay-copy" data-target="coinspay-cron-url">{$t.copy|escape:'html':'UTF-8'}</button></span>
              </div>
              <p class="help-block">{$t.cron_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>
          {/if}
        </div>

        <div class="tab-pane" id="coinspay-tab-statuses" role="tabpanel">
          <p class="help-block">{$t.statuses_help|escape:'html':'UTF-8'}</p>
          <table class="table">
            <thead>
              <tr><th>{$t.event|escape:'html':'UTF-8'}</th><th>{$t.order_status|escape:'html':'UTF-8'}</th></tr>
            </thead>
            <tbody>
              {foreach from=$coinspay.events item=event}
              <tr{if $event.error} class="danger"{/if}>
                <td><label for="coinspay-{$event.key|escape:'html':'UTF-8'}">{$event.label|escape:'html':'UTF-8'}</label></td>
                <td>
                  <select name="coinspay_status_{$event.key|escape:'html':'UTF-8'}" id="coinspay-{$event.key|escape:'html':'UTF-8'}" class="form-control coinspay-event">
                    <option value="">{$t.choose|escape:'html':'UTF-8'}</option>
                    {if $event.no_change}<option value="0"{if $event.value === '0'} selected="selected"{/if}>{$t.no_change|escape:'html':'UTF-8'}</option>{/if}
                    {if $event.same_as_paid}<option value="paid"{if $event.value === 'paid'} selected="selected"{/if}>{$t.same_as_paid|escape:'html':'UTF-8'}</option>{/if}
                    {foreach from=$coinspay.states item=state}
                    <option value="{$state.id|intval}"{if $event.value == $state.id && $event.value !== '' && $event.value !== '0' && $event.value !== 'paid'} selected="selected"{/if}>{$state.name|escape:'html':'UTF-8'}</option>
                    {/foreach}
                  </select>
                  {if $event.error}<p class="text-danger">{$event.error|escape:'html':'UTF-8'}</p>{/if}
                </td>
              </tr>
              {/foreach}
            </tbody>
          </table>
          <div class="form-group">
            <label class="control-label col-lg-3">{$t.cancel_statuses|escape:'html':'UTF-8'}</label>
            <div class="col-lg-9">
              <div class="well" style="max-height: 220px; overflow: auto;">
                {foreach from=$coinspay.states item=state}
                <div class="checkbox">
                  <label><input type="checkbox" name="coinspay_cancel_statuses[]" value="{$state.id|intval}"{if $state.cancel} checked="checked"{/if} /> {$state.name|escape:'html':'UTF-8'}</label>
                </div>
                {/foreach}
              </div>
              <p class="help-block">{$t.cancel_statuses_help|escape:'html':'UTF-8'}</p>
            </div>
          </div>
        </div>

        <div class="tab-pane" id="coinspay-tab-invoices" role="tabpanel">
          <p class="help-block">{$t.invoices_help|escape:'html':'UTF-8'}</p>
          {if $coinspay.invoices}
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>{$t.col_order|escape:'html':'UTF-8'}</th><th>{$t.col_invoice|escape:'html':'UTF-8'}</th><th>{$t.col_amount|escape:'html':'UTF-8'}</th>
                  <th>{$t.col_paid|escape:'html':'UTF-8'}</th><th>{$t.col_status|escape:'html':'UTF-8'}</th><th>{$t.col_created|escape:'html':'UTF-8'}</th><th></th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$coinspay.invoices item=invoice}
                <tr>
                  <td><a href="{$invoice.order_url|escape:'html':'UTF-8'}">#{$invoice.order_id|intval}</a></td>
                  <td><a href="{$invoice.pay_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$invoice.number|intval}</a>{if $invoice.test} <span class="label label-default">{$t.test_badge|escape:'html':'UTF-8'}</span>{/if}</td>
                  <td>{$invoice.amount|escape:'html':'UTF-8'}</td>
                  <td>{$invoice.paid|escape:'html':'UTF-8'}</td>
                  <td class="coinspay-invoice-status">{$invoice.status_text|escape:'html':'UTF-8'}</td>
                  <td>{$invoice.created|escape:'html':'UTF-8'}</td>
                  <td>
                    <button type="button" class="btn btn-default btn-xs coinspay-refresh" data-invoice="{$invoice.invoice_id|escape:'html':'UTF-8'}">{$t.refresh|escape:'html':'UTF-8'}</button>
                    <div class="coinspay-refresh-result small"></div>
                  </td>
                </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
          {else}
          <p>{$t.no_invoices|escape:'html':'UTF-8'}</p>
          {/if}
        </div>
      </div>
      <div class="panel-footer">
        <button type="submit" name="submitCoinspay" value="1" class="btn btn-default pull-right"><i class="process-icon-save"></i> {$t.save|escape:'html':'UTF-8'}</button>
        <span class="text-muted small">{$coinspay.version|escape:'html':'UTF-8'}</span>
      </div>
    </div>
  </form>
</div>
<script type="text/javascript" src="{$coinspay.script_url|escape:'html':'UTF-8'}"></script>
{/if}
