# E-Invoice RO for Perfex CRM

E-Invoice RO is a Romanian UBL invoice generation layer for Perfex CRM. It adds strict fiscal mapping, deterministic reconciliation, a controlled XML download action, Romanian company and payment settings, reusable plain-text notes, English and Romanian administration text, and a native Perfex upgrade path.

The public product name changed from **E-Invoice Pro** to **E-Invoice RO** in version 2.0.2 so its Romanian scope is explicit. The technical module identifier remains `einvoice_pro`; its directory, initialization file, routes, option keys, classes, hooks, migration history, and package root are intentionally unchanged for upgrade compatibility.

## Version 2.0.3 status

Version 2.0.3 is the current development release. It fixes seller-country resolution for Perfex 3.4 by reading the explicit `invoice_company_country_code` first, while keeping compatibility with the legacy country identifier. It does not change XML semantics, stored settings, or the technical module identity introduced through 2.0.2.

Synthetic fixtures for standard VAT, two VAT rates, exempt, reverse-charge, non-VAT seller, document discount, adjustment, foreign invoice currency with RON accounting VAT, and unidentified Romanian B2C pass the internal suite. All representative fixtures also passed the normative OASIS UBL 2.1 Invoice XSD and the official CEN/TC 434 EN 16931 UBL 1.3.16 Schematron during the August 18, 2026 development verification.

The module still does not bundle or execute the complete UBL XSD + EN 16931 + RO_CIUS validation stack at download time. The attached production example was reported as accepted by the ANAF validator, but that result does not certify every mapping. Therefore:

- generated XML must not be described as ANAF-validated or guaranteed compliant;
- this build is not approved for unattended fiscal production use;
- every XML must still be independently validated before submission;
- the module must not be used as the only evidence of legal or fiscal compliance.

The module does not upload invoices to ANAF, manage SPV/OAuth credentials or certificates, sign documents, poll submission status, retrieve ANAF responses, or generate credit notes.

## Implemented in 2.0.2

### Security and access

- invoice-level authorization is checked in both the preview action and download endpoint;
- staff require Perfex invoice `view` permission or conservative `view_own` ownership through the invoice creator or assigned sales agent;
- invoice route values accept positive integers only;
- missing and inaccessible invoice IDs return the same not-found behavior;
- custom-note mutations require administrator access, AJAX, POST, Perfex CSRF validation, action/index allowlists, valid UTF-8, and bounded input;
- custom note text is inserted into the DOM with `textContent`, never interpreted as HTML;
- XML downloads use normalized filenames plus `no-store`, `no-cache`, and `nosniff` headers;
- JSON endpoints return explicit HTTP status codes and `application/json` content.

### Upgrade and maintenance

- the module uses Perfex's documented `register_activation_hook` API;
- clean activation is idempotent and does not replace existing option values;
- migrations `200_version_200.php` through `203_version_203.php` support sequential native upgrades without rewriting released settings;
- helper loading uses the documented `einvoice_pro/einvoice_pro` module path from both bootstrap hooks and the controller;
- malformed legacy custom-note JSON is preserved and marked for manual review instead of being silently replaced;
- module functions and constants use the `einvoice_pro_` and `EINVOICE_PRO_` prefixes;
- settings JavaScript and CSS are separate assets;
- the downloading staff member no longer changes generated XML content;
- custom selected notes reach the XML as their stored plain text, while released presets remain translatable;
- the package builder uses an explicit runtime allowlist and reproducible archive timestamps.

### Fiscal generation

- all monetary calculations use validated decimal strings; source floats are rejected;
- UBL XML is generated with DOM text nodes and attributes, not PHP templates or string concatenation;
- `TaxCurrencyCode` is omitted for RON invoices and is accepted only when it differs from the document currency and a RON VAT total is supplied;
- multiple VAT rates on different lines produce separate reconciled VAT breakdowns;
- stacked taxes on one line are rejected because Perfex does not expose enough semantics to map them safely;
- standard VAT, exempt (`E`), reverse charge (`AE`), and non-VAT seller (`O`) are distinct canonical profiles;
- before-tax document discounts are allocated across VAT profiles with deterministic decimal rounding;
- Perfex adjustment is represented as payable rounding and reconciled with the invoice total;
- invoice dates use strict ISO parsing and a missing due date never becomes `1970-01-01`;
- Romanian county names/codes and common Perfex units are mapped through reviewed allowlists;
- the buyer country is read from the invoice billing snapshot, not the current customer address;
- the B2C 13-zero identifier is applied only to an explicitly unidentified Romanian individual for invoices dated January 15, 2026 or later;
- empty optional payment, contact, subdivision, allowance, and monetary elements are omitted;
- server-side settings validation now precedes every option write.

## Supported Perfex adapter scope and limitations

The default Perfex adapter supports issued UBL invoices with exact decimal item values, one VAT treatment per line, recognized units, and a buyer fiscal identifier. Country-prefixed VAT identifiers are emitted separately; an unprefixed fiscal identifier remains a legal identifier and is not falsely declared as VAT. The adapter intentionally blocks ambiguous source data instead of guessing.

The following cases require explicit source enrichment through `einvoice_pro_document_source`, an exact `einvoice_pro_line_tax_profile` mapping, or a future configuration UI and are blocked by default:

- a VAT-registered seller line with zero tax, because Perfex tax rate alone cannot distinguish `E` from `AE`;
- a buyer without any fiscal identifier, because Perfex does not identify legal entities and B2C individuals unambiguously;
- foreign-currency invoices without a trusted invoice-specific RON VAT total;
- stacked taxes on a single line, after-tax discounts, line-level allowances, prepayments, credit notes, and corrective invoice references;
- unknown countries, currencies, Romanian subdivisions, or units outside the reviewed 2.0.2 subsets.

Perfex filters receive the canonical value as their first argument and one additional-parameters array as their second argument. Register extension callbacks with `accepted_args=2`; the document filter receives `invoice`, while the line-tax filter receives `taxes`, `item`, and `invoice`. Filter output is validated again and cannot bypass reconciliation.

Native Perfex 3.4+ e-invoice coexistence and actual migration execution still require licensed staging verification.

These limitations are intentionally documented rather than hidden behind a generic “valid XML” claim.

## Platform requirements

Declared runtime target:

- Perfex CRM 3.4.1 or later within an explicitly tested compatibility range;
- PHP 8.1 minimum because Perfex 3.4 requires it;
- patched PHP 8.4 or 8.5 recommended for production in 2026;
- PHP `mbstring`, `libxml`, `dom`, `xml`, `xmlreader`, and `xmlwriter` extensions;
- HTTPS and Perfex CSRF protection enabled.

Local development checks currently pass on PHP 8.4.24 and PHP 8.5.9. This does not replace installation tests inside Perfex. No stable compatibility claim is made for a Perfex version until the packaged module passes its licensed staging matrix.

Perfex CRM 3.4 introduced native e-invoice exports. Confirm action placement, hook payloads, permissions, and output ownership in staging before enabling this module for staff.

## Clean installation

1. Back up the Perfex files and database.
2. Build or obtain the E-Invoice RO archive and inspect its checksum and contents.
3. Install the archive from **Setup → Modules**, or place the `einvoice_pro` directory under the Perfex `modules` directory.
4. Confirm that both the directory and initialization file are named `einvoice_pro` and `einvoice_pro.php`.
5. Activate **E-Invoice RO**.
6. Open **Setup → Settings → Finance → E-Invoice RO**.
7. Review every value; do not rely on defaults as proof of correct fiscal identity.
8. Test administrator access, staff permissions, direct URL denial, note management, and XML download using synthetic invoices.

Activation creates only missing module options. Retrying activation does not overwrite a value from an earlier or partially completed installation.

## Upgrade from 1.4.3 or 2.0.x

Version 1.4.3 is the supported upgrade floor for 2.0.3.

1. Back up the complete module directory and Perfex database together.
2. Record the current E-Invoice RO registration, bank, language, selected-note, and custom-note values.
3. Replace the existing files with the 2.0.3 package without deleting database options.
4. Open **Setup → Modules** and confirm that Perfex detects version 2.0.3.
5. Run the normal Perfex **Upgrade Database** action.
6. Perfex runs the missing migrations in order. Migration 202 adds `einvoice_pro_default_unit_code=H87` only when the option does not exist; migration 203 is data-neutral. All earlier values remain unchanged.
7. Reopen the settings page and compare every recorded value.
8. Verify authorized and unauthorized invoice access through the actual download endpoint.
9. Validate a representative set of synthetic XML documents before considering the upgraded installation operational.

The repository includes a synthetic 1.4.3 option fixture and a local preservation simulation. The migration has not yet been executed against a licensed Perfex 3.4.1 database in this workspace. Perfex module migrations are forward-only; recovery requires restoring the matching files and database backup. Installing older files over a newer database is not a supported downgrade.

## Configuration

The current settings panel contains:

| Setting | Stored option | Notes |
|---|---|---|
| XML note language | `einvoice_pro_xml_language` | English or Romanian preset text |
| Default item unit | `einvoice_pro_default_unit_code` | Reviewed UN/ECE code used only when a Perfex item has no unit |
| Trade Register number | `einvoice_pro_registration_number` | Separate from the Perfex VAT option |
| Legal-capital value | `einvoice_pro_company_legal_form` | Legacy field retained during modernization |
| Payment IBAN | `einvoice_pro_payment_iban` | Requires independent fiscal/business validation |
| Bank name | `einvoice_pro_payment_bank_name` | Legacy value preserved for compatibility; not mapped to UBL account name |
| Note slots 1–3 | `einvoice_pro_note_1` … `_3` | Empty, preset, or administrator-defined text |
| Custom note choices | `einvoice_pro_custom_notes_1` … `_3` | JSON arrays retained for 1.4.3 compatibility |

Custom notes are limited to 500 UTF-8 characters and 50 values per slot. New values are trimmed, stored as text, displayed as text, and escaped for their final output context. Invalid legacy JSON or oversized legacy values are not overwritten from the note manager; the settings page shows a review warning.

Only administrators may manage module settings and reusable note values. Invoice downloads follow the concrete invoice permission check independently of whether the button is visible.

## Development checks

Run from the module repository root:

```bash
php tests/smoke.php
php tests/fiscal.php
php tests/perfex-adapter.php
php tests/validation-manifest.php
node --check assets/js/settings.js
node tests/frontend-security.mjs
git diff --check
php scripts/package.php
```

The smoke suite covers route IDs, conservative invoice permissions, settings validation, filename normalization, UTF-8 and malformed custom-note JSON, language-key parity, clean activation defaults, and preservation of the synthetic 1.4.3 option fixture through migrations 200/201/202. The fiscal and adapter suites cover canonical totals, VAT profiles, DOM serialization, source snapshot behavior, discounts, accounting currency, B2C rules, and fail-closed negative cases.

The frontend regression prevents database-backed `innerHTML`, manual JSON parsing, and inline settings JavaScript from being reintroduced. PHP lint must also run over every PHP file:

```bash
find . -type f -name '*.php' -not -path './vendor/*' -exec php -l {} \;
```

## Building the package

```bash
php scripts/package.php
```

The archive is written to `dist/einvoice_pro-<version>.zip`. The builder reads the version from the module header, places files under one `einvoice_pro/` root, fixes archive timestamps, and includes only:

- runtime PHP, JavaScript, CSS, language, migration, and view files;
- `README.md`, `CHANGELOG.md`, and `LICENSE`.

It excludes Git data, tests and fixtures, IDE files, local caches, logs, development instructions, architecture sources, and verification records. Build the final archive twice and compare SHA-256 values before release.

## Release blockers

A stable or compliance-labelled release additionally requires:

1. clean installation and exact 1.4.3 upgrade inside licensed Perfex 3.4.1;
2. the same integration suite on the latest Perfex version declared as supported;
3. real HTTP tests for permissions, CSRF, JSON/XML statuses, headers, and native export coexistence;
4. licensed Perfex adapter tests for every supported source shape and blocked case;
5. pinned local UBL XSD and RO_CIUS/RO_NAT artifacts plus an offline runtime validation pipeline;
6. expansion of reviewed country, currency, unit, tax, B2C, credit-note, and foreign-currency configuration where product requirements demand it;
7. positive and negative synthetic fixture validation;
8. current official MF/ANAF validator evidence for the exact package;
9. browser, accessibility, responsive-layout, CI, static-analysis, and packaging checks.

No critical release blocker may be converted into a warning merely to ship the module.

## Repository documentation

The development repository contains additional documents that are deliberately excluded from the installable archive:

- `AGENTS.md` — mandatory development and security rules;
- `ARCHITECTURE.md` — current audit, target architecture, domain boundaries, and debt register;
- `MODERNIZATION_PLAN.md` — phased implementation and release plan;
- `VERIFICATION.md` — dated evidence for the current working tree.

When working from a distribution archive, this README and the changelog are the self-contained operational documentation.

## License

The project is distributed under the terms in `LICENSE`.
