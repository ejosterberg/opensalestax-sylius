# Contributing to opensalestax-sylius

Thanks for your interest. This bundle is part of the broader
[OpenSalesTax](https://github.com/ejosterberg) portfolio, and
PRs from anyone are welcome.

## Developer Certificate of Origin (DCO)

Every commit must be signed off:

```bash
git commit -s -m "Your message"
```

CI enforces this on every PR. See [https://developercertificate.org](https://developercertificate.org)
for the full text. PRs without DCO sign-off cannot be merged.

## License

This bundle is **dual-licensed** under your choice of:

- [Apache License 2.0](LICENSE-APACHE.txt), OR
- [GNU GPL version 2 or later](LICENSE-GPL.txt)

By contributing you agree your contribution is licensed under
**both** options — the recipient picks. This keeps the
licensing footprint consistent with the WooCommerce sibling
(same Apache OR GPL dual) and with the broader portfolio.

Every source file must carry an SPDX header:

```php
// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later
```

## Dev install

```bash
git clone https://github.com/ejosterberg/opensalestax-sylius.git
cd opensalestax-sylius
composer install
```

The bundle declares `ejosterberg/opensalestax ^0.1` as a runtime
dependency and `sylius/sylius ^1.13` as a `suggest`. For local
development you can pull a real Sylius install separately and
point its `composer.json` at this directory via a path repo:

```bash
# In your Sylius app:
composer config repositories.opensalestax-sylius path '../opensalestax-sylius'
composer require ejosterberg/opensalestax-sylius:dev-main
```

## Running the quality gate

```bash
composer test     # phpunit
composer stan     # phpstan max
composer psalm    # psalm
composer lint     # php-cs-fixer dry-run
composer lint-fix # php-cs-fixer apply
```

CI runs all of the above on every push (matrix: PHP 8.2 / 8.3 / 8.4).

## Reporting issues

GitHub issues. Include:

- Sylius version (`composer show sylius/sylius`)
- The OpenSalesTax engine version (`curl http://your-engine/v1/health`)
- Your PHP version
- A minimal reproducer (cart contents + shipping address ZIP +
  bundle config snippet)

For security issues, see [`SECURITY.md`](SECURITY.md) — please
do not file public issues for security topics.
