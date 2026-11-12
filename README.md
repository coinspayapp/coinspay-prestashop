# CoinsPay for PrestaShop

Accept cryptocurrency payments in your PrestaShop store. Buyers pay on the CoinsPay payment page, and the money goes on to your own wallets.

## Requirements

| | Minimum | Tested on |
|---|---|---|
| PrestaShop | 1.6.1 | 1.6.1.24, 1.7.8.11, 8.2.9, 9.0.3, 9.2.1 |
| PHP | 5.6 | 7.1, 7.4, 8.1, 8.4, 8.5 |

One archive, `coinspay.zip`, for every version. You also need a [CoinsPay](https://coinspay.app) account with a shop.

## Description

The module adds a "Cryptocurrency" payment method to checkout. When the buyer confirms the order, PrestaShop places it with the "Awaiting crypto payment" status and the buyer goes to the CoinsPay payment page, picks a coin and a network and pays. Once the payment is confirmed, the order moves to "Payment accepted" (or the status you chose) and the buyer comes back to the order confirmation.

Prices stay in your store currency. CoinsPay converts the total at the current rate and holds the coin amount for 30 minutes, so the rate does not change while the buyer opens their wallet.

### Coins and networks

More than 30 coins and tokens on TRON, Ethereum, BNB Smart Chain, Solana, Bitcoin and Litecoin, among them USDT, USDC, BTC, ETH, BNB, TRX, LTC and SOL. The full list is on [coinspay.app/coins](https://coinspay.app/coins).

### What the module takes care of

- Each invoice event maps to an order status of your choice, your own statuses included. The events cover a payment on its way, partial payment, payment (on time, late, with overpayment or marked paid by hand), expiry and cancellation. A private message with amounts, coins and transaction hashes goes to the order either way; the buyer does not see it.
- An invoice that expires unpaid cancels the order, and PrestaShop returns the stock. A late payment still reaches the order.
- When you cancel an order, the module cancels its open invoice, so the buyer can no longer pay it.
- If a payment no longer matches the order total, or arrives for an order you cancelled, the order gets the "CoinsPay: check the payment" status with a message for you to review.
- A buyer who left the payment page can pay later from their account: the order page shows a "Go to payment" button while the invoice is open. A buyer who comes back without paying can put the items back in the cart.
- Webhooks are signed and checked. If they do not arrive, the module asks CoinsPay about open invoices itself.
- Multistore: each shop has its own settings. Shops may share one CoinsPay shop or use one each; a shop added later copies the settings of the shop it was made from.
- The standard step checkout and the one page checkout of PrestaShop 9.2.
- The saved API key and webhook secret are never printed into the settings form.
- English and Russian.

### Why CoinsPay

Payments go straight to your wallets. Each network takes up to five payout addresses with percentage shares, for example 70% to you and 30% to a partner, and nothing is kept on the platform.

The fee is as low as 0.3% of each payout: the rate goes down as the monthly turnover of all your shops grows. There is no monthly or setup fee. For the first 10 days after your first payment, payouts up to $500 carry no fee. You can let the buyer pay the CoinsPay fee and the network fee on top of the order total. Details are on the [pricing page](https://coinspay.app/pricing).

## Installation

1. Download `coinspay.zip` from the [releases](https://github.com/coinspayapp/coinspay-prestashop/releases/latest) of this repository.
2. Upload it in the back office:
   - PrestaShop 1.7 to 9: Modules → Module Manager → "Upload a module";
   - PrestaShop 1.6: Modules and Services → "Add a new module".
3. Press "Configure" next to CoinsPay.

PrestaShop warns that the module "could not be verified by PrestaShop": it says so of every module that does not come from the Addons marketplace. Confirm the installation.

The archive includes the installation guide in [English](docs/install-en.md) and [Russian](docs/install-ru.md).

## Configuration

1. In the CoinsPay dashboard open your shop:
   - under "API keys" create a key with "Invoices only" access and paste it into "API key". The module needs nothing more, and such a key cannot withdraw money;
   - leave "API address" empty: the module then uses `https://coinspay.app/api/v1`;
   - under "Webhook" enter the address the module shows, save, and copy the `whsec_…` secret into "Webhook secret".
2. Press "Check connection", then "Send test event" in the dashboard. The module shows when the event arrived.
3. On the "Order statuses" tab check the status of each invoice event. "Do not change the status" leaves the order as it is.
4. Save.

The currencies, countries and customer groups that see the method are set in Payment → Preferences, as for any payment module.

With multistore, pick one shop at the top of the back office before you open the settings: they are kept for each shop. Shops may share one CoinsPay shop: its webhook then comes to one of them, which passes the other shops' events on. A shop added later with "Import data" from another shop gets that shop's settings.

## Test mode

With a test key (`cpk_test_…`) invoices are paid with a button on the payment page and no coins move. By default the method is then shown to the test buyer only: open the store with "Open the shop as the test buyer" from the settings (the link works for a day in that browser). A test payment completes only orders of the test buyer.

## When webhooks do not arrive

The module polls CoinsPay for open invoices:

- from store pages, at most every 5 minutes;
- whenever the "Address for cron" from the settings is opened. Add it to your hosting cron every 5 minutes if the store has few visitors.

In maintenance mode PrestaShop refuses webhooks, and polling catches up afterwards.

## Good to know

- The order is placed before the payment, as with bank wire. PrestaShop sends the order confirmation e-mail then and reserves the stock; the "Awaiting crypto payment" status sends no e-mail of its own.
- The webhook address must be the store's own address, as the settings show it. PrestaShop redirects any other host name to it, and webhooks do not follow redirects.
- With "Enable back-orders status" on, an order of an out-of-stock product gets the back-order status right after it is placed. Choose the status for "Paid" accordingly.
- The invoice is made in the order currency when CoinsPay accepts it, otherwise in the default currency of the store.

## Development

```
coinspay/                 the module: the root folder of the archive
  coinspay.php            the PaymentModule class: install, hooks, the settings screen
  controllers/front/      redirect (the payment button), return, webhook, cron, test buyer link
  core/                   the core shared with CoinsPay for OpenCart, in PHP 5.3 syntax
    Decision.php          what an invoice state means for the order
    Settings.php          settings and the invoice event to order status mapping
    Sync.php              lock, order, decision, order status and message
    Repo.php              the coinspay_invoice and coinspay_kv tables
    prestashop/           the PrestaShop layer: store, order states, front, settings screen
    lang/                 all module texts
  views/                  templates, the settings script, images
bin/build.php             builds dist/coinspay.zip
tests/                    unit tests of the core
```

Checks and the build run in Docker:

```bash
P=$(pwd)
docker run --rm -v "$P:/app" -w /app composer:2 install
docker run --rm -v "$P:/app" -w /app php:7.4-cli vendor/bin/phpunit
docker run --rm -v "$P:/app" -w /app php:8.5-cli vendor/bin/phpunit
docker run --rm -v "$P:/app" -w /app php:8.5-cli vendor/bin/phpcs -q
docker run --rm -v "$P:/app" -w /app --entrypoint php prestashop/prestashop:9.2.1-6.1-classic-8.5-apache bin/build.php
```

`phpcs` checks PHP 5.6 compatibility of the whole module. `bin/build.php` needs PHP with the zip extension.

## Changelog

### 0.1.0

- First release.

## License

Academic Free License 3.0 (AFL-3.0). The full text is in [LICENSE](LICENSE).
