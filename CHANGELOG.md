# Changelog

All notable changes to E-Invoice RO are documented here. Versions follow Semantic Versioning where fiscal-output changes require at least a minor release after the 2.0.0 architecture baseline.

## 2.0.2 - Unreleased

### Changed

- rename the public product from E-Invoice Pro to E-Invoice RO while preserving the technical `einvoice_pro` identifier and every upgrade contract;
- replace the legacy PHP XML view with a canonical document builder and DOM UBL serializer;
- replace floating-point totals with deterministic string-decimal arithmetic and reconciliation;
- omit `TaxCurrencyCode` when it matches the invoice currency and require accounting-currency VAT when it differs;
- read the buyer country from the invoice billing snapshot and strictly validate dates, countries, Romanian subdivisions, units, currencies, identifiers, and optional blocks;
- allocate before-tax discounts across VAT profiles and represent the Perfex adjustment as payable rounding;
- add explicit VAT profiles for standard, exempt, reverse-charge, and non-VAT seller fixtures;
- apply the unidentified Romanian B2C identifier only from January 15, 2026 and only for an explicitly selected buyer type;
- validate all module settings server-side through a dedicated administrator POST endpoint;
- add a configurable reviewed default unit for invoice items that have no unit.

### Security

- reject binary floating-point source values, unsupported stacked taxes, ambiguous zero-tax mappings, after-tax discounts, invalid IBANs, unknown units, and unreconciled totals;
- stop using SMTP configuration as a fiscal endpoint and omit empty optional XML elements;
- return localized fail-closed generation errors with stable rule IDs and privacy-safe logs.

### Tests and release engineering

- add synthetic canonical fiscal fixtures, a mocked Perfex adapter fixture, settings validation tests, and validation-manifest checks;
- pin OASIS UBL 2.1, EN 16931 UBL 1.3.16, and SaxonJ-HE 12.10 release-test metadata and checksums;
- validate representative generated fixtures with the normative OASIS Invoice XSD and official CEN/TC 434 EN 16931 UBL 1.3.16 Schematron;
- add migration 202, which preserves every earlier setting and creates only the missing default-unit option;
- include libraries and the validation manifest in the reproducible package.

## 2.0.1 - 2026-08-18

### Fixed

- load `helpers/einvoice_pro_helper.php` through Perfex's module-scoped `einvoice_pro/einvoice_pro` path in the bootstrap and controller;
- add the required empty `201_version_201.php` migration so existing 2.0.0 installations can advance through the native module upgrade flow;
- retain migration 200 so direct upgrades from 1.4.3 remain supported.

## 2.0.0 - Superseded during deployment validation

### Security

- enforce invoice-level `view` and conservative `view_own` authorization in both the preview action and download endpoint;
- accept only positive integer invoice IDs and return the same not-found response for missing and inaccessible invoices;
- require administrator, AJAX, POST, CSRF, action/index allowlists, valid UTF-8, and bounded note values for note mutations;
- remove database-backed `innerHTML` usage from note management;
- send JSON status/content types and no-store, nosniff XML download headers;
- normalize XML download filenames against path and header injection.

### Upgrade

- move activation to Perfex's documented `register_activation_hook` API;
- add the mandatory sequential `200_version_200.php` migration;
- preserve all option keys and values released in 1.4.3;
- declare Perfex CRM 3.4.1 as the security baseline.

### Maintenance

- move settings behavior to separate JavaScript and CSS assets;
- remove the global function declared by the settings view;
- add English and Romanian messages for every note-management outcome;
- retain custom note text exactly instead of replacing it with a preset translation;
- stop adding the downloading staff member's identity to generated XML;
- add architecture, development, modernization, upgrade, and verification documentation.

### Known limitations

- the legacy XML mapper and PHP template remain pending replacement by the canonical decimal model, DOM serializer, and pinned offline validator;
- licensed Perfex integration and official MF/ANAF validation have not yet been executed for this unreleased build.
