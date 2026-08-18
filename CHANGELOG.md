# Changelog

All notable changes to E-Invoice Pro are documented here. Versions follow Semantic Versioning where fiscal-output changes require at least a minor release after the 2.0.0 architecture baseline.

## 2.0.0 - Unreleased

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
