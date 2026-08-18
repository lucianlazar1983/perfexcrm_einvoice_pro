# E-Invoice Pro for Perfex CRM

E-Invoice Pro is a Perfex CRM module for generating Romanian UBL invoice XML from Perfex invoices. It provides an invoice download action, Romanian company and payment settings, reusable plain-text notes, English and Romanian administration text, and a native Perfex upgrade path.

## Version 2.0.1 status

Version 2.0.1 is currently an unreleased security and architecture baseline. It is a substantial revision of the original 1.4.3 module, but the fiscal engine is not yet the final target described by the project architecture. It supersedes the initial 2.0.0 package, which used an application-level helper path instead of Perfex's module-scoped helper path.

The module can generate XML through the retained legacy mapper. The current repository does not yet contain pinned UBL XSD, EN 16931, and RO_CIUS validation artifacts, a decimal reconciliation engine, or a reproducible official MF/ANAF validation report. Therefore:

- generated XML must not be described as ANAF-validated or guaranteed compliant;
- this build is not approved for unattended fiscal production use;
- every XML must be independently validated before submission;
- the module must not be used as the only evidence of legal or fiscal compliance.

The module does not upload invoices to ANAF, manage SPV/OAuth credentials or certificates, sign documents, poll submission status, retrieve ANAF responses, or generate credit notes.

## Implemented in 2.0.x

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
- migrations `200_version_200.php` and `201_version_201.php` support direct 1.4.3 → 2.0.1 and 2.0.0 → 2.0.1 transitions without rewriting released settings;
- helper loading uses the documented `einvoice_pro/einvoice_pro` module path from both bootstrap hooks and the controller;
- malformed legacy custom-note JSON is preserved and marked for manual review instead of being silently replaced;
- module functions and constants use the `einvoice_pro_` and `EINVOICE_PRO_` prefixes;
- settings JavaScript and CSS are separate assets;
- the downloading staff member no longer changes generated XML content;
- custom selected notes reach the XML as their stored plain text, while released presets remain translatable;
- the package builder uses an explicit runtime allowlist and reproducible archive timestamps.

## Known fiscal limitations

The remaining legacy mapper has the following release blockers:

- it uses floating-point calculations instead of an explicit decimal value model;
- discounts, adjustments, allowances, charges, prepayments, and rounding are not mapped completely;
- multiple-tax cases are not modeled safely;
- VAT category is not derived for all standard, zero, exempt, and outside-scope cases;
- free-form Perfex units are not mapped to a reviewed UN/ECE code list;
- accounting-currency VAT behavior is incomplete;
- missing dates, country/subdivision data, optional XML blocks, and buyer identity cases still need fail-closed validation;
- buyer snapshot behavior and native Perfex 3.4+ e-invoice coexistence require integration testing against licensed source and runtime;
- serialization still uses the legacy PHP XML view instead of the planned DOM/XMLWriter serializer and offline validator.

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
2. Build or obtain the E-Invoice Pro archive and inspect its checksum and contents.
3. Install the archive from **Setup → Modules**, or place the `einvoice_pro` directory under the Perfex `modules` directory.
4. Confirm that both the directory and initialization file are named `einvoice_pro` and `einvoice_pro.php`.
5. Activate **E-Invoice Pro**.
6. Open **Setup → Settings → Finance → E-Invoice Pro**.
7. Review every value; do not rely on defaults as proof of correct fiscal identity.
8. Test administrator access, staff permissions, direct URL denial, note management, and XML download using synthetic invoices.

Activation creates only missing module options. Retrying activation does not overwrite a value from an earlier or partially completed installation.

## Upgrade from 1.4.3

Version 1.4.3 is the supported upgrade floor for 2.0.1.

1. Back up the complete module directory and Perfex database together.
2. Record the current E-Invoice Pro registration, bank, language, selected-note, and custom-note values.
3. Replace the 1.4.3 files with the 2.0.1 package without deleting database options.
4. Open **Setup → Modules** and confirm that Perfex detects version 2.0.1.
5. Run the normal Perfex **Upgrade Database** action.
6. Migrations `200_version_200.php` and `201_version_201.php` should execute in order. They intentionally perform no data writes so the 1.4.3 values remain unchanged.
7. Reopen the settings page and compare every recorded value.
8. Verify authorized and unauthorized invoice access through the actual download endpoint.
9. Validate a representative set of synthetic XML documents before considering the upgraded installation operational.

The repository includes a synthetic 1.4.3 option fixture and a local preservation simulation. The migration has not yet been executed against a licensed Perfex 3.4.1 database in this workspace. Perfex module migrations are forward-only; recovery requires restoring the matching files and database backup. Installing older files over a newer database is not a supported downgrade.

## Configuration

The current settings panel contains:

| Setting | Stored option | Notes |
|---|---|---|
| XML note language | `einvoice_pro_xml_language` | English or Romanian preset text |
| Trade Register number | `einvoice_pro_registration_number` | Separate from the Perfex VAT option |
| Legal-capital value | `einvoice_pro_company_legal_form` | Legacy field retained during modernization |
| Payment IBAN | `einvoice_pro_payment_iban` | Requires independent fiscal/business validation |
| Bank name | `einvoice_pro_payment_bank_name` | Plain text |
| Note slots 1–3 | `einvoice_pro_note_1` … `_3` | Empty, preset, or administrator-defined text |
| Custom note choices | `einvoice_pro_custom_notes_1` … `_3` | JSON arrays retained for 1.4.3 compatibility |

Custom notes are limited to 500 UTF-8 characters and 50 values per slot. New values are trimmed, stored as text, displayed as text, and escaped for their final output context. Invalid legacy JSON or oversized legacy values are not overwritten from the note manager; the settings page shows a review warning.

Only administrators may manage module settings and reusable note values. Invoice downloads follow the concrete invoice permission check independently of whether the button is visible.

## Development checks

Run from the module repository root:

```bash
php tests/smoke.php
node --check assets/js/settings.js
node tests/frontend-security.mjs
git diff --check
php scripts/package.php
```

The smoke suite covers route IDs, conservative invoice permissions, filename normalization, UTF-8 and malformed custom-note JSON, English/Romanian language-key parity, module-scoped helper loading, clean activation defaults, and preservation of the complete synthetic 1.4.3 option fixture through activation and migrations 200/201.

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
4. the canonical decimal model and complete supported/unsupported fiscal-case matrix;
5. DOM/XMLWriter serialization;
6. pinned local UBL XSD, EN 16931, and RO_CIUS/RO_NAT artifacts with checksums and licenses;
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
