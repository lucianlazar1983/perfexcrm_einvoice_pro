<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Serializes a validated canonical document as UBL 2.1 with DOM APIs only.
 */
final class Einvoice_pro_ubl_serializer
{
    private const INVOICE_NS = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const CAC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const CBC_NS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    private DOMDocument $dom;

    /**
     * Produces stable UTF-8 XML bytes from one validated document.
     *
     * @param array<string, mixed> $document
     */
    public function serialize(array $document): string
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;

        $root = $this->dom->createElementNS(self::INVOICE_NS, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::CAC_NS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::CBC_NS);
        $this->dom->appendChild($root);

        $this->cbc($root, 'UBLVersionID', '2.1');
        $this->cbc($root, 'CustomizationID', $document['customization_id']);
        $this->cbc($root, 'ID', $document['id']);
        $this->cbc($root, 'IssueDate', $document['issue_date']);
        $this->optionalCbc($root, 'DueDate', $document['due_date']);
        $this->cbc($root, 'InvoiceTypeCode', $document['type_code']);
        foreach ($document['notes'] as $note) {
            $this->cbc($root, 'Note', $note);
        }
        $this->cbc($root, 'DocumentCurrencyCode', $document['currency']);
        $this->optionalCbc($root, 'TaxCurrencyCode', $document['tax_currency']);

        $this->writeParty($root, 'AccountingSupplierParty', $document['seller']);
        $this->writeParty($root, 'AccountingCustomerParty', $document['buyer']);
        $this->writePayment($root, $document['payment']);
        $this->optionalPaymentTerms($root, $document['payment_terms']);

        foreach ($document['allowances_charges'] as $adjustment) {
            $this->writeAllowanceCharge($root, $adjustment, $document['currency']);
        }

        $this->writeTaxTotal($root, $document, $document['currency']);
        if ($document['tax_currency'] !== null) {
            $accountingTax = $this->cac($root, 'TaxTotal');
            $this->money($accountingTax, 'TaxAmount', $document['tax_amount_accounting'], $document['tax_currency']);
        }

        $this->writeMonetaryTotal($root, $document['totals'], $document['currency']);
        foreach ($document['lines'] as $line) {
            $this->writeLine($root, $line, $document['currency']);
        }

        $xml = $this->dom->saveXML();
        if ($xml === false) {
            throw new RuntimeException('The UBL document could not be serialized.');
        }

        return $xml;
    }

    /**
     * Writes one supplier or customer party using the same UBL structure.
     *
     * @param array<string, mixed> $party
     */
    private function writeParty(DOMElement $root, string $aggregateName, array $party): void
    {
        $aggregate = $this->cac($root, $aggregateName);
        $partyElement = $this->cac($aggregate, 'Party');

        if ($party['endpoint'] !== null) {
            $endpoint = $this->cbc($partyElement, 'EndpointID', $party['endpoint']);
            $endpoint->setAttribute('schemeID', $party['endpoint_scheme']);
        }
        if ($party['legal_id'] !== null) {
            $identification = $this->cac($partyElement, 'PartyIdentification');
            $this->cbc($identification, 'ID', $party['legal_id']);
        }

        $address = $this->cac($partyElement, 'PostalAddress');
        $this->cbc($address, 'StreetName', $party['address']['street']);
        $this->cbc($address, 'CityName', $party['address']['city']);
        $this->optionalCbc($address, 'CountrySubentity', $party['address']['subdivision']);
        $country = $this->cac($address, 'Country');
        $this->cbc($country, 'IdentificationCode', $party['address']['country_code']);

        if ($party['vat_id'] !== null) {
            $taxScheme = $this->cac($partyElement, 'PartyTaxScheme');
            $this->cbc($taxScheme, 'CompanyID', $party['vat_id']);
            $scheme = $this->cac($taxScheme, 'TaxScheme');
            $this->cbc($scheme, 'ID', 'VAT');
        }

        $legalEntity = $this->cac($partyElement, 'PartyLegalEntity');
        $this->cbc($legalEntity, 'RegistrationName', $party['name']);
        $this->optionalCbc($legalEntity, 'CompanyID', $party['legal_id']);
        $this->optionalCbc($legalEntity, 'CompanyLegalForm', $party['legal_form']);

        if ($party['phone'] !== null || $party['email'] !== null) {
            $contact = $this->cac($partyElement, 'Contact');
            $this->optionalCbc($contact, 'Telephone', $party['phone']);
            $this->optionalCbc($contact, 'ElectronicMail', $party['email']);
        }
    }

    /**
     * Writes payment instructions only when they were configured and validated.
     *
     * @param array<string, string|null>|null $payment
     */
    private function writePayment(DOMElement $root, ?array $payment): void
    {
        if ($payment === null) {
            return;
        }

        $paymentMeans = $this->cac($root, 'PaymentMeans');
        $this->cbc($paymentMeans, 'PaymentMeansCode', $payment['means_code']);
        if ($payment['iban'] !== null) {
            $account = $this->cac($paymentMeans, 'PayeeFinancialAccount');
            $this->cbc($account, 'ID', $payment['iban']);
            $this->optionalCbc($account, 'Name', $payment['account_name']);
        }
    }

    /**
     * Writes free-text payment terms when a due date is not sufficient.
     */
    private function optionalPaymentTerms(DOMElement $root, ?string $terms): void
    {
        if ($terms === null) {
            return;
        }

        $paymentTerms = $this->cac($root, 'PaymentTerms');
        $this->cbc($paymentTerms, 'Note', $terms);
    }

    /**
     * Writes one document-level allowance or charge.
     *
     * @param array<string, mixed> $adjustment
     */
    private function writeAllowanceCharge(DOMElement $root, array $adjustment, string $currency): void
    {
        $element = $this->cac($root, 'AllowanceCharge');
        $this->cbc($element, 'ChargeIndicator', $adjustment['charge'] ? 'true' : 'false');
        $this->optionalCbc($element, 'AllowanceChargeReasonCode', $adjustment['reason_code']);
        $this->cbc($element, 'AllowanceChargeReason', $adjustment['reason']);
        if ($adjustment['percentage'] !== null) {
            $this->cbc($element, 'MultiplierFactorNumeric', $adjustment['percentage']);
        }
        $this->money($element, 'Amount', $adjustment['amount'], $currency);
        if ($adjustment['base_amount'] !== null) {
            $this->money($element, 'BaseAmount', $adjustment['base_amount'], $currency);
        }
        $this->writeTaxCategory($element, $adjustment['tax']);
    }

    /**
     * Writes the invoice-currency VAT total and each category breakdown.
     *
     * @param array<string, mixed> $document
     */
    private function writeTaxTotal(DOMElement $root, array $document, string $currency): void
    {
        $taxTotal = $this->cac($root, 'TaxTotal');
        $this->money($taxTotal, 'TaxAmount', $document['totals']['tax_amount'], $currency);

        foreach ($document['tax_subtotals'] as $subtotal) {
            $taxSubtotal = $this->cac($taxTotal, 'TaxSubtotal');
            $this->money($taxSubtotal, 'TaxableAmount', $subtotal['taxable_amount'], $currency);
            $this->money($taxSubtotal, 'TaxAmount', $subtotal['tax_amount'], $currency);
            $this->writeTaxCategory($taxSubtotal, $subtotal, 'TaxCategory', true);
        }
    }

    /**
     * Writes all legal monetary totals, omitting optional zero totals.
     *
     * @param array<string, string> $totals
     */
    private function writeMonetaryTotal(DOMElement $root, array $totals, string $currency): void
    {
        $monetaryTotal = $this->cac($root, 'LegalMonetaryTotal');
        $this->money($monetaryTotal, 'LineExtensionAmount', $totals['line_extension'], $currency);
        $this->money($monetaryTotal, 'TaxExclusiveAmount', $totals['tax_exclusive'], $currency);
        $this->money($monetaryTotal, 'TaxInclusiveAmount', $totals['tax_inclusive'], $currency);
        $this->optionalMoney($monetaryTotal, 'AllowanceTotalAmount', $totals['allowance_total'], $currency);
        $this->optionalMoney($monetaryTotal, 'ChargeTotalAmount', $totals['charge_total'], $currency);
        $this->optionalMoney($monetaryTotal, 'PrepaidAmount', $totals['prepaid'], $currency);
        $this->optionalMoney($monetaryTotal, 'PayableRoundingAmount', $totals['rounding'], $currency);
        $this->money($monetaryTotal, 'PayableAmount', $totals['payable'], $currency);
    }

    /**
     * Writes one invoice line with its VAT classification and price.
     *
     * @param array<string, mixed> $line
     */
    private function writeLine(DOMElement $root, array $line, string $currency): void
    {
        $invoiceLine = $this->cac($root, 'InvoiceLine');
        $this->cbc($invoiceLine, 'ID', $line['id']);
        $quantity = $this->cbc($invoiceLine, 'InvoicedQuantity', $line['quantity']);
        $quantity->setAttribute('unitCode', $line['unit_code']);
        $this->money($invoiceLine, 'LineExtensionAmount', $line['net_amount'], $currency);

        $item = $this->cac($invoiceLine, 'Item');
        $this->optionalCbc($item, 'Description', $line['description']);
        $this->cbc($item, 'Name', $line['name']);
        $this->writeTaxCategory($item, $line['tax'], 'ClassifiedTaxCategory');

        $price = $this->cac($invoiceLine, 'Price');
        $this->money($price, 'PriceAmount', $line['price_amount'], $currency);
    }

    /**
     * Writes a VAT category in either classified or breakdown context.
     *
     * @param array<string, mixed> $tax
     */
    private function writeTaxCategory(
        DOMElement $parent,
        array $tax,
        string $name = 'TaxCategory',
        bool $includeExemption = false
    ): void
    {
        $category = $this->cac($parent, $name);
        $this->cbc($category, 'ID', $tax['category']);
        if ($tax['category'] !== 'O') {
            $this->cbc($category, 'Percent', $tax['rate']);
        }
        if ($includeExemption) {
            $this->optionalCbc($category, 'TaxExemptionReasonCode', $tax['exemption_code']);
            $this->optionalCbc($category, 'TaxExemptionReason', $tax['exemption_reason']);
        }
        $scheme = $this->cac($category, 'TaxScheme');
        $this->cbc($scheme, 'ID', 'VAT');
    }

    /**
     * Writes a currency-qualified amount.
     */
    private function money(DOMElement $parent, string $name, string $value, string $currency): DOMElement
    {
        $element = $this->cbc($parent, $name, $value);
        $element->setAttribute('currencyID', $currency);

        return $element;
    }

    /**
     * Writes a currency-qualified amount only when it is non-zero.
     */
    private function optionalMoney(DOMElement $parent, string $name, string $value, string $currency): void
    {
        if (!Einvoice_pro_decimal::isZero($value, 2)) {
            $this->money($parent, $name, $value, $currency);
        }
    }

    /**
     * Appends one common basic component with a text node.
     */
    private function cbc(DOMElement $parent, string $name, string $value): DOMElement
    {
        $element = $this->dom->createElementNS(self::CBC_NS, 'cbc:' . $name);
        $element->appendChild($this->dom->createTextNode($value));
        $parent->appendChild($element);

        return $element;
    }

    /**
     * Appends an optional common basic component when it has a value.
     */
    private function optionalCbc(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null) {
            $this->cbc($parent, $name, $value);
        }
    }

    /**
     * Appends one common aggregate component.
     */
    private function cac(DOMElement $parent, string $name): DOMElement
    {
        $element = $this->dom->createElementNS(self::CAC_NS, 'cac:' . $name);
        $parent->appendChild($element);

        return $element;
    }
}
